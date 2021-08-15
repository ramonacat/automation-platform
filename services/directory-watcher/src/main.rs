#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use crate::events::EventSender;
use crate::secrets::SecretProvider;
use chrono::{DateTime, Utc};
use lapin::ConnectionProperties;
use native_tls::TlsConnector;
use notify::{DebouncedEvent, RecursiveMode, Watcher};
use postgres_native_tls::MakeTlsConnector;
use std::collections::VecDeque;
use std::fs::read_dir;
use std::time::Duration;
use tokio_amqp::LapinTokioExt;
use uuid::Uuid;

mod events;
mod secrets;

#[macro_use]
extern crate serde;

#[macro_use]
extern crate thiserror;

#[derive(Debug)]
enum FileStatusSyncResult {
    Modified,
    NotModified,
}

#[derive(Serialize)]
struct FileCreated<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    path: &'a str,
    mount_id: &'a str,
}

impl<'a> events::Event for FileCreated<'a> {}

#[derive(Error, Debug)]
enum SyncError {
    #[error("Problems communicating with the database")]
    Database(#[from] tokio_postgres::Error),
}

async fn sync_file_status(
    pg_client: &mut tokio_postgres::Client,
    mount_id: &str,
    path: &str,
    modified_at: DateTime<Utc>,
) -> Result<FileStatusSyncResult, SyncError> {
    let transaction = pg_client.transaction().await?;

    let rows = transaction
        .query(
            "SELECT modified_date FROM files WHERE mount_id=$1 AND path=$2 FOR UPDATE",
            &[&mount_id, &path],
        )
        .await?;

    if rows.is_empty() {
        transaction
            .execute("INSERT INTO files (id, mount_id, path, modified_date) VALUES(gen_random_uuid(), $1, $2, $3)", &[&mount_id, &path, &modified_at])
            .await?;

        transaction.commit().await?;
        return Ok(FileStatusSyncResult::Modified);
    }

    let current_modified_at = rows.get(0).expect("No row?").get::<_, DateTime<Utc>>(0);
    if current_modified_at != modified_at {
        transaction
            .execute(
                "UPDATE files SET modified_date=$1 WHERE mount_id=$2 AND path=$3",
                &[&modified_at, &mount_id, &path],
            )
            .await?;

        transaction.commit().await?;
        return Ok(FileStatusSyncResult::Modified);
    }

    transaction.commit().await?;
    Ok(FileStatusSyncResult::NotModified)
}

async fn send_file_created(
    es: &EventSender,
    path: &str,
    mount_id: &str,
) -> Result<(), events::Error> {
    es.send(FileCreated {
        id: Uuid::new_v4(),
        created_timestamp: Utc::now(),
        type_name: "file.status.created",
        path,
        mount_id,
    })
    .await?;

    Ok(())
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let secret_provider = SecretProvider::new("/etc/svc-events/secrets/");
    let rabbit_secret = secret_provider.read("rmq-events-default-user")?;
    let connection = lapin::Connection::connect(
        &format!(
            "amqp://{}:{}@rmq-events:5672",
            rabbit_secret.username(),
            rabbit_secret.password()
        ),
        ConnectionProperties::default().with_tokio(),
    )
    .await?;
    let es = EventSender::new(connection)?;
    let pg_secret = secret_provider.read("directory-watcher.ap-directory-watcher.credentials")?;

    // fixme verify the root cert
    let tls_connector = TlsConnector::builder()
        .danger_accept_invalid_certs(true)
        .build()?;

    println!("Created tls connector");

    let (mut pg_client, pg_connection) = tokio_postgres::connect(
        &format!(
            "host=ap-directory-watcher sslmode=require user={} password={}",
            pg_secret.username(),
            pg_secret.password()
        ),
        MakeTlsConnector::new(tls_connector),
    )
    .await
    .expect("Failed to connect to PostgreSQL");

    tokio::spawn(async move {
        if let Err(e) = pg_connection.await {
            eprintln!("connection error: {}", e);
        }
    });

    println!("pgsql connection created");

    let directories_from_env = std::env::var("DW_DIRECTORIES_TO_WATCH")?;
    let directories: Vec<Vec<&str>> = directories_from_env
        .split(',')
        .map(|x| x.split(':').collect())
        .collect();

    let (sender, receiver) = std::sync::mpsc::channel();
    let mut watcher = notify::watcher(sender, Duration::from_secs(1))?;

    for dir in directories {
        println!("Watching: {} ({})", dir[0], dir[1]);
        watcher.watch(dir[0], RecursiveMode::Recursive)?;

        let mut to_check: VecDeque<String> = VecDeque::new();
        to_check.push_back(dir[0].to_string());

        while let Some(path) = to_check.pop_front() {
            let result = read_dir(path)?;
            for entry in result {
                let entry = entry?;
                let metadata = entry.metadata()?;
                let path = entry
                    .path()
                    .to_str()
                    .expect("Entry has no path")
                    .to_string();

                if metadata.is_dir() {
                    to_check.push_back(path);
                } else {
                    println!("Found file: {}", path);
                    let raw_relative_path =
                        pathdiff::diff_paths(path.clone(), dir[0]).expect("Failed to diff paths");
                    let raw_relative_path_str = raw_relative_path.to_string_lossy();
                    let relative_path = raw_relative_path_str.as_ref();
                    let sync_status = sync_file_status(
                        &mut pg_client,
                        dir[1],
                        relative_path,
                        DateTime::from(metadata.modified().expect("Failed to get modified date")),
                    )
                    .await;
                    println!("Found file: {} ({:?})", path, sync_status);
                    if let Ok(FileStatusSyncResult::Modified) = sync_status {
                        send_file_created(&es, relative_path, dir[1]).await?;
                    }
                }
            }
        }
    }

    for item in receiver {
        match item {
            DebouncedEvent::NoticeWrite(x) => println!("Notice write: {}", x.to_string_lossy()),
            DebouncedEvent::NoticeRemove(x) => println!("Notice remove: {}", x.to_string_lossy()),
            DebouncedEvent::Create(x) => println!("Create: {}", x.to_string_lossy()),
            DebouncedEvent::Write(x) => println!("Write: {}", x.to_string_lossy()),
            DebouncedEvent::Chmod(x) => println!("Chmod: {}", x.to_string_lossy()),
            DebouncedEvent::Remove(x) => println!("Remove: {}", x.to_string_lossy()),
            DebouncedEvent::Rename(x, y) => {
                println!("Rename: {} -> {}", x.to_string_lossy(), y.to_string_lossy())
            }
            DebouncedEvent::Rescan => println!("Rescan!"),
            DebouncedEvent::Error(x, y) => println!(
                "Error: {} (at {})",
                x,
                y.map_or("".into(), |z| z.to_string_lossy().to_string())
            ),
        }
    }

    Ok(())
}
