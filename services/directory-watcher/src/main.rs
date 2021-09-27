#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use crate::file_status_store::{Error, Postgres};
use crate::filesystem_events::FilesystemEventHandler;
use crate::mount::Mount;
use crate::platform::events::RabbitMQ;
use crate::platform::secrets::SecretProvider;
use crate::scan::Scanner;
use lapin::{Connection, ConnectionProperties};
use native_tls::TlsConnector;
use notify::{RecursiveMode, Watcher};
use postgres_native_tls::MakeTlsConnector;
use std::sync::Arc;
use std::time::Duration;
use tokio::sync::Mutex;
use tokio_amqp::LapinTokioExt;
use tokio_postgres::Client;

mod events;
mod file_status_store;
mod filesystem_events;
mod mount;
mod platform;
mod scan;

#[macro_use]
extern crate serde;

#[macro_use]
extern crate thiserror;

#[macro_use]
extern crate tracing;

#[macro_use]
extern crate async_trait;

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    let _guard = tracing::subscriber::set_default(subscriber);

    let secret_provider = SecretProvider::new("/etc/svc-events/secrets/");
    let connection = connect_to_rabbit(&secret_provider).await?;
    let es = Arc::new(RabbitMQ::new(connection)?);
    let pg_client = Arc::new(Mutex::new(connect_to_postgres(&secret_provider).await?));
    let directories_from_env = std::env::var("DW_DIRECTORIES_TO_WATCH")?;
    let file_status_store = Arc::new(Mutex::new(Postgres::new(pg_client.clone())));
    let mut scanner = Scanner::new(es.clone(), file_status_store.clone());

    info!("Initialization completed");

    let mounts = parse_mounts(&directories_from_env);

    let (sender, receiver) = std::sync::mpsc::channel();
    let mut watcher = notify::watcher(sender, Duration::from_secs(1))?;
    let filesystem_event_handler =
        FilesystemEventHandler::new(es.clone(), file_status_store.clone(), &mounts);

    for mount in &mounts {
        watcher.watch(&mount.path(), RecursiveMode::Recursive)?;
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
    #[error("Failed to publish an event")]
    Events(#[from] platform::events::Error),
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
