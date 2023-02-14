use futures_util::{AsyncReadExt, StreamExt, TryStreamExt};
use music::client::Client;
use music::structs::{Metadata, Rpc, StreamTrackRequest};
use rpc_support::DefaultRawRpcClient;
use std::io::Cursor;
use tokio::net::TcpStream;

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let mut vec = vec![];
    let mut client = Client::new(DefaultRawRpcClient::new(
        TcpStream::connect("192.168.49.2:30655").await?,
    ))
    .unwrap();

    // todo this is inefficient, as it loads the whole file in memory
    client
        .stream_track(
            StreamTrackRequest {
                track_id: uuid::Uuid::parse_str("7fcc568b-9d29-426e-a4cd-d85e8fdef3d7").unwrap(),
            },
            Metadata {},
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

    let (_stream, handle) = rodio::OutputStream::try_default().unwrap();
    let sink = rodio::Sink::try_new(&handle).unwrap();

    sink.append(rodio::decoder::Decoder::new(Cursor::new(vec)).unwrap());

    sink.sleep_until_end();

    Ok(())
}
