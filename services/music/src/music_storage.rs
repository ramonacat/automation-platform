use std::sync::Arc;

use thiserror::Error;
use tokio::sync::Mutex;
use uuid::Uuid;

#[derive(Debug, Error)]
pub enum Error {
    #[error("Database error: {0}")]
    DatabaseError(#[from] tokio_postgres::Error),
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

pub struct MusicStorage {
    pg_client: Arc<Mutex<tokio_postgres::Client>>,
}

// TODO should this all be INSERT ... ON CONFLICT UPDATE?
impl MusicStorage {
    pub fn new(pg_client: Arc<Mutex<tokio_postgres::Client>>) -> Self {
        Self { pg_client }
    }

    pub async fn upsert_relation_type(&self, name: &str) -> Result<Uuid, Error> {
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

    pub async fn upsert_artist(
        &self,
        artist: &str,
        discogs_id: Option<&str>,
    ) -> Result<Uuid, Error> {
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
    pub async fn upsert_album(&self, command: &UpsertAlbum<'_>) -> Result<Uuid, Error> {
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
    pub async fn upsert_track(&self, command: &UpsertTrack<'_>) -> Result<Uuid, Error> {
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
