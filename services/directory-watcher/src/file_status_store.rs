use crate::mount::PathInside;
use chrono::{DateTime, Utc};
use std::sync::Arc;
use tokio::sync::Mutex;
use tokio_postgres::Client;

#[derive(Debug, Copy, Clone)]
pub enum FileStatusSyncResult {
    Created,
    Modified,
    NotModified,
}

#[derive(Error, Debug)]
pub enum Error {
    #[error("Problems communicating with the database")]
    Database(#[from] tokio_postgres::Error),
}

#[async_trait]
pub trait FileStatusStore {
    async fn delete(&mut self, path: &PathInside<'_>) -> Result<(), Error>;
    async fn rename(&mut self, from: &PathInside<'_>, to: &PathInside<'_>) -> Result<(), Error>;
    async fn sync(
        &mut self,
        path: &PathInside<'_>,
        modified_at: DateTime<Utc>,
    ) -> Result<FileStatusSyncResult, Error>;
}

pub struct Postgres {
    pg_client: Arc<Mutex<Client>>,
}
impl Postgres {
    pub fn new(pg_client: Arc<Mutex<Client>>) -> Self {
        Self { pg_client }
    }
}

#[async_trait]
impl FileStatusStore for Postgres {
    async fn delete(&mut self, path: &PathInside<'_>) -> Result<(), Error> {
        self.pg_client
            .lock()
            .await
            .execute(
                "DELETE FROM files WHERE mount_id=$1 AND path=$2",
                &[&path.mount_id(), &path.path().to_string_lossy()],
            )
            .await?;
        Ok(())
    }

    async fn rename(&mut self, from: &PathInside<'_>, to: &PathInside<'_>) -> Result<(), Error> {
        assert_eq!(
            from.mount_id(),
            to.mount_id(),
            "File moved between different mounts"
        );

        self.pg_client
            .lock()
            .await
            .execute(
                "UPDATE files SET path=$1 WHERE mount_id=$2 AND path=$3",
                &[
                    &to.path().to_string_lossy(),
                    &from.mount_id(),
                    &from.path().to_string_lossy(),
                ],
            )
            .await?;
        Ok(())
    }

    async fn sync(
        &mut self,
        path: &PathInside<'_>,
        modified_at: DateTime<Utc>,
    ) -> Result<FileStatusSyncResult, Error> {
        let mut postgres = self.pg_client.lock().await;
        let transaction = postgres.transaction().await?;

        let rows = transaction
            .query(
                "SELECT modified_date FROM files WHERE mount_id=$1 AND path=$2 FOR UPDATE",
                &[&path.mount_id(), &path.path().to_string_lossy()],
            )
            .await?;

        if let Some(row) = rows.get(0) {
            let current_modified_at = row.get::<_, DateTime<Utc>>(0);
            if current_modified_at != modified_at {
                transaction
                    .execute(
                        "UPDATE files SET modified_date=$1 WHERE mount_id=$2 AND path=$3",
                        &[
                            &modified_at,
                            &path.mount_id(),
                            &path.path().to_string_lossy(),
                        ],
                    )
                    .await?;

                transaction.commit().await?;
                return Ok(FileStatusSyncResult::Modified);
            }

            transaction.commit().await?;
            Ok(FileStatusSyncResult::NotModified)
        } else {
            transaction
                .execute("INSERT INTO files (id, mount_id, path, modified_date) VALUES(gen_random_uuid(), $1, $2, $3)", &[&path.mount_id(), &path.path().to_string_lossy(), &modified_at])
                .await?;

            transaction.commit().await?;
            Ok(FileStatusSyncResult::Created)
        }
    }
}
