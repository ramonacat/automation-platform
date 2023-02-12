use std::sync::Arc;

use thiserror::Error;
use tokio::sync::Mutex;
use uuid::Uuid;

#[derive(Debug, Error)]
pub enum Error {
    #[error("Database error: {0}")]
    DatabaseError(#[from] tokio_postgres::Error),

    #[error("Deserialization error: {0}")]
    DeserializationError(#[from] serde_json::Error),
}

pub struct UpsertAlbum<'a> {
    pub artist_id: Uuid,
    pub relation_type_id: Uuid,
    pub title: &'a str,
    pub disc_count: Option<i32>,
    pub track_count: Option<i32>,
    pub year: Option<i32>,
    pub discogs_id: Option<&'a str>,
}

pub struct UpsertTrack<'a> {
    pub title: &'a str,
    pub album_id: Uuid,
    pub artist_id: Uuid,
    pub relation_type_id: Uuid,
    pub disc_number: Option<i32>,
    pub track_number: Option<i32>,
    pub path: serde_json::Value,
}

pub struct Artist {
    pub id: Uuid,
    pub name: String,
    pub discogs_id: Option<String>,
}

pub struct Album {
    pub id: Uuid,
    pub title: String,
    pub disc_count: Option<i32>,
    pub track_count: Option<i32>,
    pub year: Option<i32>,
    pub discogs_id: Option<String>,
}

pub struct Track {
    pub id: Uuid,
    pub title: String,
    pub disc_number: Option<i32>,
    pub track_number: Option<i32>,
    pub path: events::FileOnMountPath,
}

pub struct Postgres {
    pg_client: Arc<Mutex<tokio_postgres::Client>>,
}

#[async_trait::async_trait]
pub(crate) trait MusicStorage {
    async fn all_artists(&self) -> Result<Vec<Artist>, Error>;
    async fn all_albums(&self, artist_id: Uuid) -> Result<Vec<Album>, Error>;
    async fn all_tracks(&self, album_id: Uuid) -> Result<Vec<Track>, Error>;
    async fn track_by_id(&self, id: Uuid) -> Result<Track, Error>;

    async fn upsert_relation_type(&self, name: &str) -> Result<Uuid, Error>;
    async fn upsert_artist(&self, artist: &str, discogs_id: Option<&str>) -> Result<Uuid, Error>;
    async fn upsert_album(&self, command: &UpsertAlbum<'_>) -> Result<Uuid, Error>;
    async fn upsert_track(&self, command: &UpsertTrack<'_>) -> Result<Uuid, Error>;
}

impl Postgres {
    pub fn new(pg_client: Arc<Mutex<tokio_postgres::Client>>) -> Self {
        Self { pg_client }
    }
}

// TODO should this all be INSERT ... ON CONFLICT UPDATE?
#[async_trait::async_trait]
impl MusicStorage for Postgres {
    async fn all_artists(&self) -> Result<Vec<Artist>, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let rows = transaction
            .query("SELECT id, name, discogs_id FROM artists", &[])
            .await?;

        let mut artists = Vec::new();
        for row in rows {
            let id: Uuid = row.get(0);
            let name: String = row.get(1);
            let discogs_id: Option<String> = row.get(2);

            artists.push(Artist {
                id,
                name,
                discogs_id,
            });
        }

        transaction.commit().await?;

        Ok(artists)
    }

    async fn all_albums(&self, artist_id: Uuid) -> Result<Vec<Album>, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let rows = transaction
            .query(
                "SELECT id, title, disc_count, track_count, year, discogs_id FROM albums WHERE id IN (SELECT album_id FROM albums_artists WHERE artist_id = $1)",
                &[&artist_id],
            )
            .await?;

        let mut albums = Vec::new();
        for row in rows {
            let id: Uuid = row.get(0);
            let title: String = row.get(1);
            let disc_count: Option<i32> = row.get(2);
            let track_count: Option<i32> = row.get(3);
            let year: Option<i32> = row.get(4);
            let discogs_id: Option<String> = row.get(5);

            albums.push(Album {
                id,
                title,
                disc_count,
                track_count,
                year,
                discogs_id,
            });
        }

        transaction.commit().await?;

        Ok(albums)
    }

    async fn all_tracks(&self, album_id: Uuid) -> Result<Vec<Track>, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let rows = transaction
            .query(
                "SELECT id, title, disc_number, track_number, path FROM tracks WHERE album_id = $1",
                &[&album_id],
            )
            .await?;

        let mut tracks = Vec::new();
        for row in rows {
            let id: Uuid = row.get(0);
            let title: String = row.get(1);
            let disc_number: Option<i32> = row.get(2);
            let track_number: Option<i32> = row.get(3);
            let path: events::FileOnMountPath = serde_json::from_value(row.get(4))?;

            tracks.push(Track {
                id,
                title,
                disc_number,
                track_number,
                path,
            });
        }

        transaction.commit().await?;

        Ok(tracks)
    }

    async fn track_by_id(&self, id: Uuid) -> Result<Track, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let row = transaction
            .query_one(
                "SELECT id, title, disc_number, track_number, path FROM tracks WHERE id = $1",
                &[&id],
            )
            .await?;

        let id: Uuid = row.get(0);
        let title: String = row.get(1);
        let disc_number: Option<i32> = row.get(2);
        let track_number: Option<i32> = row.get(3);
        let path: events::FileOnMountPath = serde_json::from_value(row.get(4))?;

        let track = Track {
            id,
            title,
            disc_number,
            track_number,
            path,
        };

        transaction.commit().await?;

        Ok(track)
    }

    async fn upsert_relation_type(&self, name: &str) -> Result<Uuid, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let row = transaction
            .query_opt("SELECT id FROM relation_types WHERE name = $1", &[&name])
            .await?;

        if let Some(row) = row {
            transaction.commit().await?;

            Ok(row.get(0))
        } else {
            let id = Uuid::new_v4();
            transaction
                .execute(
                    "INSERT INTO relation_types (id, name) VALUES ($1, $2)",
                    &[&id, &name],
                )
                .await?;

            transaction.commit().await?;

            Ok(id)
        }
    }

    async fn upsert_artist(&self, artist: &str, discogs_id: Option<&str>) -> Result<Uuid, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let row = transaction
            .query_opt("SELECT id FROM artists WHERE name = $1", &[&artist])
            .await?;

        if let Some(row) = row {
            transaction.commit().await?;
            let id = row.get(0);
            Ok(id)
        } else {
            let id = Uuid::new_v4();
            transaction
                .execute(
                    "INSERT INTO artists(id,name,discogs_id) VALUES($1, $2, $3)",
                    &[&id, &artist, &discogs_id],
                )
                .await?;
            transaction.commit().await?;

            Ok(id)
        }
    }

    // TODO how do we handle the case where title/artist matches, but other data does not?
    async fn upsert_album(&self, command: &UpsertAlbum<'_>) -> Result<Uuid, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let row = transaction
            .query_opt(
                "SELECT id FROM albums WHERE title = $1 AND EXISTS (SELECT 1 FROM albums_artists WHERE artist_id = $2 AND album_id = albums.id)",
                &[&command.title, &command.artist_id],
            )
            .await?;
        if let Some(row) = row {
            let id = row.get(0);

            transaction.commit().await?;

            Ok(id)
        } else {
            let id = Uuid::new_v4();
            transaction
                .execute(
                    "INSERT INTO albums(id,discogs_id,title,disc_count,track_count,year) VALUES($1, $2, $3, $4, $5, $6)",
                    &[&id, &command.discogs_id, &command.title, &command.disc_count, &command.track_count, &command.year],
                )
                .await?;

            transaction
                .execute(
                    "INSERT INTO albums_artists(album_id,artist_id,relation_type_id) VALUES($1, $2, $3)",
                    &[&id, &command.artist_id, &command.relation_type_id],
                )
                .await?;

            transaction.commit().await?;

            Ok(id)
        }
    }

    // TODO update all the data that does not match
    #[allow(clippy::too_many_arguments)]
    async fn upsert_track(&self, command: &UpsertTrack<'_>) -> Result<Uuid, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let row = transaction
            .query_opt("SELECT id FROM tracks WHERE path @> $1", &[&command.path])
            .await?;
        if let Some(row) = row {
            transaction.commit().await?;
            let id = row.get(0);
            Ok(id)
        } else {
            let id = Uuid::new_v4();
            transaction
                .execute(
                    "INSERT INTO tracks(id,title,album_id,disc_number,track_number,path) VALUES($1, $2, $3, $4, $5, $6)",
                    &[&id, &command.title, &command.album_id, &command.disc_number, &command.track_number, &command.path],
                )
                .await?;

            transaction
                .execute(
                    "INSERT INTO tracks_artists(track_id,artist_id,relation_type_id) VALUES($1, $2, $3)",
                    &[&id, &command.artist_id, &command.relation_type_id],
                )
                .await?;
            transaction.commit().await?;

            Ok(id)
        }
    }
}
