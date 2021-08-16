#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use std::collections::VecDeque;
use std::fs::read_dir;
use std::time::Duration;

use crate::events::{send_file_changed, send_file_created, send_file_deleted, send_file_moved};
use crate::file_status_store::{FileStatusStore, FileStatusSyncResult};
use crate::mount_relative_path::{Error, MountRelativePath};
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
mod file_status_store;
mod mount_relative_path;
mod platform;

#[macro_use]
extern crate serde;

#[macro_use]
extern crate thiserror;

#[macro_use]
extern crate tracing;

pub struct WatchableMount {
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
    let mut file_status_store = FileStatusStore::new(&mut pg_client);

    info!("Initialization completed");

    let directories = parse_directories_to_watch(&directories_from_env);

    let (sender, receiver) = std::sync::mpsc::channel();
    let mut watcher = notify::watcher(sender, Duration::from_secs(1))?;

    pre_scan_directories(&es, &mut file_status_store, &directories, &mut watcher).await?;

    for item in receiver {
        match item {
            DebouncedEvent::NoticeWrite(x) => info!("Notice write: {}", x.to_string_lossy()),
            DebouncedEvent::NoticeRemove(x) => info!("Notice remove: {}", x.to_string_lossy()),
            DebouncedEvent::Create(x) => {
                let path = x.to_string_lossy();
                let mount_relative_path = find_mount_for_path(&directories, &path)?;

                // fixme don't use Utc::now, but the actual modified date here!
                file_status_store
                    .sync(&mount_relative_path, Utc::now())
                    .await?;
                send_file_created(&es, &mount_relative_path).await?;
            }
            DebouncedEvent::Chmod(x) | DebouncedEvent::Write(x) => {
                let path = x.to_string_lossy();
                let mount_relative_path = find_mount_for_path(&directories, &path)?;
                // fixme don't use Utc::now, but the actual modified date here!
                file_status_store
                    .sync(&mount_relative_path, Utc::now())
                    .await?;
                send_file_changed(&es, &mount_relative_path).await?;
            }
            DebouncedEvent::Remove(x) => {
                let path = x.to_string_lossy();
                let mount_relative_path = find_mount_for_path(&directories, &path)?;
                file_status_store.delete(&mount_relative_path).await?;
                send_file_deleted(&es, &mount_relative_path).await?;
            }
            DebouncedEvent::Rename(x, y) => {
                let path_from = x.to_string_lossy();
                let path_to = y.to_string_lossy();

                let path_relative_from = find_mount_for_path(&directories, &path_from)?;
                let path_relative_to = find_mount_for_path(&directories, &path_to)?;

                file_status_store
                    .rename(&path_relative_from, &path_relative_to)
                    .await?;
                send_file_moved(&es, &path_relative_from, &path_relative_to).await?;
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

fn find_mount_for_path<'a>(
    mounts: &'a [WatchableMount],
    path: &str,
) -> Result<MountRelativePath<'a>, MountForPathError> {
    for mount in mounts {
        if let Ok(mount_relative_path) = MountRelativePath::from_absolute(mount, path) {
            return Ok(mount_relative_path);
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
    #[error("Failed to get mount relative path")]
    MountRelativePath(#[from] Error),
}

async fn pre_scan_directories(
    es: &EventSender,
    file_status_store: &mut FileStatusStore<'_>,
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
                    let mount_relative_path = MountRelativePath::from_absolute(dir, &path)?;
                    let sync_status = file_status_store
                        .sync(&mount_relative_path, DateTime::from(metadata.modified()?))
                        .await;
                    info!("Found file: {} ({:?})", path, sync_status);
                    if let Ok(sync_status) = sync_status {
                        match sync_status {
                            FileStatusSyncResult::Created => {
                                send_file_created(es, &mount_relative_path).await?;
                            }
                            FileStatusSyncResult::Modified => {
                                send_file_changed(es, &mount_relative_path).await?;
                            }
                            FileStatusSyncResult::NotModified => {}
                        }
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
