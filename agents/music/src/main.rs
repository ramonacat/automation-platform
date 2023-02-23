use futures_util::{AsyncReadExt, StreamExt, TryStreamExt};
use music::{Client, Metadata, Rpc, StreamTrackRequest};
use rpc_support::{DefaultRawRpcClient, RawRpcClient};
use std::io::Cursor;
use tokio::net::TcpStream;

// todo this is inefficient, as it loads the whole file in memory
async fn read_track<TRawRpcClient: RawRpcClient + Send + Sync>(
    track_id: uuid::Uuid,
    client: &mut Client<TRawRpcClient>,
) -> Result<Vec<u8>, Box<dyn std::error::Error>> {
    let mut vec = vec![];

    client
        .stream_track(
            StreamTrackRequest { track_id },
            Metadata {
                correlation_id: uuid::Uuid::new_v4(),
            },
        )
        .await
        .unwrap()
        .map(|x| {
            x.map(|y| y.data)
                .map_err(|y| std::io::Error::new(std::io::ErrorKind::Other, y))
        })
        .into_async_read()
        .read_to_end(&mut vec)
        .await?;

    Ok(vec)
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let mut client = Client::new(DefaultRawRpcClient::new(
        TcpStream::connect("192.168.49.2:30655").await?,
    ));

    let vec = read_track(
        uuid::Uuid::parse_str("7fcc568b-9d29-426e-a4cd-d85e8fdef3d7").unwrap(),
        &mut client,
    )
    .await?;

    let (_stream, handle) = rodio::OutputStream::try_default().unwrap();
    let sink = rodio::Sink::try_new(&handle).unwrap();

    sink.append(rodio::decoder::Decoder::new(Cursor::new(vec)).unwrap());

    sink.sleep_until_end();

    Ok(())
}

#[cfg(test)]
mod tests {
    use rpc_support::{rpc_error::RpcError, ResponseStream};
    use serde::{de::DeserializeOwned, Serialize};
    use uuid::Uuid;

    use super::*;

    struct MockRawRpcClient {
        response_stream: Box<dyn Fn() -> ResponseStream<String> + Send + Sync>,
    }

    #[async_trait::async_trait]
    impl RawRpcClient for MockRawRpcClient {
        async fn send_rpc<TRequest, TMetadata, TResponse>(
            &mut self,
            _id: u64,
            _method_name: &str,
            _request: &TRequest,
            _metadata: &TMetadata,
        ) -> Result<TResponse, RpcError>
        where
            TRequest: Serialize + Sync + Send,
            TMetadata: Serialize + Sync + Send,
            TResponse: DeserializeOwned,
        {
            todo!();
        }

        async fn send_rpc_stream_request<TRequest, TMetadata, TResponse>(
            &mut self,
            _request_id: u64,
            _method_name: &str,
            _request: &TRequest,
            _metadata: &TMetadata,
        ) -> Result<ResponseStream<TResponse>, RpcError>
        where
            TRequest: Serialize + Sync + Send,
            TMetadata: Serialize + Sync + Send,
            TResponse: DeserializeOwned,
        {
            Ok(Box::pin((self.response_stream)().map(move |x| {
                Ok::<_, RpcError>(serde_json::from_str(&x?)?)
            })))
        }
    }

    #[tokio::test]
    async fn test_read_track() {
        let mut client = Client::new(MockRawRpcClient {
            response_stream: Box::new(|| {
                Box::pin(
                    async_stream::stream! {
                        yield Ok("{\"data\": [1,2,3,4,5]}".to_string());
                    }
                    .boxed(),
                )
            }),
        });

        let result = read_track(Uuid::new_v4(), &mut client).await.unwrap();

        assert_eq!(result, vec![1, 2, 3, 4, 5]);
    }
}
