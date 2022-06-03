#[allow(unused)]
use async_std::stream::Stream;
use rpc_support::rpc_error::RpcError;
use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {}
#[derive(Serialize, Deserialize, Debug)]
pub struct TrackPath {
    pub path: String,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct TrackData {
    pub data: String,
}

#[async_trait::async_trait]
pub trait Rpc {
    async fn stream_track(
        &mut self,
        request: TrackPath,
        metadata: Metadata,
    ) -> Result<Box<dyn Stream<Item = Result<TrackData, RpcError>> + Unpin + Send>, RpcError>;
}
