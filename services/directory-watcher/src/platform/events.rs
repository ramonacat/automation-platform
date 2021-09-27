use jsonschema::{ErrorIterator, JSONSchema, ValidationError};
use lapin::options::BasicPublishOptions;
use lapin::BasicProperties;
use serde::Serialize;

#[async_trait]
pub trait EventSender {
    async fn send<'a, T: Event + Send + Sync + Serialize + 'a>(
        &self,
        event: T,
    ) -> Result<(), Error>;
}

pub struct RabbitMQ {
    schema: JSONSchema,
    rabbit: lapin::Connection,
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
    #[error("Problems communicating with rabbitmq")]
    Rabbit(#[from] lapin::Error),
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

impl RabbitMQ {
    pub fn new(rabbit: lapin::Connection) -> Result<Self, Error> {
        Ok(Self {
            schema: JSONSchema::compile(&serde_json::from_str(&std::fs::read_to_string(
                "/etc/svc-directory-watcher/schemas/events.schema.json",
            )?)?)?,
            rabbit,
        })
    }
}

#[async_trait]
impl EventSender for RabbitMQ {
    async fn send<'a, T: Event + Serialize + 'a>(&self, event: T) -> Result<(), Error> {
        let serialized = serde_json::to_value(event)?;
        self.schema.validate(&serialized)?;

        let channel = self.rabbit.create_channel().await?;
        channel
            .basic_publish(
                "events",
                "",
                BasicPublishOptions::default(), // fixme ensure correct options
                format!("{}", serialized).as_bytes().to_vec(), // fixme can this be simplified?
                BasicProperties::default(),     // fixme do we need anything here?
            )
            .await?;

        Ok(())
    }
}
