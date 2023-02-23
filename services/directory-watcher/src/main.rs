#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use crate::file_status_store::{Error, Postgres};
use crate::filesystem_events::FilesystemEventHandler;
use crate::scan::Scanner;
use events::Metadata;
use notify::{PollWatcher, RecursiveMode, Watcher};
use platform::secrets::SecretProvider;
use rpc_support::DefaultRawRpcClient;
use std::sync::Arc;
use tokio::net::TcpStream;
use tokio::sync::Mutex;

mod file_status_store;
mod filesystem_events;
mod scan;

#[macro_use]
extern crate thiserror;

#[macro_use]
extern crate tracing;

#[macro_use]
extern crate async_trait;

fn create_event_metadata() -> Metadata {
    Metadata {
        source: "directory-watcher".to_string(),
        correlation_id: uuid::Uuid::new_v4(),
    }
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    let _guard = tracing::subscriber::set_default(subscriber);

    let secret_provider = SecretProvider::new("/etc/svc-events/secrets/");
    let es_watcher = events::Client::new(DefaultRawRpcClient::new(
        TcpStream::connect("svc-events:7654").await?,
    ));
    let es_scanner = events::Client::new(DefaultRawRpcClient::new(
        TcpStream::connect("svc-events:7654").await?,
    ));
    let configuration = platform::configuration::Configuration::new()?;
    let pg_client = Arc::new(Mutex::new(
        platform::postgres::connect(
            &secret_provider,
            "ap-directory-watcher",
            "directory-watcher.ap-directory-watcher.credentials",
        )
        .await?,
    ));
    let directories_from_env = configuration.get_string("$.mounts")?;
    let file_status_store = Arc::new(Mutex::new(Postgres::new(pg_client.clone())));
    let mounts = Arc::new(Mutex::new(platform::mounts::Provider::from_raw_string(
        &directories_from_env,
    )));
    let mut scanner = Scanner::new(
        Arc::new(Mutex::new(es_scanner)),
        file_status_store.clone(),
        mounts.clone(),
    );

    info!("Initialization completed");

    let (sender, receiver) = std::sync::mpsc::channel();
    // The PollWatcher is used, because the inotify watcher does not work with NFS mounts.
    // todo asses performance impact, find a better solution?
    let mut watcher = PollWatcher::new(sender, notify::Config::default())?;
    let filesystem_event_handler = FilesystemEventHandler::new(
        Arc::new(Mutex::new(es_watcher)),
        file_status_store.clone(),
        mounts.clone(),
    );

    for mount in mounts.lock().await.mounts() {
        watcher.watch(mount.path(), RecursiveMode::Recursive)?;
    }

    scanner.scan().await?;
    filesystem_event_handler.handle_events(receiver).await?;

    Ok(())
}

#[derive(Error, Debug)]
pub enum HandleEventsError {
    #[error("Couldn't find a mount for path")]
    Mount(#[from] platform::mounts::MountError),
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
