use async_std::stream::StreamExt;
use music::structs::{Metadata, Rpc, TrackData, TrackPath};
use music::Server;
use rpc_support::rpc_error::RpcError;
use std::sync::Arc;
use tokio::sync::Mutex;
use tokio_util::io::ReaderStream;

struct RpcServer {}

#[async_trait::async_trait]
impl Rpc for RpcServer {
    async fn stream_track(
        &mut self,
        request: TrackPath,
        _metadata: Metadata,
    ) -> Result<
        Box<dyn futures_core::stream::Stream<Item = Result<TrackData, RpcError>> + Unpin + Send>,
        RpcError,
    > {
        let mut path = std::path::PathBuf::from("/mnt/the-nas/");
        path.push(request.path);

        let file = tokio::fs::File::open(path).await?;
        let reader = ReaderStream::new(file);

        Ok(Box::new(reader.map(|buf| {
            Ok(TrackData {
                // fixme this is very inefficient, we should just transport raw bytes, but the RPC system does not support that yet
                data: base64::encode(&buf?),
            })
        })))
    }
}

#[tokio::main]
#[tracing::instrument]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    // todo make the bind addr/port configurable
    let server = Server::new("0.0.0.0:7655", Arc::new(Mutex::new(RpcServer {}))).await?;
    server.run().await?;

    Ok(())
}
