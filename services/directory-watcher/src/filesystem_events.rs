use crate::events::{FileChanged, FileCreated, FileDeleted, FileMoved};
use crate::file_status_store::FileStatusStore;
use crate::mount::{Mount, PathInside};
use crate::HandleEventsError;
use chrono::{DateTime, Utc};
use notify::DebouncedEvent;
use platform::events::EventSender;
use std::path::PathBuf;
use std::sync::mpsc::Receiver;
use std::sync::Arc;
use tokio::sync::Mutex;

pub struct FilesystemEventHandler<'a, T: EventSender + Sync + Send> {
    event_sender: Arc<Mutex<T>>,
    file_status_store: Arc<Mutex<dyn FileStatusStore + Send>>,
    mounts: &'a [Mount],
}

impl<'a, T: EventSender + Sync + Send> FilesystemEventHandler<'a, T> {
    pub fn new(
        event_sender: Arc<Mutex<T>>,
        file_status_store: Arc<Mutex<dyn FileStatusStore + Send>>,
        mounts: &'a [Mount],
    ) -> Self {
        Self {
            event_sender,
            file_status_store,
            mounts,
        }
    }

    pub async fn handle_events(
        &self,
        receiver: Receiver<DebouncedEvent>,
    ) -> Result<(), HandleEventsError> {
        info!("Waiting for filesystem events");
        for item in receiver {
            self.handle_event(item).await?;
        }

        Ok(())
    }

    async fn handle_event(&self, item: DebouncedEvent) -> Result<(), HandleEventsError> {
        info!("Handling filesystem event: {:?}", item);
        match item {
            DebouncedEvent::Create(x) => {
                let mount_relative_path = PathInside::from_mount_list(self.mounts, &x)?;

                let modfiied_date = Self::modified_date(x);
                self.file_status_store
                    .lock()
                    .await
                    .sync(&mount_relative_path, modfiied_date)
                    .await?;
                self.event_sender
                    .lock()
                    .await
                    .send(FileCreated::new(&mount_relative_path))
                    .await?;
            }
            DebouncedEvent::Chmod(x) | DebouncedEvent::Write(x) => {
                let mount_relative_path = PathInside::from_mount_list(self.mounts, &x)?;
                let modfiied_date = Self::modified_date(x);

                self.file_status_store
                    .lock()
                    .await
                    .sync(&mount_relative_path, modfiied_date)
                    .await?;
                self.event_sender
                    .lock()
                    .await
                    .send(FileChanged::new(&mount_relative_path))
                    .await?;
            }
            DebouncedEvent::Remove(x) => {
                let mount_relative_path = PathInside::from_mount_list(self.mounts, &x)?;
                self.file_status_store
                    .lock()
                    .await
                    .delete(&mount_relative_path)
                    .await?;
                self.event_sender
                    .lock()
                    .await
                    .send(FileDeleted::new(&mount_relative_path))
                    .await?;
            }
            DebouncedEvent::Rename(x, y) => {
                let path_relative_from = PathInside::from_mount_list(self.mounts, &x)?;
                let path_relative_to = PathInside::from_mount_list(self.mounts, &y)?;

                self.file_status_store
                    .lock()
                    .await
                    .rename(&path_relative_from, &path_relative_to)
                    .await?;
                self.event_sender
                    .lock()
                    .await
                    .send(FileMoved::new(&path_relative_from, &path_relative_to))
                    .await?;
            }
            DebouncedEvent::Error(x, y) => error!(
                "Error: {} (at {})",
                x,
                y.map_or("".into(), |z| z.to_string_lossy().to_string())
            ),
            DebouncedEvent::Rescan => info!("Rescan!"),
            DebouncedEvent::NoticeWrite(_) | DebouncedEvent::NoticeRemove(_) => {}
        };

        Ok(())
    }

    fn modified_date(x: PathBuf) -> DateTime<Utc> {
        // todo do not panic here
        DateTime::from(std::fs::metadata(x).unwrap().modified().unwrap())
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::file_status_store::FileStatusSyncResult;
    use crate::platform::events::{Error, Event};
    use serde::Serialize;
    use serde_json::{to_value, Value};
    use tempdir::TempDir;

    struct MockEventSender {
        events: Vec<Value>,
    }

    #[async_trait]
    impl EventSender for MockEventSender {
        async fn send<'a, T: Event + Send + Sync + Serialize + 'a>(
            &mut self,
            event: T,
        ) -> Result<(), Error> {
            self.events.push(to_value(event).unwrap());

            Ok(())
        }
    }

    struct MockFileStatusStore {
        sync_result: FileStatusSyncResult,
    }

    #[async_trait]
    impl FileStatusStore for MockFileStatusStore {
        async fn delete(
            &mut self,
            _path: &PathInside<'_>,
        ) -> Result<(), crate::file_status_store::Error> {
            Ok(())
        }

        async fn rename(
            &mut self,
            _from: &PathInside<'_>,
            _to: &PathInside<'_>,
        ) -> Result<(), crate::file_status_store::Error> {
            Ok(())
        }

        async fn sync(
            &mut self,
            _path: &PathInside<'_>,
            _modified_at: DateTime<Utc>,
        ) -> Result<FileStatusSyncResult, crate::file_status_store::Error> {
            Ok(self.sync_result)
        }
    }

    async fn setup(
        temp: &TempDir,
        event: DebouncedEvent,
        sync_result: FileStatusSyncResult,
    ) -> Vec<Value> {
        let temp = temp.path();

        std::fs::create_dir(temp.join("b/")).unwrap();
        std::fs::write(temp.join("b/1"), "aaa").unwrap();

        let mounts = vec![Mount::new("mount_a".to_string(), PathBuf::from(temp))];
        let event_sender = Arc::new(Mutex::new(MockEventSender { events: vec![] }));
        let handler = FilesystemEventHandler::new(
            event_sender.clone(),
            Arc::new(Mutex::new(MockFileStatusStore { sync_result })),
            &mounts,
        );
        let (tx, rx) = std::sync::mpsc::channel();

        tx.send(event).unwrap();
        drop(tx);
        handler.handle_events(rx).await.unwrap();
        let events = event_sender.lock().await.events.clone();
        events
    }

    #[tokio::test]
    async fn can_handle_file_creation() {
        let temp = TempDir::new("tmp").unwrap();
        let events = setup(
            &temp,
            DebouncedEvent::Create(temp.path().join("b/1")),
            FileStatusSyncResult::Created,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            &Value::String("file.status.created".into()),
            events[0].get("type").unwrap()
        )
    }

    #[tokio::test]
    async fn can_handle_file_change() {
        let temp = TempDir::new("tmp").unwrap();
        let events = setup(
            &temp,
            DebouncedEvent::Write(temp.path().join("b/1")),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            &Value::String("file.status.changed".into()),
            events[0].get("type").unwrap()
        )
    }

    #[tokio::test]
    async fn can_handle_file_removal() {
        let temp = TempDir::new("tmp").unwrap();
        let events = setup(
            &temp,
            DebouncedEvent::Remove(temp.path().join("b/1")),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            &Value::String("file.status.deleted".into()),
            events[0].get("type").unwrap()
        )
    }

    #[tokio::test]
    async fn can_handle_file_rename() {
        let temp = TempDir::new("tmp").unwrap();
        let events = setup(
            &temp,
            DebouncedEvent::Rename(temp.path().join("b/0"), temp.path().join("b/1")),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            &Value::String("file.status.moved".into()),
            events[0].get("type").unwrap()
        )
    }
}
