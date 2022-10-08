#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use crate::file_status_store::{Error, Postgres};
use crate::filesystem_events::FilesystemEventHandler;
use crate::mount::Mount;
use crate::scan::Scanner;
use events::Metadata;
use native_tls::TlsConnector;
use notify::{PollWatcher, RecursiveMode, Watcher};
use platform::secrets::SecretProvider;
use postgres_native_tls::MakeTlsConnector;
use std::sync::Arc;
use std::time::SystemTime;
use tokio::sync::Mutex;
use tokio_postgres::Client;
use uuid::Uuid;

mod file_status_store;
mod filesystem_events;
mod mount;
mod scan;

#[macro_use]
extern crate thiserror;

#[macro_use]
extern crate tracing;

#[macro_use]
extern crate async_trait;

fn create_event_metadata() -> Metadata {
    Metadata {
        created_time: SystemTime::now(),
        source: "directory-watcher".to_string(),
        id: Uuid::new_v4(),
    }
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    let _guard = tracing::subscriber::set_default(subscriber);

    let secret_provider = SecretProvider::new("/etc/svc-events/secrets/");
    let es_watcher = events::Client::new("svc-events:7654").await?;
    let es_scanner = events::Client::new("svc-events:7654").await?;
    let configuration = platform::configuration::Configuration::new()?;
    let pg_client = Arc::new(Mutex::new(connect_to_postgres(&secret_provider).await?));
    let directories_from_env = configuration.get_string("$.mounts")?;
    let file_status_store = Arc::new(Mutex::new(Postgres::new(pg_client.clone())));
    let mut scanner = Scanner::new(Arc::new(Mutex::new(es_scanner)), file_status_store.clone());

    info!("Initialization completed");

    let mounts = parse_mounts(&directories_from_env);

    let (sender, receiver) = std::sync::mpsc::channel();
    // The PollWatcher is used, because the inotify watcher does not work with NFS mounts.
    // todo asses performance impact, find a better solution?
    let mut watcher = PollWatcher::new(sender, notify::Config::default())?;
    let filesystem_event_handler = FilesystemEventHandler::new(
        Arc::new(Mutex::new(es_watcher)),
        file_status_store.clone(),
        &mounts,
    );

    for mount in &mounts {
        watcher.watch(mount.path(), RecursiveMode::Recursive)?;
    }

    scanner.scan(&mounts).await?;
    filesystem_event_handler.handle_events(receiver).await?;

    Ok(())
}

#[derive(Error, Debug)]
pub enum HandleEventsError {
    #[error("Couldn't find a mount for path")]
    Mount(#[from] mount::Error),
    #[error("Syncing the state failed")]
    Sync(#[from] Error),
    #[error("RPC call failed")]
    Rpc(#[from] rpc_support::rpc_error::RpcError),
    #[error("IO error")]
    Io(#[from] std::io::Error),
    #[error("Notify error")]
    NotifyError(#[from] notify::Error),
    #[error("Path is missing")]
    MissingPath,
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
    SecretFailed(#[from] ::platform::secrets::Error),
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
