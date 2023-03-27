use futures::StreamExt;
use music::{ClientConnection, RequesterRpc, ResponderReverseRpc, StreamTrackRequest};
use std::{io::Cursor, sync::Arc};
use tokio::net::TcpStream;

// todo this is inefficient, as it loads the whole file in memory
async fn read_track(
    track_id: uuid::Uuid,
    client: &Arc<impl RequesterRpc>,
) -> Result<Vec<u8>, Box<dyn std::error::Error>> {
    let mut vec = vec![];

    let stream = client
        .stream_track(StreamTrackRequest { track_id })
        .await
        .unwrap();

    let mut stream = Box::into_pin(stream);

    while let Some(item) = stream.next().await {
        vec.append(&mut item?.data);
    }

    Ok(vec)
}

struct ReverseRpc;

impl ResponderReverseRpc for ReverseRpc {}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let stream = TcpStream::connect("192.168.49.2:30655").await?;
    let client = ClientConnection::from_tcp_stream(stream);

    let requester = client.run(Arc::new(ReverseRpc)).await;

    let vec = read_track(
        uuid::Uuid::parse_str("7fcc568b-9d29-426e-a4cd-d85e8fdef3d7").unwrap(),
        &requester,
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
    use music::{AllAlbums, AllAlbumsRequest, AllArtists, AllTracks, AllTracksRequest, TrackData};
    use uuid::Uuid;

    use super::*;

    struct MockRequesterRpc;

    #[async_trait::async_trait]
    impl RequesterRpc for MockRequesterRpc {
        async fn stream_track(
            &self,
            _request: StreamTrackRequest,
        ) -> Result<
            Box<
                dyn futures::Stream<Item = Result<TrackData, music::Error>> + Send + Sync + 'static,
            >,
            rpc_support::connection::Error,
        > {
            let stream = Box::new(async_stream::stream! {
                yield Ok(TrackData { data: vec![1,2,3] });
                yield Ok(TrackData { data: vec![4,5] });
            });

            Ok(stream)
        }
        async fn all_artists(
            &self,
            _request: (),
        ) -> Result<Result<AllArtists, music::Error>, rpc_support::connection::Error> {
            todo!();
        }
        async fn all_albums(
            &self,
            _request: AllAlbumsRequest,
        ) -> Result<Result<AllAlbums, music::Error>, rpc_support::connection::Error> {
            todo!();
        }
        async fn all_tracks(
            &self,
            _request: AllTracksRequest,
        ) -> Result<Result<AllTracks, music::Error>, rpc_support::connection::Error> {
            todo!();
        }
    }

    #[tokio::test]
    async fn test_read_track() {
        let client = MockRequesterRpc;

        let result = read_track(Uuid::new_v4(), &Arc::new(client)).await.unwrap();

        assert_eq!(result, vec![1, 2, 3, 4, 5]);
    }
}
