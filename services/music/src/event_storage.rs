use std::{sync::Arc, time::SystemTime};

use thiserror::Error;
use tokio::sync::Mutex;
use uuid::Uuid;

#[derive(Debug, Error)]
pub enum Error {
    #[error("Database error: {0}")]
    DatabaseError(#[from] tokio_postgres::Error),
}

pub struct EventStorage {
    pg_client: Arc<Mutex<tokio_postgres::Client>>,
}

impl EventStorage {
    pub fn new(pg_client: Arc<Mutex<tokio_postgres::Client>>) -> Self {
        Self { pg_client }
    }

    pub async fn store_event(&mut self, id: &Uuid, timestamp: &SystemTime) -> Result<(), Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        transaction
            .execute(
                "INSERT INTO handled_events(id,timestamp) VALUES($1, $2)",
                &[id, timestamp],
            )
            .await?;

        transaction.commit().await?;

        Ok(())
    }

    pub async fn was_processed(&mut self, id: &Uuid) -> Result<bool, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let event = transaction
            .query_opt("SELECT id FROM handled_events WHERE id = $1", &[id])
            .await?;

        transaction.commit().await?;

        Ok(event.is_some())
    }

    pub async fn latest_processed_timestamp(&mut self) -> Result<Option<SystemTime>, Error> {
        let mut client = self.pg_client.lock().await;
        let transaction = client.transaction().await?;

        let event = transaction
            .query_opt(
                "SELECT timestamp FROM handled_events ORDER BY timestamp DESC LIMIT 1",
                &[],
            )
            .await?;

        transaction.commit().await?;

        Ok(event.map(|row| row.get(0)))
    }
}
