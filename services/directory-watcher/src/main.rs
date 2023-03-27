#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use crate::file_status_store::Postgres;
use crate::rpc_server::{EventsReverseRpc, RpcServer};
use platform::secrets::SecretProvider;
use std::sync::Arc;
use tokio::net::{TcpListener, TcpStream};
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
        .await
        .unwrap(),
    ));
    let file_status_store = Postgres::new(pg_client.clone());
    let connection =
        events::ClientConnection::from_tcp_stream(TcpStream::connect("svc-events:7654").await?);
    let requester = connection.run(Arc::new(EventsReverseRpc {})).await;
    let rpc_server = Arc::new(RpcServer::new(
        Mutex::new(file_status_store),
        requester,
        Box::new(Uuid::new_v4),
    ));
    // TODO: make the bind addr/port configurable

    let listener = TcpListener::bind("0.0.0.0:7655").await?;

    info!("Initialization completed");
    while let Ok((client, socket)) = listener.accept().await {
        info!("Client accepted {socket:?}");
        let stream = lib_directory_watcher::ServerConnection::from_tcp_stream(client);

        tokio::spawn(stream.run(rpc_server.clone()));
    }
    // let server = Server::new("0.0.0.0:7655", Arc::new(Mutex::new(rpc_server))).await?;

    // server.run().await.unwrap();

    Ok(())
}
