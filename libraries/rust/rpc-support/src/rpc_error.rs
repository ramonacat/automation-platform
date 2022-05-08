use serde::{Deserialize, Serialize};
use thiserror::Error;

#[derive(Debug, Serialize, Deserialize, Clone, Error)]
pub enum RpcError {
    #[error("Serialization failed: {0}")]
    SerializationFailed(String),
    #[error("IO failed: {0}")]
    IoError(String),
    #[error("{0}")]
    Custom(String),
}

impl From<serde_json::Error> for RpcError {
    fn from(e: serde_json::Error) -> Self {
        RpcError::SerializationFailed(e.to_string())
    }
}

impl From<std::io::Error> for RpcError {
    fn from(e: std::io::Error) -> Self {
        RpcError::IoError(e.to_string())
    }
}
