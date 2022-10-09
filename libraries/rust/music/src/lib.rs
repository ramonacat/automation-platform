use crate::structs::{Metadata, Rpc, TrackData, TrackPath};
use async_std::prelude::Stream;
use rpc_support::rpc_error::RpcError;
use rpc_support::{read_request, send_stream_response, RawRpcClient};
use std::pin::Pin;
use std::sync::atomic::AtomicU64;
use std::sync::Arc;
use thiserror::Error;
use tokio::io::BufReader;
use tokio::net::{TcpListener, TcpStream};
use tokio::sync::Mutex;
use tracing::info;

pub mod structs;

pub struct Client {
    id: AtomicU64,
    raw: RawRpcClient,
}

pub struct Server<T>
where
    T: Rpc + Send + Sync + 'static,
{
    tcp: Arc<Mutex<TcpListener>>,
    rpc: Arc<Mutex<T>>,
}

#[derive(Debug, Error)]
pub enum RunError {
    #[error("{0}")]
    IoError(#[from] tokio::io::Error),
}

#[derive(Debug, Error)]
pub enum ClientError {
    #[error("{0}")]
    IoError(#[from] tokio::io::Error),
    #[error("{0}")]
    JsonError(#[from] serde_json::Error),
    #[error("{0}")]
    RpcError(#[from] RpcError),
    #[error("{0}")]
    UnknownMethod(String),
}

impl Client {
    /// # Errors
    /// Will return an error when the TCP connection fails.
    pub async fn new(addr: &str) -> Result<Self, RpcError> {
        let tcp = TcpStream::connect(addr).await?;

        Ok(Client {
            raw: RawRpcClient::new(tcp),
            id: AtomicU64::new(0),
        })
    }
}

#[async_trait::async_trait]
impl Rpc for Client {
    async fn stream_track(
        &mut self,
        request: TrackPath,
        metadata: Metadata,
    ) -> Result<Pin<Box<dyn Stream<Item = Result<TrackData, RpcError>> + Unpin + Send>>, RpcError>
    {
        self.raw
            .send_rpc_stream_request(
                self.id.fetch_add(1, std::sync::atomic::Ordering::AcqRel),
                "stream_track",
                &request,
                &metadata,
            )
            .await
    }
}

impl<T> Server<T>
where
    T: Rpc + Send + Sync + 'static,
{
    /// # Errors
    /// Will return an error when establishing the TCP Listener fails
    pub async fn new(addr: &str, rpc: Arc<Mutex<T>>) -> Result<Self, RpcError> {
        let tcp = Arc::new(Mutex::new(TcpListener::bind(addr).await?));
        Ok(Server { tcp, rpc })
    }

    async fn handle_client(socket: TcpStream, rpc: Arc<Mutex<T>>) -> Result<(), ClientError> {
        let (read, write) = socket.into_split();
        let mut reader = BufReader::new(read);

        let write = Arc::new(Mutex::new(write));
        loop {
            let (payload_line, method_name, request_id, metadata) =
                read_request(&mut reader).await?;

            match method_name.as_str() {
                "stream_track" => {
                    let result = rpc
                        .lock()
                        .await
                        .stream_track(serde_json::from_str(&payload_line)?, metadata)
                        .await;

                    // todo run with some error handling
                    let write = write.clone();
                    tokio::spawn(async move {
                        // todo the writer must be moved to a separate task, otherwise it will block
                        send_stream_response(&mut *write.clone().lock().await, result, request_id)
                            .await
                            .unwrap();
                    });
                }
                _ => return Err(ClientError::UnknownMethod(method_name)),
            };
        }
    }

    /// # Errors
    /// Will return an error if the connection fails
    pub async fn run(self) -> Result<(), RunError> {
        loop {
            let (socket, address) = self.tcp.lock().await.accept().await?;
            info!("New client connected: {}", address);

            let rpc = self.rpc.clone();

            tokio::spawn(platform::async_infra::run_with_error_handling(
                Self::handle_client(socket, rpc),
            ));
        }
    }
}
