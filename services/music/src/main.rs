use async_std::stream::StreamExt;
use events::Rpc as EventsRpc;
use music::structs::{Metadata, Rpc, TrackData, TrackPath};
use music::Server;
use rpc_support::rpc_error::RpcError;
use std::pin::Pin;
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
        Pin<
            Box<
                dyn futures_core::stream::Stream<Item = Result<TrackData, RpcError>> + Unpin + Send,
            >,
        >,
        RpcError,
    > {
        let mut path = std::path::PathBuf::from("/mnt/the-nas/");
        path.push(request.path);

        let file = tokio::fs::File::open(path).await?;
        let reader = ReaderStream::new(file);

        Ok(Box::pin(reader.map(|buf| {
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

    tokio::spawn(platform::async_infra::run_with_error_handling::<RpcError>(
        async move {
            let mut client = events::Client::new("svc-events:7654")
                .await
                .map_err(RpcError::from)?;

            let mut stream = client
                .subscribe(
                    events::SubscribeRequest { from: None },
                    events::Metadata {
                        // TODO most of this meta makes sense only for send_event
                        created_time: std::time::SystemTime::now(),
                        id: uuid::Uuid::new_v4(),
                        source: "music".to_string(),
                    },
                )
                .await?;

            while let Some(x) = stream.next().await {
                println!("got event: {:?}", x);
            }

            Ok(())
            // client.
        },
    ));

    // todo make the bind addr/port configurable
    let server = Server::new("0.0.0.0:7655", Arc::new(Mutex::new(RpcServer {}))).await?;
    server.run().await?;

    Ok(())
}
