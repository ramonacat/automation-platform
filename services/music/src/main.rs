mod event_storage;
mod music_storage;

use event_storage::EventStorage;
use events::{EventKind, RpcClient as EventsRpc};
use futures::stream::StreamExt;
use music::{
    Album, AllAlbums, AllAlbumsRequest, AllArtists, AllTracks, AllTracksRequest, Artist, Metadata,
    RpcServer as MusicRpc, Server, StreamTrackRequest, Track, TrackData,
};
use music_storage::{Error, MusicStorage, Postgres};
use platform::async_infra;
use platform::postgres::connect;
use platform::secrets::SecretProvider;
use rpc_support::rpc_error::RpcError;
use rpc_support::{Client as RpcClient, DefaultRawRpcClient};
use std::pin::Pin;
use std::sync::Arc;
use std::{collections::HashMap, sync::Weak};
use tokio::net::TcpStream;
use tokio::sync::Mutex;
use tokio_util::io::ReaderStream;
use tracing::info;
use uuid::Uuid;

use crate::music_storage::{UpsertAlbum, UpsertTrack};

struct RpcServer<TMusicStorage: MusicStorage> {
    music_storage: Arc<Mutex<TMusicStorage>>,
}

#[async_trait::async_trait]
impl<TMusicStorage: MusicStorage + Send + Sync> MusicRpc for RpcServer<TMusicStorage> {
    async fn stream_track(
        &mut self,
        request: StreamTrackRequest,
        _metadata: Metadata,
        _client: Weak<Mutex<dyn RpcClient>>,
    ) -> Result<
        Pin<Box<dyn futures::stream::Stream<Item = Result<TrackData, RpcError>> + Unpin + Send>>,
        RpcError,
    > {
        let track = self
            .music_storage
            .lock()
            .await
            .track_by_id(request.track_id)
            .await?;
        // TODO get the mount path from track.path as well!
        let mut path = std::path::PathBuf::from("/mnt/the-nas/");
        path.push(track.path.path);

        let file = tokio::fs::File::open(path).await?;
        let reader = ReaderStream::new(file);

        Ok(Box::pin(reader.map(|buf| {
            Ok(TrackData {
                data: buf?.to_vec(),
            })
        })))
    }

    async fn all_artists(
        &mut self,
        _request: (),
        _metadata: Metadata,
        _client: Weak<Mutex<dyn RpcClient>>,
    ) -> Result<AllArtists, RpcError> {
        let artists = self.music_storage.lock().await.all_artists().await?;

        Ok(AllArtists {
            artists: artists
                .into_iter()
                .map(|artist| Artist {
                    id: artist.id,
                    name: artist.name,
                })
                .collect(),
        })
    }

    async fn all_albums(
        &mut self,
        request: AllAlbumsRequest,
        _metadata: Metadata,
        _client: Weak<Mutex<dyn RpcClient>>,
    ) -> Result<AllAlbums, RpcError> {
        let albums = self
            .music_storage
            .lock()
            .await
            .all_albums(request.artist_id)
            .await?;

        Ok(AllAlbums {
            albums: albums
                .into_iter()
                .map(|album| Album {
                    id: album.id,
                    title: album.title,
                    artists: vec![], // TODO fill this
                })
                .collect(),
        })
    }

    async fn all_tracks(
        &mut self,
        request: AllTracksRequest,
        _metadata: Metadata,
        _client: Weak<Mutex<dyn RpcClient>>,
    ) -> Result<AllTracks, RpcError> {
        let tracks = self
            .music_storage
            .lock()
            .await
            .all_tracks(request.album_id)
            .await?;

        Ok(AllTracks {
            tracks: tracks
                .into_iter()
                .map(|track| Track {
                    id: track.id,
                    title: track.title,
                    artists: vec![], // TODO fill this
                    album_id: request.album_id,
                })
                .collect(),
        })
    }
}

impl From<Error> for RpcError {
    fn from(err: Error) -> Self {
        RpcError::Custom(format!("{err:?}"))
    }
}

// TODO this should be per-error, so we don't expose all the error messages
fn to_rpc_error<T>(e: T) -> RpcError
where
    T: std::error::Error,
{
    RpcError::Custom(format!("{e:?}"))
}

#[tokio::main]
#[tracing::instrument]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let secret_provider = SecretProvider::new("/etc/svc-events/secrets/");
    let configuration = platform::configuration::Configuration::new()?;
    let directories_from_env = configuration.get_string("$.mounts")?;
    let mounts = Arc::new(Mutex::new(platform::mounts::Provider::from_raw_string(
        &directories_from_env,
    )));

    let pg_client = Arc::new(Mutex::new(
        connect(&secret_provider, "ap-music", "music.ap-music.credentials").await?,
    ));
    let music_storage = Arc::new(Mutex::new(Postgres::new(pg_client.clone())));

    tokio::spawn(async_infra::run_with_error_handling::<RpcError>(
        async move {
            let storage = Postgres::new(pg_client.clone());
            let mut event_storage = EventStorage::new(pg_client);

            let mut client = events::Client::new(DefaultRawRpcClient::new(
                TcpStream::connect("svc-events:7654").await?,
            ));

            let mut stream = client
                .subscribe(
                    events::SubscribeRequest {
                        id: Uuid::new_v4(),
                        from: event_storage
                            .latest_processed_timestamp()
                            .await
                            .map_err(to_rpc_error)?,
                    },
                    events::Metadata {
                        source: "music".to_string(),
                        correlation_id: Uuid::new_v4(),
                    },
                )
                .await?;

            while let Some(x) = stream.next().await {
                let x = x?;

                if event_storage
                    .was_processed(&x.id)
                    .await
                    .map_err(to_rpc_error)?
                {
                    continue;
                }

                match x.data {
                    EventKind::FileCreated { path } => {
                        let physical_path = mounts
                            .lock()
                            .await
                            .mount_relative_to_filesystem_path_by_mount_id(
                                &path.mount_id,
                                &path.path,
                            )
                            .map_err(to_rpc_error)?;

                        // TODO of course this ain't great, make the directories configurable
                        if !path.path.starts_with("Music") {
                            event_storage
                                .store_event(&x.id, &x.created_time)
                                .await
                                .map_err(to_rpc_error)?;
                            continue;
                        }

                        if let Some(extension) = physical_path.extension() {
                            // TODO: we probably want to support more extensions, mp3 at least
                            if extension != "flac" {
                                event_storage
                                    .store_event(&x.id, &x.created_time)
                                    .await
                                    .map_err(to_rpc_error)?;
                                continue;
                            }
                        } else {
                            event_storage
                                .store_event(&x.id, &x.created_time)
                                .await
                                .map_err(to_rpc_error)?;
                            continue;
                        }

                        let flac = claxon::FlacReader::open(physical_path).map_err(to_rpc_error)?;
                        let tags: HashMap<_, _> = flac.tags().collect();

                        // TODO are these the only tag names, or do we need to care about alternative names?
                        // TODO we need to insert the tracks even if some data is missing
                        // TODO differentiate between track artists and album artists
                        if let Some(artist) = tags.get("ARTIST") {
                            let artist_id = storage.upsert_artist(artist, None).await?;
                            info!("Artist ID: {:?}", artist_id);
                            // TODO: Do not hardcode the relation type
                            let relation_type_id =
                                storage.upsert_relation_type("Main Artist").await?;

                            if let Some(album) = tags.get("ALBUM") {
                                let album_id = storage
                                    .upsert_album(&UpsertAlbum {
                                        artist_id,
                                        relation_type_id,
                                        title: album,
                                        disc_count: tags
                                            .get("TOTALDISCS")
                                            .map(|y| y.parse().map_err(to_rpc_error))
                                            .transpose()?,
                                        track_count: tags
                                            .get("TOTALTRACKS")
                                            .map(|y| y.parse().map_err(to_rpc_error))
                                            .transpose()?,
                                        year: tags
                                            .get("YEAR")
                                            .map(|y| y.parse().map_err(to_rpc_error))
                                            .transpose()?,
                                        discogs_id: None,
                                    })
                                    .await?;
                                info!("Album ID: {:?}", album_id);

                                if let Some(title) = tags.get("TITLE") {
                                    let track_id = storage
                                        .upsert_track(&UpsertTrack {
                                            title,
                                            album_id,
                                            artist_id,
                                            relation_type_id,
                                            disc_number: tags
                                                .get("DISCNUMBER")
                                                .map(|y| y.parse().map_err(to_rpc_error))
                                                .transpose()?,
                                            track_number: tags
                                                .get("TRACKNUMBER")
                                                .map(|y| y.parse().map_err(to_rpc_error))
                                                .transpose()?,
                                            path: serde_json::to_value(&path)
                                                .map_err(to_rpc_error)?,
                                        })
                                        .await?;
                                    info!("Track ID: {:?}", track_id);
                                } else {
                                    info!("No title tag, tags: {:?}, path: {:?}", tags, path);
                                }
                            } else {
                                info!("No album tag, tags: {:?}, path: {:?}", tags, path);
                            }
                        } else {
                            info!("No artist tag, tags: {:?}, path: {:?}", tags, path);
                        }
                    }

                    // TODO: Implement all that...
                    EventKind::FileChanged { .. }
                    | EventKind::FileDeleted { .. }
                    | EventKind::FileMoved { .. } => {}
                }

                event_storage
                    .store_event(&x.id, &x.created_time)
                    .await
                    .map_err(to_rpc_error)?;
            }

            // FIXME: Mark the events as handled and ensure we read from the right place next time

            Ok(())
        },
    ));

    // todo make the bind addr/port configurable
    let server = Server::new(
        "0.0.0.0:7655",
        Arc::new(Mutex::new(RpcServer {
            music_storage: music_storage.clone(),
        })),
    )
    .await?;
    server.run().await?;

    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::music_storage::MusicStorage;

    struct MockMusicStorage;

    #[async_trait::async_trait]
    impl MusicStorage for MockMusicStorage {
        async fn all_artists(&self) -> Result<Vec<music_storage::Artist>, Error> {
            unimplemented!();
        }

        async fn all_albums(&self, _artist_id: Uuid) -> Result<Vec<music_storage::Album>, Error> {
            Ok(vec![music_storage::Album {
                id: Uuid::new_v4(),
                title: "Test Album".to_string(),
                year: Some(2020),
                disc_count: Some(1),
                track_count: Some(2),
                discogs_id: None,
            }])
        }

        async fn all_tracks(&self, _albumid: Uuid) -> Result<Vec<music_storage::Track>, Error> {
            unimplemented!();
        }

        async fn track_by_id(&self, _id: Uuid) -> Result<music_storage::Track, Error> {
            unimplemented!();
        }

        async fn upsert_relation_type(&self, _name: &str) -> Result<Uuid, Error> {
            unimplemented!();
        }

        async fn upsert_artist(
            &self,
            _artist: &str,
            _discogs_id: Option<&str>,
        ) -> Result<Uuid, Error> {
            unimplemented!();
        }

        async fn upsert_album(&self, _command: &UpsertAlbum<'_>) -> Result<Uuid, Error> {
            unimplemented!();
        }

        async fn upsert_track(&self, _command: &UpsertTrack<'_>) -> Result<Uuid, Error> {
            unimplemented!();
        }
    }

    struct MockClient;

    #[async_trait::async_trait]
    impl rpc_support::Client for MockClient {
        async fn write_all(&mut self, _data: &[u8]) -> std::io::Result<()> {
            todo!();
        }

        async fn read_line(&mut self) -> std::io::Result<String> {
            todo!();
        }
    }

    #[tokio::test]
    async fn rpc_server_all_albums() {
        let mut server = RpcServer {
            music_storage: Arc::new(Mutex::new(MockMusicStorage)),
        };

        let request = AllAlbumsRequest {
            artist_id: "00000000-0000-0000-0000-000000000000".parse().unwrap(),
        };

        let response = server
            .all_albums(
                request,
                Metadata {
                    correlation_id: Uuid::new_v4(),
                },
                Weak::<Mutex<MockClient>>::new(),
            )
            .await
            .unwrap();

        assert_eq!(response.albums[0].title, "Test Album");
    }
}
