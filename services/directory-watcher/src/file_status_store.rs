use crate::mount_relative_path::MountRelativePath;
use chrono::{DateTime, Utc};
use tokio_postgres::Client;

pub struct FileStatusStore<'a> {
    pg_client: &'a mut Client,
}

#[derive(Debug)]
pub enum FileStatusSyncResult {
    Created,
    Modified,
    NotModified,
}

#[derive(Error, Debug)]
pub enum SyncError {
    #[error("Problems communicating with the database")]
    Database(#[from] tokio_postgres::Error),
}

impl<'a> FileStatusStore<'a> {
    pub fn new(pg_client: &'a mut Client) -> Self {
        Self { pg_client }
    }

    pub async fn delete(&mut self, path: &MountRelativePath<'_>) -> Result<(), SyncError> {
        self.pg_client
            .execute(
                "DELETE FROM files WHERE mount_id=$1 AND path=$2",
                &[&path.mount_id(), &path.path()],
            )
            .await?;
        Ok(())
    }

    pub async fn rename(
        &mut self,
        from: &MountRelativePath<'_>,
        to: &MountRelativePath<'_>,
    ) -> Result<(), SyncError> {
        assert_eq!(
            from.mount_id(),
            to.mount_id(),
            "File moved between different mounts"
        );

        self.pg_client
            .execute(
                "UPDATE files SET path=$1 WHERE mount_id=$2 AND path=$3",
                &[&to.path(), &from.mount_id(), &from.path()],
            )
            .await?;
        Ok(())
    }

    pub async fn sync(
        &mut self,
        path: &MountRelativePath<'_>,
        modified_at: DateTime<Utc>,
    ) -> Result<FileStatusSyncResult, SyncError> {
        let transaction = self.pg_client.transaction().await?;

        let rows = transaction
            .query(
                "SELECT modified_date FROM files WHERE mount_id=$1 AND path=$2 FOR UPDATE",
                &[&path.mount_id(), &path.path()],
            )
            .await?;

        if rows.is_empty() {
            transaction
                .execute("INSERT INTO files (id, mount_id, path, modified_date) VALUES(gen_random_uuid(), $1, $2, $3)", &[&path.mount_id(), &path.path(), &modified_at])
                .await?;

            transaction.commit().await?;
            return Ok(FileStatusSyncResult::Created);
        }

        let current_modified_at = rows.get(0).expect("No row?").get::<_, DateTime<Utc>>(0);
        if current_modified_at != modified_at {
            transaction
                .execute(
                    "UPDATE files SET modified_date=$1 WHERE mount_id=$2 AND path=$3",
                    &[&modified_at, &path.mount_id(), &path.path()],
                )
                .await?;

            transaction.commit().await?;
            return Ok(FileStatusSyncResult::Modified);
        }

        transaction.commit().await?;
        Ok(FileStatusSyncResult::NotModified)
    }
}
