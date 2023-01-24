#[allow(unused)]
use async_std::stream::Stream;
use rpc_support::rpc_error::RpcError;
use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct Metadata {
    pub source: String,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct SubscribeRequest {
    pub id: ::uuid::Uuid,
    pub from: Option<std::time::SystemTime>,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct Event {
    #[serde(with = "rpc_support::system_time_serializer")]
    pub created_time: std::time::SystemTime,
    pub id: ::uuid::Uuid,
    pub data: EventKind,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct FileOnMountPath {
    pub mount_id: String,
    pub path: String,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub enum EventKind {
    FileMoved {
        from: FileOnMountPath,
        to: FileOnMountPath,
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
