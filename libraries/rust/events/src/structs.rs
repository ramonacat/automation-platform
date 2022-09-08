#[allow(unused)]
use async_std::stream::Stream;
use rpc_support::rpc_error::RpcError;
use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {
    pub source: String,
    #[serde(with = "rpc_support::system_time_serializer")]
    pub created_time: std::time::SystemTime,
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
        from: FileOnMountPath,
        to: FileOnMountPath,
    },
    FileChanged {
        path: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
    },
    FileDeleted {
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
