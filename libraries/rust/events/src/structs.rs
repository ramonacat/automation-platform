#[allow(unused)]
use async_std::stream::Stream;
use rpc_support::rpc_error::RpcError;
use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {
    #[serde(with = "rpc_support::system_time_serializer")]
    pub created_time: std::time::SystemTime,
    pub source: String,
    pub id: ::uuid::Uuid,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileOnMountPath {
    pub mount_id: String,
    pub path: String,
}
#[derive(Serialize, Deserialize, Debug)]
pub enum Event {
    FileMoved {
        to: FileOnMountPath,
        from: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
    },
    FileDeleted {
        path: FileOnMountPath,
    },
    FileChanged {
        path: FileOnMountPath,
    },
}

#[async_trait::async_trait]
pub trait Rpc {
    async fn send_event(
        &mut self,
        request: Event,
        metadata: Metadata,
    ) -> Result<(), RpcError>;
}
