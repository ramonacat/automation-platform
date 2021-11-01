use async_trait::async_trait;
use jsonschema::{ErrorIterator, ValidationError};
use serde::{Deserialize, Serialize};
use std::fmt::Debug;
use std::net::SocketAddr;
use thiserror::Error;
use tokio::io::{AsyncBufReadExt, AsyncWriteExt, BufReader, ErrorKind};
use tokio::net::{TcpSocket, TcpStream};
use tracing::info;

#[derive(Serialize, Deserialize, Debug)]
pub enum Status {
    Ok,
    Failed,
}

#[derive(Serialize, Deserialize, Debug)]
pub struct Response {
    pub status: Status,
}

#[async_trait]
pub trait EventSender {
    async fn send<'a, T: Event + Send + Sync + Serialize + Debug + 'a>(
        &mut self,
        event: T,
    ) -> Result<(), Error>;
}

pub struct Service {
    socket: TcpStream,
    address: SocketAddr,
}

pub trait Event: Send {}

#[derive(Error, Debug)]
pub enum Error {
    #[error("IO error")]
    Io(#[from] std::io::Error),
    #[error("Invalid JSON")]
    InvalidJson(#[from] serde_json::Error),
    #[error("Failed to validate schema")]
    SchemaValidation(jsonschema::error::ValidationErrorKind),
    #[error("Multiple schema validation errors")]
    MultipleSchemaValidationErrors(Vec<jsonschema::error::ValidationErrorKind>),
    #[error("Server failed to acknowledge the event")]
    ServerFailed,
}

impl<'a> From<jsonschema::ValidationError<'a>> for Error {
    fn from(e: ValidationError<'a>) -> Self {
        Self::SchemaValidation(e.kind)
    }
}

impl From<jsonschema::ErrorIterator<'_>> for Error {
    fn from(e: ErrorIterator) -> Self {
        Self::MultipleSchemaValidationErrors(e.map(|e| e.kind).collect())
    }
}

impl Service {
    pub async fn new(address: SocketAddr) -> Result<Self, Error> {
        Ok(Self {
            socket: TcpSocket::new_v4()?.connect(address).await?,
            address,
        })
    }

    async fn reconnect(&mut self) -> Result<(), Error> {
        self.socket = TcpSocket::new_v4()?.connect(self.address).await?;

        Ok(())
    }
}

#[async_trait]
impl EventSender for Service {
    async fn send<'a, T: Event + Serialize + Debug + Send + Sync + 'a>(
        &mut self,
        event: T,
    ) -> Result<(), Error> {
        info!("Sending an event: {:?}", event);
        let serialized = serde_json::to_value(&event)?;

        let mut reader = BufReader::new(&mut self.socket);

        match reader
            .write_all(format!("{}\n", serialized).as_bytes())
            .await
        {
            Ok(_) => {}
            Err(error) => {
                return match error.kind() {
                    ErrorKind::ConnectionReset => {
                        self.reconnect().await?;

                        self.send(event).await
                    }
                    _ => Err(error.into()),
                }
            }
        }
        info!("Event sent. Awaiting response.");
        let mut response = String::new();
        reader.read_line(&mut response).await?;

        let response: Response = serde_json::from_str(&response)?;

        match response.status {
            Status::Ok => Ok(()),
            Status::Failed => Err(Error::ServerFailed),
        }
    }
}
