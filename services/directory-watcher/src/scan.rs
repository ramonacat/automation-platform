use crate::events::{FileChanged, FileCreated};
use crate::file_status_store::{FileStatusStore, FileStatusSyncResult};
use crate::mount::{Mount, PathInside};
use async_walkdir::{DirEntry, WalkDir};
use chrono::DateTime;
use futures_lite::stream::StreamExt;
use platform::events::EventSender;
use std::fs::Metadata;
use std::sync::Arc;
use tokio::sync::Mutex;

#[derive(Error, Debug)]
pub enum Error {
    #[error("Notify failed")]
    Notify(#[from] notify::Error),
    #[error("IO error")]
    Io(#[from] std::io::Error),
    #[error("Failure publishing the event")]
    Event(#[from] platform::events::Error),
    #[error("Failed to get mount relative path")]
    MountRelativePath(#[from] crate::mount::Error),
    #[error("Failed to sync the state")]
    Sync(#[from] crate::file_status_store::Error),
}

pub struct Scanner<T: EventSender + Sync + Send> {
    event_sender: Arc<Mutex<T>>,
    file_status_store: Arc<Mutex<dyn FileStatusStore + Send>>,
}

impl<T: EventSender + Sync + Send> Scanner<T> {
    pub fn new(
        event_sender: Arc<Mutex<T>>,
        file_status_store: Arc<Mutex<dyn FileStatusStore + Send>>,
    ) -> Self {
        Self {
            event_sender,
            file_status_store,
        }
    }

    pub async fn scan(&mut self, mounts: &[Mount]) -> Result<(), Error> {
        // todo check for deleted files that are still in the DB
        // todo push this to its own thread?
        for dir in mounts {
            info!("Watching: {} ({})", dir.path().to_string_lossy(), dir.id());

            let mut walkdir = WalkDir::new(dir.path());

            loop {
                match walkdir.next().await {
                    Some(Ok(entry)) => {
                        let metadata = entry.metadata().await?;
                        self.sync_file(dir, entry, metadata).await?;
                    }
                    Some(Err(e)) => {
                        error!("Failed to read path {}", e);
                    }
                    None => break,
                }
            }
        }
        info!("Initial scan completed");
        Ok(())
    }

    async fn sync_file(
        &mut self,
        dir: &Mount,
        entry: DirEntry,
        metadata: Metadata,
    ) -> Result<(), Error> {
        let path = entry.path();

        let mount_relative_path = PathInside::from_absolute(dir, &path)?;
        let sync_status = self
            .file_status_store
            .lock()
            .await
            .sync(&mount_relative_path, DateTime::from(metadata.modified()?))
            .await?;

        info!("Found file: {} ({:?})", path.to_string_lossy(), sync_status);
        match sync_status {
            FileStatusSyncResult::Created => {
                self.event_sender
                    .lock()
                    .await
                    .send(FileCreated::new(&mount_relative_path))
                    .await?;
            }
            FileStatusSyncResult::Modified => {
                self.event_sender
                    .lock()
                    .await
                    .send(FileChanged::new(&mount_relative_path))
                    .await?;
            }
            FileStatusSyncResult::NotModified => {}
        }

        Ok(())
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use chrono::Utc;
    use platform::events::Event;
    use serde::Serialize;
    use serde_json::{to_value, Value};
    use std::path::PathBuf;
    use tempdir::TempDir;

    struct MockEventSender {
        events: Vec<Value>,
    }
    #[async_trait]
    impl EventSender for MockEventSender {
        async fn send<'a, T: Event + Serialize + 'a>(
            &mut self,
            event: T,
        ) -> Result<(), platform::events::Error> {
            self.events.push(to_value(event).unwrap());

            Ok(())
        }
    }

    struct MockFileStatusStore;
    #[async_trait]
    impl FileStatusStore for MockFileStatusStore {
        async fn delete(
            &mut self,
            _path: &PathInside<'_>,
        ) -> Result<(), crate::file_status_store::Error> {
            todo!()
        }

        async fn rename(
            &mut self,
            _from: &PathInside<'_>,
            _to: &PathInside<'_>,
        ) -> Result<(), crate::file_status_store::Error> {
            todo!()
        }

        async fn sync(
            &mut self,
            path: &PathInside<'_>,
            _modified_at: DateTime<Utc>,
        ) -> Result<FileStatusSyncResult, crate::file_status_store::Error> {
            if path.path().ends_with("a/b") {
                Ok(FileStatusSyncResult::Created)
            } else if path.path().ends_with("b/c") {
                Ok(FileStatusSyncResult::Modified)
            } else {
                Ok(FileStatusSyncResult::NotModified)
            }
        }
    }

    #[tokio::test]
    pub async fn will_mark_preexisting_file_as_not_changed() {
        let sender = Arc::new(Mutex::new(MockEventSender { events: vec![] }));
        let mut scanner = Scanner::new(sender.clone(), Arc::new(Mutex::new(MockFileStatusStore)));
        let tempdir = TempDir::new("tmp").unwrap();
        let temp = tempdir.path();

        std::fs::create_dir(temp.join("a")).unwrap();
        std::fs::create_dir(temp.join("b")).unwrap();
        std::fs::create_dir(temp.join("c")).unwrap();

        std::fs::write(temp.join("a/b"), "test").unwrap();
        std::fs::write(temp.join("b/c"), "test").unwrap();
        std::fs::write(temp.join("c/d"), "test").unwrap();

        scanner
            .scan(&[Mount::new("mount_a".into(), temp.to_path_buf())])
            .await
            .unwrap();

        let events = &sender.lock().await.events;
        assert_eq!(2, events.len());
        assert_eq!(
            &Value::String("mount_a".into()),
            events[0].get("mount_id").unwrap()
        );
        assert_eq!(
            &Value::String("file.status.created".into()),
            events[0].get("type").unwrap()
        );
        assert_eq!(
            PathBuf::from("a/b"),
            PathBuf::from(events[0].get("path").unwrap().as_str().unwrap())
        );

        assert_eq!(
            &Value::String("mount_a".into()),
            events[1].get("mount_id").unwrap()
        );
        assert_eq!(
            &Value::String("file.status.changed".into()),
            events[1].get("type").unwrap()
        );
        assert_eq!(
            PathBuf::from("b/c"),
            PathBuf::from(events[1].get("path").unwrap().as_str().unwrap())
        );
    }
}
