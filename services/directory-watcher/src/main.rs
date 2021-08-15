#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use std::collections::VecDeque;
use std::fs::read_dir;
use std::time::Duration;

use crate::events::{send_file_changed, send_file_created, send_file_deleted, send_file_moved};
use crate::platform::events::EventSender;
use crate::platform::secrets::SecretProvider;
use chrono::{DateTime, Utc};
use lapin::{Connection, ConnectionProperties};
use native_tls::TlsConnector;
use notify::{DebouncedEvent, RecommendedWatcher, RecursiveMode, Watcher};
use postgres_native_tls::MakeTlsConnector;
use tokio_amqp::LapinTokioExt;
use tokio_postgres::Client;

mod events;
mod platform;

#[macro_use]
extern crate serde;

#[macro_use]
extern crate thiserror;

#[macro_use]
extern crate tracing;

#[derive(Debug)]
enum FileStatusSyncResult {
    Modified,
    NotModified,
}

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

struct WatchableMount {
    path: String,
    mount_id: String,
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    let _guard = tracing::subscriber::set_default(subscriber);

    let secret_provider = SecretProvider::new("/etc/svc-events/secrets/");
    let connection = connect_to_rabbit(&secret_provider).await?;
    let es = EventSender::new(connection)?;
    let mut pg_client = connect_to_postgres(&secret_provider).await?;
    let directories_from_env = std::env::var("DW_DIRECTORIES_TO_WATCH")?;

    info!("Initialization completed");

    let directories = parse_directories_to_watch(&directories_from_env);

    let (sender, receiver) = std::sync::mpsc::channel();
    let mut watcher = notify::watcher(sender, Duration::from_secs(1))?;

    pre_scan_directories(&es, &mut pg_client, &directories, &mut watcher).await?;

    for item in receiver {
        match item {
            DebouncedEvent::NoticeWrite(x) => info!("Notice write: {}", x.to_string_lossy()),
            DebouncedEvent::NoticeRemove(x) => info!("Notice remove: {}", x.to_string_lossy()),
            DebouncedEvent::Create(x) => {
                let path = x.to_string_lossy();
                let mount_relative_path = find_mount_for_path(&directories, &path)?;
                send_file_created(&es, &mount_relative_path.path, mount_relative_path.mount_id)
                    .await?;
            }
            DebouncedEvent::Chmod(x) | DebouncedEvent::Write(x) => {
                let path = x.to_string_lossy();
                let mount_relative_path = find_mount_for_path(&directories, &path)?;
                send_file_changed(&es, &mount_relative_path.path, mount_relative_path.mount_id)
                    .await?;
            }
            DebouncedEvent::Remove(x) => {
                let path = x.to_string_lossy();
                let mount_relative_path = find_mount_for_path(&directories, &path)?;
                send_file_deleted(&es, &mount_relative_path.path, mount_relative_path.mount_id)
                    .await?;
            }
            DebouncedEvent::Rename(x, y) => {
                let path_from = x.to_string_lossy();
                let path_to = y.to_string_lossy();

                let path_relative_from = find_mount_for_path(&directories, &path_from)?;
                let path_relative_to = find_mount_for_path(&directories, &path_to)?;

                // fixme in that case this should be evented as delete and create
                assert_eq!(
                    path_relative_from.mount_id, path_relative_to.mount_id,
                    "File was moved between mounts, this is not supported"
                );

                send_file_moved(
                    &es,
                    &path_relative_from.path,
                    &path_relative_to.path,
                    path_relative_from.mount_id,
                )
                .await?;
            }
            DebouncedEvent::Rescan => info!("Rescan!"),
            DebouncedEvent::Error(x, y) => error!(
                "Error: {} (at {})",
                x,
                y.map_or("".into(), |z| z.to_string_lossy().to_string())
            ),
        }
    }

    Ok(())
}

#[derive(Error, Debug)]
enum MountForPathError {
    #[error("No mount found for the path")]
    MountNotFound,
}

struct MountRelativePath<'a> {
    mount_id: &'a str,
    path: String,
}

fn find_mount_for_path<'a>(
    mounts: &'a [WatchableMount],
    path: &str,
) -> Result<MountRelativePath<'a>, MountForPathError> {
    for mount in mounts {
        if let Some(relative_path) = pathdiff::diff_paths(path, &mount.path) {
            return Ok(MountRelativePath {
                mount_id: &mount.mount_id,
                path: relative_path.to_string_lossy().to_string(),
            });
        }
    }

    Err(MountForPathError::MountNotFound)
}

#[derive(Error, Debug)]
enum PreScanDirectoriesError {
    #[error("Notify failed")]
    Notify(#[from] notify::Error),
    #[error("IO error")]
    Io(#[from] std::io::Error),
    #[error("Failure publishing the event")]
    Event(#[from] platform::events::Error),
}

async fn pre_scan_directories(
    es: &EventSender,
    mut pg_client: &mut Client,
    directories: &[WatchableMount],
    watcher: &mut RecommendedWatcher,
) -> Result<(), PreScanDirectoriesError> {
    for dir in directories {
        info!("Watching: {} ({})", dir.path, dir.mount_id);
        watcher.watch(dir.path.clone(), RecursiveMode::Recursive)?;

        let mut to_check: VecDeque<String> = VecDeque::new();
        to_check.push_back(dir.path.clone());

        while let Some(path) = to_check.pop_front() {
            let result = read_dir(path)?;
            for entry in result {
                let entry = entry?;
                let metadata = entry.metadata()?;
                let path = entry.path().to_string_lossy().to_string();

                if metadata.is_dir() {
                    to_check.push_back(path);
                } else {
                    let raw_relative_path = pathdiff::diff_paths(path.clone(), &dir.path)
                        .expect("Failed to diff paths");
                    let raw_relative_path_str = raw_relative_path.to_string_lossy();
                    let relative_path = raw_relative_path_str.as_ref();
                    let sync_status = sync_file_status(
                        &mut pg_client,
                        &dir.mount_id,
                        relative_path,
                        DateTime::from(metadata.modified()?),
                    )
                    .await;
                    info!("Found file: {} ({:?})", path, sync_status);
                    if let Ok(FileStatusSyncResult::Modified) = sync_status {
                        send_file_created(es, relative_path, &dir.mount_id).await?;
                    }
                }
            }
        }
    }
    Ok(())
}

fn parse_directories_to_watch(directories_from_env: &str) -> Vec<WatchableMount> {
    directories_from_env
        .split(',')
        .map(|x| x.split(':').collect())
        .map(|x: Vec<&str>| WatchableMount {
            path: x[0].into(),
            mount_id: x[1].into(),
        })
        .collect()
}

#[derive(Error, Debug)]
enum PostgresConnectionError {
    #[error("Failed to read secret")]
    SecretFailed(#[from] platform::secrets::Error),
    #[error("Failed to connect to postgres")]
    PostgresFailed(#[from] tokio_postgres::Error),
    #[error("TLS error")]
    Tls(#[from] native_tls::Error),
}

async fn connect_to_postgres(
    secret_provider: &SecretProvider<'_>,
) -> Result<Client, PostgresConnectionError> {
    let pg_secret = secret_provider.read("directory-watcher.ap-directory-watcher.credentials")?;

    // fixme verify the root cert
    let tls_connector = TlsConnector::builder()
        .danger_accept_invalid_certs(true)
        .build()?;

    let (pg_client, pg_connection) = tokio_postgres::connect(
        &format!(
            "host=ap-directory-watcher sslmode=require user={} password={}",
            pg_secret.username(),
            pg_secret.password()
        ),
        MakeTlsConnector::new(tls_connector),
    )
    .await?;

    tokio::spawn(async move {
        if let Err(e) = pg_connection.await {
            error!("connection error: {}", e);
        }
    });
    Ok(pg_client)
}

#[derive(Error, Debug)]
enum RabbitConnectionError {
    #[error("Failed to read secret")]
    SecretFailed(#[from] platform::secrets::Error),
    #[error("RabbitMQ connection failed")]
    LapinFailed(#[from] lapin::Error),
}

async fn connect_to_rabbit(
    secret_provider: &SecretProvider<'_>,
) -> Result<Connection, RabbitConnectionError> {
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
    Ok(connection)
}
