#[allow(unused)]
use async_std::stream::Stream;
use rpc_support::rpc_error::RpcError;
use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct Metadata {
    pub source: String,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct FileOnMountPath {
    pub path: String,
    pub mount_id: String,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct Event {
    #[serde(with = "rpc_support::system_time_serializer")]
    pub created_time: std::time::SystemTime,
    pub data: EventKind,
    pub id: ::uuid::Uuid,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct SubscribeRequest {
    pub from: Option<std::time::SystemTime>,
    pub id: ::uuid::Uuid,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub enum EventKind {
    FileChanged {
        path: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
    },
    FileMoved {
        to: FileOnMountPath,
        from: FileOnMountPath,
    },
    FileDeleted {
        path: FileOnMountPath,
    },
}

#[async_trait::async_trait]
pub trait Rpc {
    async fn subscribe(
        &mut self,
        request: SubscribeRequest,
        metadata: Metadata,
    ) -> Result<
        std::pin::Pin<Box<dyn Stream<Item = Result<Event, RpcError>> + Unpin + Send>>,
        RpcError,
    >;
    async fn send_event(
        &mut self,
        request: Event,
        metadata: Metadata,
    ) -> Result<(), RpcError>;
}
