use platform::async_infra::run_with_error_handling;
use thiserror::Error;
use tokio::io::{AsyncBufRead, AsyncBufReadExt, AsyncWrite, AsyncWriteExt};
use tokio::sync::mpsc::{Receiver, Sender};

use crate::wire_format::Frame;

#[derive(Error, Debug)]
pub enum Error {
    #[error("IO: {0:?}")]
    Io(#[from] std::io::Error),
    #[error("Serialization: {0:?}")]
    Serialization(#[from] serde_json::Error),
    #[error("frame send: {0:?}")]
    FrameSend(#[from] tokio::sync::mpsc::error::SendError<Frame>),
    #[error("join: {0:?}")]
    Join(#[from] tokio::task::JoinError),
}

pub(crate) struct Writer<TWriter: AsyncWrite> {
    writer: TWriter,
}

impl<TWriter: AsyncWrite + Unpin> Writer<TWriter> {
    pub fn from_raw(writer: TWriter) -> Self {
        Self { writer }
    }

    pub async fn write_frame(&mut self, frame: Frame) -> Result<(), Error> {
        let mut buffer = String::new();

        // TODO Replace unwraps with error handling
        buffer.push_str(&serde_json::to_string(&frame).unwrap());
        buffer.push('\n');

        self.writer.write_all(buffer.as_bytes()).await?;

        Ok(())
    }
}

pub struct Reader<TReader: AsyncBufRead> {
    reader: TReader,
}

impl<TReader: AsyncBufRead + Unpin> Reader<TReader> {
    pub fn from_raw(reader: TReader) -> Self {
        Self { reader }
    }

    /// # Errors
    /// Can fail if the connection is dropped
    pub async fn read_frame(&mut self) -> Result<Frame, Error> {
        let mut line_buffer = String::new();

        self.reader.read_line(&mut line_buffer).await?;
        let frame = serde_json::from_str(&line_buffer)?;

        Ok(frame)
    }
}

pub struct Stream<TRawReader: AsyncBufRead, TRawWriter: AsyncWrite> {
    reader: Reader<TRawReader>,
    writer: Writer<TRawWriter>,
}

impl<
        TRawReader: AsyncBufRead + Unpin + Send + 'static,
        TRawWriter: AsyncWrite + Unpin + Send + 'static,
    > Stream<TRawReader, TRawWriter>
{
    pub fn from_reader_writer(raw_reader: TRawReader, raw_writer: TRawWriter) -> Self {
        Self {
            reader: Reader::from_raw(raw_reader),
            writer: Writer::from_raw(raw_writer),
        }
    }

    async fn reader_task(
        mut reader: Reader<TRawReader>,
        read_tx: Sender<Frame>,
    ) -> Result<(), Error> {
        while let Ok(frame) = reader.read_frame().await {
            read_tx.send(frame).await?;
        }

        Ok(())
    }

    async fn writer_task(
        mut writer: Writer<TRawWriter>,
        mut write_rx: Receiver<Frame>,
    ) -> Result<(), Error> {
        while let Some(frame) = write_rx.recv().await {
            writer.write_frame(frame).await?;
        }

        Ok(())
    }

    /// # Errors
    /// Can fail if the background tasks fail
    pub async fn run(self, read_tx: Sender<Frame>, write_rx: Receiver<Frame>) -> Result<(), Error> {
        let Self { reader, writer } = self;

        let reader_task = tokio::spawn(run_with_error_handling(Self::reader_task(reader, read_tx)));
        let writer_task =
            tokio::spawn(run_with_error_handling(Self::writer_task(writer, write_rx)));

        let join_result = tokio::join!(reader_task, writer_task);

        join_result.0?;
        join_result.1?;

        Ok(())
    }
}
