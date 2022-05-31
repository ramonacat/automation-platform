use futures_lite::stream::StreamExt;
use music::structs::{Metadata, Rpc, TrackPath};
use music::Client;
use tokio::io::AsyncWriteExt;

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let mut client = Client::new("192.168.157.137:30655").await.unwrap();
    let mut stream = client
        .stream_track(
            TrackPath {
                path: "Music/HOLYCHILD/The Shape of Brat Pop to Come/01. Barbie Nation.flac"
                    .to_string(),
            },
            Metadata {},
        )
        .await
        .unwrap();

    let mut output = tokio::fs::File::create("output.flac").await.unwrap();

    while let Some(data) = stream.next().await {
        let data = data?;

        output
            .write_all(&base64::decode(data.data).unwrap())
            .await
            .unwrap();
    }

    Ok(())
}
