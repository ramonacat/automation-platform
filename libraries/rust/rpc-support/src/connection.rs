use std::sync::{
    atomic::{AtomicU64, Ordering},
    Arc,
};

use dashmap::DashMap;
use futures::{Stream as FuturesStream, StreamExt};
use platform::async_infra::run_with_error_handling;
use serde::{de::DeserializeOwned, Serialize};
use thiserror::Error;
use tokio::{
    io::{AsyncBufRead, AsyncWrite},
    sync::mpsc::{channel, Sender},
    task::JoinHandle,
};
use tracing::warn;

use crate::{
    stream::Stream,
    wire_format::{Frame, Request, RequestId, ResponseEndStream, ResponseError, ResponseOk},
};

#[derive(Error, Debug)]
pub enum Error {
    #[error("response frame send: {0:?}")]
    ResponseFrameSend(#[from] tokio::sync::mpsc::error::SendError<ResponseFrame>),
    #[error("frame send: {0:?}")]
    FrameSend(#[from] tokio::sync::mpsc::error::SendError<Frame>),
    #[error("Serialization: {0:?}")]
    Serialization(#[from] serde_json::Error),

    #[error("Unexpected end of stream")]
    UnexpectedEndOfStream,
}

#[async_trait::async_trait]
pub trait RequestDispatcher {
    async fn dispatch(
        &self,
        request: Request,
    ) -> Box<dyn FuturesStream<Item = Frame> + Send + Sync>;
}

#[derive(Debug)]
pub enum ResponseFrame {
    Ok(ResponseOk),
    Error(ResponseError),
    EndStream(ResponseEndStream),
}

pub struct Connection<TRawReader: AsyncBufRead, TRawWriter: AsyncWrite> {
    stream: Stream<TRawReader, TRawWriter>,
    write_tx: Sender<Frame>,
    write_rx: tokio::sync::mpsc::Receiver<Frame>,
    read_tx: Sender<Frame>,
    read_rx: tokio::sync::mpsc::Receiver<Frame>,
    responses: Arc<DashMap<RequestId, Sender<ResponseFrame>>>,
    request_sender: Arc<RequestSender>,
}

impl<
        TRawReader: AsyncBufRead + Unpin + Send + 'static,
        TRawWriter: AsyncWrite + Unpin + Send + 'static,
    > Connection<TRawReader, TRawWriter>
{
    pub fn new(stream: Stream<TRawReader, TRawWriter>) -> Self {
        let (write_tx, write_rx) = tokio::sync::mpsc::channel(64);
        let (read_tx, read_rx) = tokio::sync::mpsc::channel(64);

        let responses: Arc<DashMap<RequestId, Sender<ResponseFrame>>> = Arc::new(DashMap::new());

        Self {
            write_tx: write_tx.clone(),
            write_rx,
            read_tx,
            read_rx,
            responses: responses.clone(),
            stream,
            request_sender: Arc::new(RequestSender {
                responses,
                write_tx,
                id: AtomicU64::new(0),
            }),
        }
    }

    pub fn request_sender(&self) -> Arc<RequestSender> {
        self.request_sender.clone()
    }

    /// # Panics
    /// TODO: Make it never panic, return an error instead
    pub async fn run(
        self,
        dispatcher: Arc<impl RequestDispatcher + Send + Sync + 'static>,
    ) -> JoinHandle<()> {
        let Self {
            stream,
            write_tx,
            write_rx,
            read_tx,
            mut read_rx,
            responses,
            request_sender: _,
        } = self;

        let handle_stream = tokio::spawn(run_with_error_handling(stream.run(read_tx, write_rx)));

        let responses_ = responses;
        let write_tx_ = write_tx;

        let handle = tokio::spawn(run_with_error_handling(async move {
            while let Some(frame) = read_rx.recv().await {
                let dispatcher = dispatcher.clone();
                let write_tx_ = write_tx_.clone();
                let responses_ = responses_.clone();

                tokio::spawn(run_with_error_handling(async move {
                    match frame {
                        Frame::Request(request) => {
                            let response = dispatcher.dispatch(request).await;
                            let mut response = Box::into_pin(response);

                            while let Some(frame) = response.next().await {
                                write_tx_.send(frame).await?;
                            }
                        }
                        Frame::ResponseOk(response) => {
                            if let Some(sender) = responses_.get(&response.request_id) {
                                (*sender).send(ResponseFrame::Ok(response)).await?;
                            } else {
                                warn!(
                                    "Found response, but no stream was registered. Request ID: {}",
                                    response.request_id
                                );
                            }
                        }
                        Frame::ResponseError(response) => {
                            if let Some(sender) = responses_.get(&response.request_id) {
                                (*sender).send(ResponseFrame::Error(response)).await?;
                            } else {
                                warn!(
                                    "Found response, but no stream was registered. Request ID: {}",
                                    response.request_id
                                );
                            }
                        }
                        Frame::ResponseEndStream(response) => {
                            if let Some(sender) = responses_.get(&response.request_id) {
                                (*sender).send(ResponseFrame::EndStream(response)).await?;
                            } else {
                                warn!(
                                    "Found response, but no stream was registered. Request ID: {}",
                                    response.request_id
                                );
                            }
                        }
                    }

                    Ok::<_, Error>(())
                }));
            }

            Ok::<_, Error>(())
        }));

        tokio::spawn(async move {
            // TODO error handling!
            handle_stream.await.unwrap();
            handle.await.unwrap();
        })
    }
}

pub struct RequestSender {
    responses: Arc<DashMap<RequestId, Sender<ResponseFrame>>>,
    write_tx: Sender<Frame>,

    id: AtomicU64,
}

impl RequestSender {
    /// # Errors
    /// Fails if the request fails to be sent
    pub async fn send_request<TResponseOk: DeserializeOwned, TResponseError: DeserializeOwned>(
        &self,
        method_name: String,
        request: impl Serialize,
    ) -> Result<Result<TResponseOk, TResponseError>, Error> {
        let id = RequestId::new(self.id.fetch_add(1, Ordering::AcqRel));

        let (tx, mut rx) = channel(1);

        self.responses.insert(id, tx);

        self.write_tx
            .send(Frame::Request(Request {
                id,
                method_name,
                data: serde_json::to_vec(&request)?,
            }))
            .await?;

        match rx.recv().await {
            Some(response) => match response {
                ResponseFrame::Ok(ok) => Ok(Ok(serde_json::from_slice(&ok.data)?)),
                ResponseFrame::Error(error) => Ok(Err(serde_json::from_slice(&error.data)?)),
                ResponseFrame::EndStream(_) => Err(Error::UnexpectedEndOfStream),
            },
            None => Err(Error::UnexpectedEndOfStream),
        }
    }

    /// # Panics
    /// TODO: Make this NEVER panic
    /// # Errors
    /// Can fail to send the request to the server
    pub async fn send_stream_request<
        TResponseOk: DeserializeOwned + Send + Sync + 'static,
        TResponseError: DeserializeOwned + Send + Sync + 'static,
    >(
        &self,
        method_name: String,
        request: impl Serialize,
    ) -> Result<
        Box<dyn FuturesStream<Item = Result<TResponseOk, TResponseError>> + Send + Sync + 'static>,
        Error,
    > {
        let id = RequestId::new(self.id.fetch_add(1, Ordering::AcqRel));

        let (tx, mut rx) = channel(1);

        self.responses.insert(id, tx);

        self.write_tx
            .send(Frame::Request(Request {
                id,
                method_name,
                data: serde_json::to_vec(&request)?,
            }))
            .await?;

        let responses = self.responses.clone();
        let stream = async_stream::stream! {
            while let Some(response) = rx.recv().await {
                match response {
                    // TODO: remove the unwraps
                    ResponseFrame::Ok(ok) => yield Ok(serde_json::from_slice(&ok.data).unwrap()),
                    ResponseFrame::Error(error) => yield Err(serde_json::from_slice(&error.data).unwrap()),
                    ResponseFrame::EndStream(_) => {
                        responses.remove(&id);
                        break;
                    },
                }
            }
        };

        Ok(Box::new(stream))
    }
}
