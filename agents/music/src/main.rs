use claxon::FlacReader;
use futures_util::{AsyncReadExt, StreamExt, TryStreamExt};
use music::structs::{Metadata, Rpc, TrackPath};
use music::Client;
use std::io::{Cursor, ErrorKind};

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let mut vec = vec![];
    let mut client = Client::new("172.19.207.105:30655").await.unwrap();
    // todo this is inefficient, as it loads the whole file in memory
    client
        .stream_track(
            TrackPath {
                path: "Music/HOLYCHILD/The Shape of Brat Pop to Come/01. Barbie Nation.flac"
                    .to_string(),
            },
            Metadata {},
        )
        .await
        .unwrap()
        .map(|x| base64::decode(x.unwrap().data))
        .map_err(|x| std::io::Error::new(ErrorKind::AlreadyExists, x))
        .into_async_read()
        .read_to_end(&mut vec)
        .await?;

    let fr = FlacReader::new(&*vec)?;
    for (name, value) in fr.tags() {
        println!("{name}: {value}");
    }
    println!("=====");

    println!("{:?}\n{:?}", fr.vendor(), fr.streaminfo());

    let (_stream, handle) = rodio::OutputStream::try_default().unwrap();
    let sink = rodio::Sink::try_new(&handle).unwrap();

    sink.append(rodio::decoder::Decoder::new(Cursor::new(vec)).unwrap());

    sink.sleep_until_end();

    Ok(())
}
