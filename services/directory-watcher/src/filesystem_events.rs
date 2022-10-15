use crate::file_status_store::FileStatusStore;
use crate::mount::{Mount, PathInside};
use crate::{create_event_metadata, HandleEventsError};
use events::{Event, FileOnMountPath};
use notify::event::{ModifyKind, RenameMode};
use notify::{Event as DebouncedEvent, EventKind}; // fixme rename to NotifyEvent?
use std::path::Path;
use std::sync::mpsc::Receiver;
use std::sync::Arc;
use std::time::SystemTime;
use time::OffsetDateTime;
use tokio::sync::Mutex;
use uuid::Uuid;

pub struct FilesystemEventHandler<'a, T: events::Rpc + Sync + Send> {
    event_sender: Arc<Mutex<T>>,
    file_status_store: Arc<Mutex<dyn FileStatusStore + Send>>,
    mounts: &'a [Mount],
}

impl From<PathInside<'_>> for FileOnMountPath {
    fn from(p: PathInside) -> Self {
        Self {
            path: p.path().to_string(),
            mount_id: p.mount_id().to_string(),
        }
    }
}

impl<'a, T: events::Rpc + Sync + Send> FilesystemEventHandler<'a, T> {
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
        receiver: Receiver<notify::Result<DebouncedEvent>>,
    ) -> Result<(), HandleEventsError> {
        info!("Waiting for filesystem events");
        for item in receiver {
            // TODO skip errors, but handle the rest
            self.handle_event(item?).await?;
        }

        Ok(())
    }

    async fn handle_event(&self, item: DebouncedEvent) -> Result<(), HandleEventsError> {
        info!("Handling filesystem event: {:?}", item);
        match item.kind {
            EventKind::Any | EventKind::Other => {
                if let Some(path) = item.paths.first() {
                    self.handle_file_modified(path).await?;
                }
            }
            EventKind::Create(_) => {
                if let Some(path) = item.paths.first() {
                    self.handle_file_created(path).await?;
                }
            }
            EventKind::Remove(_) => {
                if let Some(path) = item.paths.first() {
                    self.handle_file_deleted(path).await?;
                }
            }
            EventKind::Modify(kind) => {
                if kind == ModifyKind::Name(RenameMode::Both) {
                    let path_from = item.paths.get(0).ok_or(HandleEventsError::MissingPath)?;
                    let path_to = item.paths.get(1).ok_or(HandleEventsError::MissingPath)?;

                    self.handle_file_renamed(path_from, path_to).await?;
                } else if kind == ModifyKind::Name(RenameMode::From) {
                    if let Some(path) = item.paths.first() {
                        self.handle_file_deleted(path).await?;
                    }
                } else if let Some(path) = item.paths.first() {
                    self.handle_file_modified(path).await?;
                }
            }
            EventKind::Access(_) => {}
        }

        Ok(())
    }

    async fn handle_file_renamed(&self, x: &Path, y: &Path) -> Result<(), HandleEventsError> {
        let (_, is_dir) = Self::modified_date(y)?;

        if is_dir {
            return Ok(());
        }

        let path_relative_from = PathInside::from_mount_list(self.mounts, x)?;
        let path_relative_to = PathInside::from_mount_list(self.mounts, y)?;

        self.file_status_store
            .lock()
            .await
            .rename(&path_relative_from, &path_relative_to)
            .await?;

        self.event_sender
            .lock()
            .await
            .send_event(
                Event {
                    id: Uuid::new_v4(),
                    created_time: std::time::SystemTime::now(),
                    data: events::EventKind::FileMoved {
                        from: path_relative_from.into(),
                        to: path_relative_to.into(),
                    },
                },
                create_event_metadata(),
            )
            .await?;

        Ok(())
    }

    // TODO only send events in case we had the file in the database (i.e. an event about creation was sent before)
    async fn handle_file_deleted(&self, x: &Path) -> Result<(), HandleEventsError> {
        let mount_relative_path = PathInside::from_mount_list(self.mounts, x)?;

        self.file_status_store
            .lock()
            .await
            .delete(&mount_relative_path)
            .await?;

        self.event_sender
            .lock()
            .await
            .send_event(
                Event {
                    created_time: SystemTime::now(),
                    id: Uuid::new_v4(),
                    data: events::EventKind::FileDeleted {
                        path: mount_relative_path.into(),
                    },
                },
                create_event_metadata(),
            )
            .await?;

        Ok(())
    }

    async fn handle_file_created(&self, x: &Path) -> Result<(), HandleEventsError> {
        let mount_relative_path = PathInside::from_mount_list(self.mounts, x)?;

        let (modified_date, is_dir) = Self::modified_date(x)?;

        if is_dir {
            return Ok(());
        }

        self.file_status_store
            .lock()
            .await
            .sync(&mount_relative_path, modified_date)
            .await?;

        self.event_sender
            .lock()
            .await
            .send_event(
                Event {
                    created_time: SystemTime::now(),
                    id: Uuid::new_v4(),
                    data: events::EventKind::FileCreated {
                        path: mount_relative_path.into(),
                    },
                },
                create_event_metadata(),
            )
            .await?;

        Ok(())
    }

    async fn handle_file_modified(&self, x: &Path) -> Result<(), HandleEventsError> {
        let mount_relative_path = PathInside::from_mount_list(self.mounts, x)?;
        let (modified_date, is_dir) = Self::modified_date(x)?;

        if is_dir {
            return Ok(());
        }

        self.file_status_store
            .lock()
            .await
            .sync(&mount_relative_path, modified_date)
            .await?;

        self.event_sender
            .lock()
            .await
            .send_event(
                Event {
                    created_time: SystemTime::now(),
                    id: Uuid::new_v4(),
                    data: events::EventKind::FileChanged {
                        path: mount_relative_path.into(),
                    },
                },
                create_event_metadata(),
            )
            .await?;

        Ok(())
    }

    fn modified_date(x: &Path) -> Result<(OffsetDateTime, bool), HandleEventsError> {
        let metadata = std::fs::metadata(x)?;
        Ok((
            OffsetDateTime::from(metadata.modified()?),
            metadata.is_dir(),
        ))
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::file_status_store::FileStatusSyncResult;
    use events::{Metadata, SubscribeRequest};
    use futures_lite::Stream;
    use notify::event::{CreateKind, DataChange, RemoveKind};
    use rpc_support::rpc_error::RpcError;
    use serde_json::{json, to_value, Value};
    use std::path::PathBuf;
    use std::pin::Pin;
    use tempfile::TempDir;

    struct MockRpcClient {
        events: Vec<Value>,
    }

    #[async_trait]
    impl events::Rpc for MockRpcClient {
        async fn send_event(
            &mut self,
            request: Event,
            _metadata: Metadata,
        ) -> Result<(), RpcError> {
            self.events.push(to_value(request).unwrap());

            Ok(())
        }

        async fn subscribe(
            &mut self,
            _request: SubscribeRequest,
            _metadata: Metadata,
        ) -> Result<Pin<Box<dyn Stream<Item = Result<Event, RpcError>> + Unpin + Send>>, RpcError>
        {
            todo!()
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
            _modified_at: OffsetDateTime,
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
        let event_sender = Arc::new(Mutex::new(MockRpcClient { events: vec![] }));
        let handler = FilesystemEventHandler::new(
            event_sender.clone(),
            Arc::new(Mutex::new(MockFileStatusStore { sync_result })),
            &mounts,
        );
        let (tx, rx) = std::sync::mpsc::channel();

        tx.send(Ok(event)).unwrap();
        drop(tx);
        handler.handle_events(rx).await.unwrap();
        let events = event_sender.lock().await.events.clone();
        events
    }

    #[tokio::test]
    async fn can_handle_file_creation() {
        let temp = TempDir::new().unwrap();
        let events = setup(
            &temp,
            DebouncedEvent::new(EventKind::Create(CreateKind::File))
                .add_path(temp.path().join("b/1")),
            FileStatusSyncResult::Created,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            &json!({
                "FileCreated": {
                "path": {
                    "mount_id": "mount_a",
                    "path": "b/1",
                },
                    }
            }),
            events[0].get("data").unwrap()
        );
    }

    #[tokio::test]
    async fn can_handle_file_change() {
        let temp = TempDir::new().unwrap();
        let path = temp.path().join("b/1");
        let events = setup(
            &temp,
            DebouncedEvent::new(EventKind::Modify(ModifyKind::Data(DataChange::Any)))
                .add_path(path.clone()),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            &json!({
                "FileChanged": {
                "path": {
                    "mount_id": "mount_a",
                    "path": "b/1",
                },
                    }
            }),
            events[0].get("data").unwrap()
        );
    }

    #[tokio::test]
    async fn can_handle_file_removal() {
        let temp = TempDir::new().unwrap();
        let path = temp.path().join("b/1");
        let events = setup(
            &temp,
            DebouncedEvent::new(EventKind::Remove(RemoveKind::Any)).add_path(path.clone()),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            &json!({
                "FileDeleted": {
                    "path": {
                        "mount_id": "mount_a",
                        "path": "b/1",
                    },
                }
            }),
            events[0].get("data").unwrap()
        );
    }

    #[tokio::test]
    async fn can_handle_file_rename() {
        let temp = TempDir::new().unwrap();
        let events = setup(
            &temp,
            DebouncedEvent::new(EventKind::Modify(ModifyKind::Name(RenameMode::Both)))
                .add_path(temp.path().join("b/0"))
                .add_path(temp.path().join("b/1")),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());

        assert_eq!(
            &json!({
                "FileMoved": {
                "from": {
                    "mount_id": "mount_a",
                    "path": "b/0",
                },
                "to": {
                    "mount_id": "mount_a",
                    "path": "b/1",
                },
                    }
            }),
            events[0].get("data").unwrap()
        );
    }
}
