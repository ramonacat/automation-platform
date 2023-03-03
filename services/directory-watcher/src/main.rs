#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use crate::file_status_store::Postgres;
use crate::rpc_server::RpcServer;
use lib_directory_watcher::Server;
use platform::async_infra::run_with_error_handling;
use platform::secrets::SecretProvider;
use rpc_support::DefaultRawRpcClient;
use std::sync::Arc;
use tokio::net::TcpStream;
use tokio::sync::Mutex;
use uuid::Uuid;

mod file_status_store;
mod rpc_server;

#[macro_use]
extern crate thiserror;

#[macro_use]
extern crate tracing;

#[macro_use]
extern crate async_trait;

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    let _guard = tracing::subscriber::set_global_default(subscriber);

    let secret_provider = SecretProvider::new("/etc/svc-events/secrets/");

    let pg_client = Arc::new(Mutex::new(
        platform::postgres::connect(
            &secret_provider,
            "ap-directory-watcher",
            "directory-watcher.ap-directory-watcher.credentials",
        )
        .await?,
    ));
    let file_status_store = Postgres::new(pg_client.clone());
    let event_service = events::Client::new(DefaultRawRpcClient::new(
        TcpStream::connect("svc-events:7654").await?,
    ));
    let rpc_server = RpcServer::new(file_status_store, event_service, Box::new(Uuid::new_v4));
    // TODO: make the bind addr/port configurable
    let server = Server::new("0.0.0.0:7655", Arc::new(Mutex::new(rpc_server))).await?;

    tokio::spawn(run_with_error_handling(async move { server.run().await }));

    info!("Initialization completed");

    Ok(())
}
