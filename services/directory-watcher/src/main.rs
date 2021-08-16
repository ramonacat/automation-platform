#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use std::collections::VecDeque;
use std::fs::read_dir;
use std::time::Duration;

use crate::events::{send_file_changed, send_file_created, send_file_deleted, send_file_moved};
use crate::file_status_store::{FileStatusStore, FileStatusSyncResult, SyncError};
use crate::mount::{Error, Mount, PathInside};
use crate::platform::events::EventSender;
use crate::platform::secrets::SecretProvider;
use chrono::{DateTime, Utc};
use lapin::{Connection, ConnectionProperties};
use native_tls::TlsConnector;
use notify::{DebouncedEvent, RecursiveMode, Watcher};
use postgres_native_tls::MakeTlsConnector;
use std::path::PathBuf;
use std::sync::mpsc::Receiver;
use tokio_amqp::LapinTokioExt;
use tokio_postgres::Client;

mod events;
mod file_status_store;
mod mount;
mod platform;

#[macro_use]
extern crate serde;

#[macro_use]
extern crate thiserror;

#[macro_use]
extern crate tracing;

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

    let mounts = parse_mounts(&directories_from_env);

    let (sender, receiver) = std::sync::mpsc::channel();
    let mut watcher = notify::watcher(sender, Duration::from_secs(1))?;

    for mount in &mounts {
        watcher.watch(&mount.path(), RecursiveMode::Recursive)?;
    }

    pre_scan_directories(&es, &mut file_status_store, &mounts).await?;
    handle_events(&es, &mut file_status_store, &mounts, receiver).await?;

    Ok(())
}

#[derive(Error, Debug)]
pub enum HandleEventsError {
    #[error("Couldn't find a mount for path")]
    Mount(#[from] mount::Error),
    #[error("Syncing the state failed")]
    Sync(#[from] SyncError),
    #[error("Failed to publish an event")]
    Events(#[from] platform::events::Error),
}

async fn handle_events(
    es: &EventSender,
    file_status_store: &mut FileStatusStore<'_>,
    mounts: &[Mount],
    receiver: Receiver<DebouncedEvent>,
) -> Result<(), HandleEventsError> {
    for item in receiver {
        match item {
            DebouncedEvent::NoticeWrite(x) => info!("Notice write: {}", x.to_string_lossy()),
            DebouncedEvent::NoticeRemove(x) => info!("Notice remove: {}", x.to_string_lossy()),
            DebouncedEvent::Create(x) => {
                let mount_relative_path = PathInside::from_mount_list(mounts, &x)?;

                // fixme don't use Utc::now, but the actual modified date here!
                file_status_store
                    .sync(&mount_relative_path, Utc::now())
                    .await?;
                send_file_created(es, &mount_relative_path).await?;
            }
            DebouncedEvent::Chmod(x) | DebouncedEvent::Write(x) => {
                let mount_relative_path = PathInside::from_mount_list(mounts, &x)?;
                // fixme don't use Utc::now, but the actual modified date here!
                file_status_store
                    .sync(&mount_relative_path, Utc::now())
                    .await?;
                send_file_changed(es, &mount_relative_path).await?;
            }
            DebouncedEvent::Remove(x) => {
                let mount_relative_path = PathInside::from_mount_list(mounts, &x)?;
                file_status_store.delete(&mount_relative_path).await?;
                send_file_deleted(es, &mount_relative_path).await?;
            }
            DebouncedEvent::Rename(x, y) => {
                let path_relative_from = PathInside::from_mount_list(mounts, &x)?;
                let path_relative_to = PathInside::from_mount_list(mounts, &y)?;

                file_status_store
                    .rename(&path_relative_from, &path_relative_to)
                    .await?;
                send_file_moved(es, &path_relative_from, &path_relative_to).await?;
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
    directories: &[Mount],
) -> Result<(), PreScanDirectoriesError> {
    for dir in directories {
        info!("Watching: {} ({})", dir.path().to_string_lossy(), dir.id());

        let mut to_check: VecDeque<PathBuf> = VecDeque::new();
        to_check.push_back(dir.path().to_path_buf());

        while let Some(path) = to_check.pop_front() {
            let result = read_dir(path)?;
            for entry in result {
                let entry = entry?;
                let metadata = entry.metadata()?;
                let path = entry.path();

                if metadata.is_dir() {
                    to_check.push_back(path.clone());
                } else {
                    let mount_relative_path = PathInside::from_absolute(dir, path.clone())?;
                    let sync_status = file_status_store
                        .sync(&mount_relative_path, DateTime::from(metadata.modified()?))
                        .await;
                    info!("Found file: {} ({:?})", path.to_string_lossy(), sync_status);
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

fn parse_mounts(directories_from_env: &str) -> Vec<Mount> {
    directories_from_env
        .split(',')
        .map(|x| x.split(':').collect())
        .map(|x: Vec<&str>| Mount::new(x[1].into(), x[0].into()))
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
