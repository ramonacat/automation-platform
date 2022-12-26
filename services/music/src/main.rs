mod event_storage;
mod music_storage;

use async_std::stream::StreamExt;
use event_storage::EventStorage;
use events::{EventKind, Rpc as EventsRpc};
use music::structs::{Metadata, Rpc, TrackData, TrackPath};
use music::Server;
use music_storage::{Error, MusicStorage};
use platform::async_infra;
use platform::postgres::connect;
use platform::secrets::SecretProvider;
use rpc_support::rpc_error::RpcError;
use std::collections::HashMap;
use std::pin::Pin;
use std::sync::Arc;
use tokio::sync::Mutex;
use tokio_util::io::ReaderStream;
use tracing::info;
use uuid::Uuid;

use crate::music_storage::{UpsertAlbum, UpsertTrack};

struct RpcServer {}

#[async_trait::async_trait]
impl Rpc for RpcServer {
    async fn stream_track(
        &mut self,
        request: TrackPath,
        _metadata: Metadata,
    ) -> Result<
        Pin<
            Box<
                dyn futures_core::stream::Stream<Item = Result<TrackData, RpcError>> + Unpin + Send,
            >,
        >,
        RpcError,
    > {
        let mut path = std::path::PathBuf::from("/mnt/the-nas/");
        path.push(request.path);

        let file = tokio::fs::File::open(path).await?;
        let reader = ReaderStream::new(file);

        Ok(Box::pin(reader.map(|buf| {
            Ok(TrackData {
                // TODO this is very inefficient, we should just transport raw bytes, but the RPC system does not support that yet
                data: base64::encode(&buf?),
            })
        })))
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

    tokio::spawn(async_infra::run_with_error_handling::<RpcError>(
        async move {
            let storage = MusicStorage::new(pg_client.clone());
            let mut event_storage = EventStorage::new(pg_client);

            let mut client = events::Client::new("svc-events:7654")
                .await
                .map_err(RpcError::from)?;

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
    let server = Server::new("0.0.0.0:7655", Arc::new(Mutex::new(RpcServer {}))).await?;
    server.run().await?;

    Ok(())
}
