use crate::rpc_error::RpcError;
use dashmap::DashMap;
use platform::async_infra::run_with_error_handling;
use serde::de::DeserializeOwned;
use serde::{Deserialize, Serialize};
use std::sync::Arc;
use thiserror::Error;
use tokio::io::{AsyncBufRead, AsyncBufReadExt, AsyncWrite, AsyncWriteExt};
use tokio::net::tcp::{OwnedReadHalf, OwnedWriteHalf};
use tokio::sync::mpsc::Sender;
use tracing::{error, info};

pub mod rpc_error;
pub mod system_time_serializer;

#[derive(Serialize, Deserialize, Debug)]
struct RequestEnvelope {
    pub method_name: String,
    pub request_id: u64,
}

#[derive(Serialize, Deserialize, Debug)]
struct ResponseEnvelope {
    pub request_id: u64,
    pub error: Option<RpcError>,
}

type WaitingResponses = DashMap<u64, Sender<(ResponseEnvelope, Option<String>)>>;

pub struct RawRpcClient {
    writer: OwnedWriteHalf,
    waiting_responses: Arc<WaitingResponses>,
}

#[derive(Debug, Error)]
pub enum RpcClientTaskError {
    #[error("{0}")]
    Io(#[from] std::io::Error),
    #[error("{0}")]
    Mpsc(String),
    #[error("{0}")]
    Serde(#[from] serde_json::Error),
}

impl<T> From<tokio::sync::mpsc::error::SendError<T>> for RpcClientTaskError {
    fn from(e: tokio::sync::mpsc::error::SendError<T>) -> Self {
        RpcClientTaskError::Mpsc(format!("{}", e))
    }
}

async fn client_task(
    read: OwnedReadHalf,
    waiting_responses: Arc<WaitingResponses>,
) -> Result<(), RpcClientTaskError> {
    let mut reader = tokio::io::BufReader::new(read);

    loop {
        let mut response_envelope_line = String::new();
        reader.read_line(&mut response_envelope_line).await?;
        let response_envelope: ResponseEnvelope = serde_json::from_str(&response_envelope_line)?;

        // todo pass the error
        if let Some(ref _error) = response_envelope.error {
            if let Some((_, sender)) = waiting_responses.remove(&response_envelope.request_id) {
                sender.send((response_envelope, None)).await?;
                continue;
            }
            error!(
                "Found response, but no request. Request ID: {}",
                response_envelope.request_id
            );

            continue;
        }

        let mut response_line = String::new();
        reader.read_line(&mut response_line).await?;

        if let Some((_, sender)) = waiting_responses.remove(&response_envelope.request_id) {
            sender
                .send((response_envelope, Some(response_line)))
                .await
                .unwrap();
        } else {
            error!(
                "Found response, but no request. Request ID: {}",
                response_envelope.request_id
            );
        }
    }
}

impl RawRpcClient {
    pub fn new(stream: tokio::net::TcpStream) -> Self {
        let (read, write) = stream.into_split();
        let waiting_responses: Arc<WaitingResponses> = Arc::new(DashMap::new());

        tokio::task::spawn(run_with_error_handling(client_task(
            read,
            waiting_responses.clone(),
        )));

        RawRpcClient {
            writer: write,
            waiting_responses,
        }
    }

    /// # Errors
    /// Can fail if sending the request fails or if the call returns an error
    pub async fn send_rpc<TRequest, TMetadata, TResponse>(
        &mut self,
        id: u64,
        method_name: &str,
        request: &TRequest,
        metadata: &TMetadata,
    ) -> Result<TResponse, RpcError>
    where
        TMetadata: Serialize,
        TRequest: Serialize,
        TResponse: DeserializeOwned,
    {
        let (tx, mut rx) = tokio::sync::mpsc::channel(1);
        self.waiting_responses.insert(id, tx);
        let mut buffer = vec![];
        buffer.extend_from_slice(
            serde_json::to_string(&RequestEnvelope {
                method_name: method_name.to_string(),
                request_id: id,
            })?
            .as_bytes(),
        );
        buffer.push(b'\n');
        buffer.extend_from_slice(serde_json::to_string(&metadata)?.as_bytes());
        buffer.push(b'\n');
        buffer.extend_from_slice(serde_json::to_string(&request)?.as_bytes());
        buffer.push(b'\n');

        self.writer.write_all(&buffer).await?;

        info!("Waiting for response");
        let (response_envelope, response_line) = rx
            .recv()
            .await
            .ok_or_else(|| RpcError::Custom("No response from client task".into()))?;
        info!(
            "Got response: {:?} {}",
            response_envelope,
            response_line.as_ref().unwrap_or(&String::new())
        );

        if let Some(error) = response_envelope.error {
            return Err(error);
        }

        let response_line = response_line.ok_or_else(|| RpcError::Custom("No response".into()))?;
        let response: TResponse = serde_json::from_str(&response_line)?;

        Ok(response)
    }
}

/**
 * # Errors
 * Can fail if the request cannot be read from the stream
 */
pub async fn read_request<TMetadata>(
    reader: &mut (impl AsyncBufRead + Unpin),
) -> Result<(String, String, u64, TMetadata), RpcError>
where
    TMetadata: DeserializeOwned,
{
    let mut envelope_line = String::new();
    let mut metadata_line = String::new();
    let mut payload_line = String::new();

    let mut reader = Box::pin(reader);

    reader.read_line(&mut envelope_line).await?;
    reader.read_line(&mut metadata_line).await?;
    reader.read_line(&mut payload_line).await?;

    info!("Envelope: {}", envelope_line);
    info!("Metadata: {}", metadata_line);
    info!("Payload: {}", payload_line);

    let envelope: RequestEnvelope = serde_json::from_str(&envelope_line)?;
    let metadata: TMetadata = serde_json::from_str(&metadata_line)?;

    Ok((
        payload_line,
        envelope.method_name,
        envelope.request_id,
        metadata,
    ))
}

/**
 * # Errors
 * Can fail if the response cannot be written to the stream
 */
pub async fn send_response<TResponse>(
    writer: &mut (impl AsyncWrite + Unpin),
    response: Result<TResponse, RpcError>,
    request_id: u64,
) -> Result<(), RpcError>
where
    TResponse: Serialize,
{
    let mut buffer = vec![];

    buffer.extend_from_slice(
        serde_json::to_string(&ResponseEnvelope {
            request_id,
            error: response.as_ref().err().map(|e| (*e).clone()),
        })?
        .as_bytes(),
    );
    buffer.push(b'\n');

    if let Ok(response) = response {
        buffer.extend_from_slice(serde_json::to_string(&response)?.as_bytes());
        buffer.push(b'\n');
    }

    writer.write_all(&buffer).await?;
    Ok(())
}
