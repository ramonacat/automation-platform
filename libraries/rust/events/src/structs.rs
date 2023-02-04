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
pub struct FileOnMountPath {
    pub mount_id: String,
    pub path: String,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct Event {
    pub data: EventKind,
    pub id: ::uuid::Uuid,
    #[serde(with = "rpc_support::system_time_serializer")]
    pub created_time: std::time::SystemTime,
}
#[derive(Serialize, Deserialize, Debug, Clone)]
pub enum EventKind {
    FileChanged {
        path: FileOnMountPath,
    },
    FileDeleted {
        path: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
    },
    FileMoved {
        from: FileOnMountPath,
        to: FileOnMountPath,
    },
}

#[async_trait::async_trait]
pub trait Rpc {
    async fn send_event(
        &mut self,
        request: Event,
        metadata: Metadata,
    ) -> Result<(), RpcError>;
    async fn subscribe(
        &mut self,
        request: SubscribeRequest,
        metadata: Metadata,
    ) -> Result<
        std::pin::Pin<Box<dyn Stream<Item = Result<Event, RpcError>> + Unpin + Send>>,
        RpcError,
    >;
}
