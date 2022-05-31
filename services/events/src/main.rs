#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use std::error::Error;
use tracing::info;

use tokio_postgres::Client;

use events::{Event, Metadata, Rpc, Server};
use platform::async_infra::run_with_error_handling;
use postgres_native_tls::MakeTlsConnector;
use rpc_support::rpc_error::RpcError;
use serde::Serialize;
use std::sync::Arc;
use tokio::sync::Mutex;

#[macro_use]
extern crate async_trait;

struct RpcServer {
    postgres: Arc<Mutex<Client>>,
}

fn rpc_error_map(e: impl Error) -> RpcError {
    RpcError::Custom(e.to_string())
}

impl RpcServer {
    async fn save_event<T>(
        &mut self,
        name: &str,
        message: T,
        metadata: Metadata,
    ) -> Result<(), RpcError>
    where
        T: Serialize + Send,
    {
        // fixme this is gross, is there a way to avoid reserializing?
        let serde_value =
            serde_json::to_value(serde_json::to_string(&message).map_err(rpc_error_map)?)
                .map_err(rpc_error_map)?;
        self.postgres
            .lock()
            .await
            .execute(
                "INSERT INTO events(id, created_timestamp, type, data) VALUES($1,$2,$3,$4)",
                &[&metadata.id, &metadata.created_time, &name, &serde_value],
            )
            .await
            .map_err(rpc_error_map)?;

        info!(
            "Message handled: {}",
            serde_json::to_string(&message).map_err(rpc_error_map)?
        );

        Ok(())
    }
}

#[async_trait]
impl Rpc for RpcServer {
    async fn send_event(&mut self, request: Event, metadata: Metadata) -> Result<(), RpcError> {
        self.save_event(
            match request {
                Event::FileDeleted { .. } => "FileDeleted",
                Event::FileCreated { .. } => "FileCreated",
                Event::FileMoved { .. } => "FileMoved",
                Event::FileChanged { .. } => "FileChanged",
            },
            request,
            metadata,
        )
        .await?;

        Ok(())
    }
}

#[tokio::main]
#[tracing::instrument]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let secret_provider = platform::secrets::SecretProvider::new("/etc/svc-events/secrets/");
    let secret = secret_provider.read("events.ap-events.credentials")?;

    let tls_connector = MakeTlsConnector::new(
        native_tls::TlsConnector::builder()
            .danger_accept_invalid_certs(true) // fixme don't accept invalid certs!
            .build()?,
    );

    let (client, connection) = tokio_postgres::connect(
        &format!(
            "host=ap-events user={} password={}",
            secret.username(),
            secret.password()
        ),
        tls_connector,
    )
    .await?;

    tokio::spawn(async move {
        run_with_error_handling(connection).await;
    });

    // todo make the bind addr/port configurable
    let server = Server::new(
        "0.0.0.0:7654",
        Arc::new(Mutex::new(RpcServer {
            postgres: Arc::new(Mutex::new(client)),
        })),
    )
    .await?;
    server.run().await?;

    Ok(())
}
