#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use tokio::io::{AsyncBufReadExt, AsyncWriteExt, BufReader};
use tokio::net::{TcpListener, TcpStream};

use std::error::Error;
use std::future::Future;
use std::net::SocketAddr;
use tracing::{error, info};

use tokio_postgres::Client;

use jsonschema::{ErrorIterator, JSONSchema, ValidationError};
use platform::events::{Response, Status};
use postgres_native_tls::MakeTlsConnector;
use serde_json::Value;
use std::fmt::{Debug, Formatter};
use std::sync::Arc;
use thiserror::Error;
use time::format_description::well_known::Rfc3339;
use time::OffsetDateTime;
use uuid::Uuid;

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
                let client = EventsClient::new(socket, address, client.as_ref())?;
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
    match callback.await {
        Err(e) => {
            error!("Task failed: {}", e);
        }
        Ok(()) => {}
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
    #[error("Multiple schema validation errors")]
    SchemaValidation(Vec<jsonschema::error::ValidationErrorKind>),
}

impl From<jsonschema::ErrorIterator<'_>> for ClientHandlingError {
    fn from(e: ErrorIterator) -> Self {
        Self::SchemaValidation(e.map(|e| e.kind).collect())
    }
}

impl From<jsonschema::ValidationError<'_>> for ClientHandlingError {
    fn from(e: ValidationError) -> Self {
        Self::SchemaValidation(vec![e.kind])
    }
}

struct EventsClient<'a> {
    socket: TcpStream,
    address: SocketAddr,
    postgres: &'a Client,
    schema: JSONSchema,
}

impl Debug for EventsClient<'_> {
    fn fmt(&self, f: &mut Formatter<'_>) -> std::fmt::Result {
        write!(f, "EventsClient:{}", self.address)
    }
}

#[derive(Debug, Error)]
enum EventsClientConstructionError {
    #[error("Multiple schema validation errors")]
    SchemaValidation(Vec<jsonschema::error::ValidationErrorKind>),
    #[error("JSON Parsing Failed: {0}")]
    JsonParsingFailed(#[from] serde_json::Error),
    #[error("IO Error: {0}")]
    IoError(#[from] std::io::Error),
}

impl From<jsonschema::ErrorIterator<'_>> for EventsClientConstructionError {
    fn from(e: ErrorIterator) -> Self {
        Self::SchemaValidation(e.map(|e| e.kind).collect())
    }
}

impl From<jsonschema::ValidationError<'_>> for EventsClientConstructionError {
    fn from(e: ValidationError) -> Self {
        Self::SchemaValidation(vec![e.kind])
    }
}

#[derive(Error, Debug)]
enum MessageHandlingError {
    #[error("Database Error: {0}")]
    DatabaseError(#[from] tokio_postgres::Error),
    #[error("Multiple schema validation errors")]
    SchemaValidation(Vec<jsonschema::error::ValidationErrorKind>),
    #[error("JSON Parsing Failed: {0}")]
    JsonParsingFailed(#[from] serde_json::Error),
    #[error("Missing key: {0}")]
    MissingKey(String),
    #[error("Missing key: {0}")]
    InvalidKeyType(String),
    #[error("Invalid UUID: {0}")]
    InvalidUuid(#[from] uuid::Error),
    #[error("Invalid Date/Time: {0}")]
    InvalidDateTime(#[from] time::error::Parse),
}

impl From<jsonschema::ErrorIterator<'_>> for MessageHandlingError {
    fn from(e: ErrorIterator) -> Self {
        Self::SchemaValidation(e.map(|e| e.kind).collect())
    }
}

impl From<jsonschema::ValidationError<'_>> for MessageHandlingError {
    fn from(e: ValidationError) -> Self {
        Self::SchemaValidation(vec![e.kind])
    }
}

impl<'a> EventsClient<'a> {
    pub fn new(
        socket: TcpStream,
        address: SocketAddr,
        postgres: &'a Client,
    ) -> Result<Self, EventsClientConstructionError> {
        Ok(Self {
            socket,
            address,
            postgres,
            // todo inject this
            schema: JSONSchema::compile(&serde_json::from_str(&std::fs::read_to_string(
                "/etc/svc-events/schemas/events.schema.json",
            )?)?)?,
        })
    }

    #[tracing::instrument]
    async fn handle(mut self) -> Result<(), ClientHandlingError> {
        let (mut reader, mut writer) = self.socket.split();
        let reader = BufReader::new(&mut reader);
        let mut lines = reader.lines();
        while let Some(line) = lines.next_line().await? {
            let response = match Self::handle_message(line, self.postgres, &self.schema).await {
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
        schema: &JSONSchema,
    ) -> Result<(), MessageHandlingError> {
        const ID_FIELD: &str = "id";
        const CREATED_TIMESTAMP_FIELD: &str = "created_timestamp";
        const TYPE_FIELD: &str = "type";

        let parsed: Value = serde_json::from_str(&line)?;

        schema.validate(&parsed)?;

        postgres
            .execute(
                "INSERT INTO events(id, created_timestamp, type, data) VALUES($1,$2,$3,$4)",
                &[
                    &Uuid::parse_str(
                        parsed
                            .get(ID_FIELD)
                            .ok_or_else(|| MessageHandlingError::MissingKey(ID_FIELD.to_string()))?
                            .as_str()
                            .ok_or_else(|| {
                                MessageHandlingError::InvalidKeyType(ID_FIELD.to_string())
                            })?,
                    )?,
                    &OffsetDateTime::parse(
                        parsed
                            .get(CREATED_TIMESTAMP_FIELD)
                            .ok_or_else(|| {
                                MessageHandlingError::MissingKey(
                                    CREATED_TIMESTAMP_FIELD.to_string(),
                                )
                            })?
                            .as_str()
                            .ok_or_else(|| {
                                MessageHandlingError::InvalidKeyType(
                                    CREATED_TIMESTAMP_FIELD.to_string(),
                                )
                            })?,
                        &Rfc3339,
                    )?,
                    &parsed
                        .get(TYPE_FIELD)
                        .ok_or_else(|| MessageHandlingError::MissingKey(TYPE_FIELD.to_string()))?
                        .as_str()
                        .ok_or_else(|| {
                            MessageHandlingError::InvalidKeyType(TYPE_FIELD.to_string())
                        })?,
                    &parsed,
                ],
            )
            .await?;

        info!("Message handled: {}", line);

        Ok(())
    }
}
