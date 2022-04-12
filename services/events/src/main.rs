#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use tokio::io::{AsyncBufReadExt, AsyncWriteExt, BufReader};
use tokio::net::{TcpListener, TcpStream};

use std::error::Error;
use std::future::Future;
use std::net::SocketAddr;
use tracing::{error, info};

use tokio_postgres::Client;

use events::MessagePayload;
use platform::events::{Response, Status};
use postgres_native_tls::MakeTlsConnector;
use std::fmt::{Debug, Formatter};
use std::sync::Arc;
use thiserror::Error;

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
    let listener = TcpListener::bind("0.0.0.0:7654").await?;

    // todo can we get rid of the Arc? ThreadLocal?
    let client = Arc::new(client);
    loop {
        let (socket, address) = listener.accept().await?;
        info!("New client connected: {}", address);

        let client = client.clone();
        tokio::spawn(async move {
            run_with_error_handling(async move {
                let client = EventsClient::new(socket, address, client.as_ref());
                client.handle().await?;

                Ok::<_, ClientHandlingError>(())
            })
            .await;
        });
    }
}

async fn run_with_error_handling<TError>(callback: impl Future<Output = Result<(), TError>> + Send)
where
    TError: Error,
{
    if let Err(e) = callback.await {
        error!("Task failed: {}", e);
    }
}

#[derive(Error, Debug)]
enum ClientHandlingError {
    #[error("IO Error: {0}")]
    IoError(#[from] std::io::Error),
    #[error("Database Error: {0}")]
    DatabaseError(#[from] tokio_postgres::Error),
    #[error("JSON Parsing Failed: {0}")]
    JsonParsingFailed(#[from] serde_json::Error),
    #[error("Failed to construct the client: {0}")]
    ClientConstructionFailed(#[from] EventsClientConstructionError),
}

struct EventsClient<'a> {
    socket: TcpStream,
    address: SocketAddr,
    postgres: &'a Client,
}

impl Debug for EventsClient<'_> {
    fn fmt(&self, f: &mut Formatter<'_>) -> std::fmt::Result {
        write!(f, "EventsClient:{}", self.address)
    }
}

#[derive(Debug, Error)]
enum EventsClientConstructionError {
    #[error("JSON Parsing Failed: {0}")]
    JsonParsingFailed(#[from] serde_json::Error),
    #[error("IO Error: {0}")]
    IoError(#[from] std::io::Error),
}

#[derive(Error, Debug)]
enum MessageHandlingError {
    #[error("Database Error: {0}")]
    DatabaseError(#[from] tokio_postgres::Error),
    #[error("JSON Parsing Failed: {0}")]
    JsonParsingFailed(#[from] serde_json::Error),
    #[error("Invalid UUID: {0}")]
    InvalidUuid(#[from] uuid::Error),
    #[error("Invalid Date/Time: {0}")]
    InvalidDateTime(#[from] time::error::Parse),
}

trait TypeName {
    fn type_name(&self) -> &'static str;
}

// todo define a macro to do this automagically? use `strum_macros`?
impl TypeName for MessagePayload {
    fn type_name(&self) -> &'static str {
        match self {
            MessagePayload::FileMoved { .. } => "FileMoved",
            MessagePayload::FileChanged { .. } => "FileChanged",
            MessagePayload::FileCreated { .. } => "FileCreated",
            MessagePayload::FileDeleted { .. } => "FileDeleted",
        }
    }
}

impl<'a> EventsClient<'a> {
    pub const fn new(socket: TcpStream, address: SocketAddr, postgres: &'a Client) -> Self {
        Self {
            socket,
            address,
            postgres,
        }
    }

    #[tracing::instrument]
    async fn handle(mut self) -> Result<(), ClientHandlingError> {
        let (mut reader, mut writer) = self.socket.split();
        let reader = BufReader::new(&mut reader);
        let mut lines = reader.lines();
        while let Some(line) = lines.next_line().await? {
            let response = match Self::handle_message(line, self.postgres).await {
                Ok(_) => Response { status: Status::Ok },
                Err(e) => {
                    error!("Request processing failed: {:?}", e);
                    Response {
                        status: Status::Failed,
                    }
                }
            };
            info!("Sending response: {:?}", response);
            writer.write_all(&serde_json::to_vec(&response)?).await?;
            writer.write_all(b"\n").await?;
        }

        info!("Client disconnected");

        Ok(())
    }

    async fn handle_message(
        line: String,
        postgres: &'_ Client,
    ) -> Result<(), MessageHandlingError> {
        let parsed: ::events::Message = serde_json::from_str(&line)?;
        let value: serde_json::Value = serde_json::from_str(&line)?;

        postgres
            .execute(
                "INSERT INTO events(id, created_timestamp, type, data) VALUES($1,$2,$3,$4)",
                &[
                    &parsed.metadata.id,
                    &parsed.metadata.created_time,
                    &parsed.payload.type_name(),
                    &value,
                ],
            )
            .await?;

        info!("Message handled: {}", line);

        Ok(())
    }
}
