use crate::file_status_store::FileStatusStore;
use crate::mount::{Mount, PathInside};
use crate::{create_event_metadata, HandleEventsError};
use events::FileOnMountPath;
use notify::DebouncedEvent;
use std::path::PathBuf;
use std::sync::mpsc::Receiver;
use std::sync::Arc;
use time::OffsetDateTime;
use tokio::sync::Mutex;

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
                    .send_file_created(
                        events::FileCreated {
                            path: mount_relative_path.into(),
                        },
                        create_event_metadata(),
                    )
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
                    .send_file_changed(
                        events::FileChanged {
                            path: mount_relative_path.into(),
                        },
                        create_event_metadata(),
                    )
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
                    .send_file_deleted(
                        events::FileDeleted {
                            path: mount_relative_path.into(),
                        },
                        create_event_metadata(),
                    )
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
                    .send_file_moved(
                        events::FileMoved {
                            from: path_relative_from.into(),
                            to: path_relative_to.into(),
                        },
                        create_event_metadata(),
                    )
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

    fn modified_date(x: PathBuf) -> OffsetDateTime {
        // todo do not panic here
        OffsetDateTime::from(std::fs::metadata(x).unwrap().modified().unwrap())
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::file_status_store::FileStatusSyncResult;
    use events::{FileChanged, FileCreated, FileDeleted, FileMoved, Metadata};
    use rpc_support::rpc_error::RpcError;
    use serde_json::{json, to_value, Value};
    use tempfile::TempDir;

    struct MockRpcClient {
        events: Vec<Value>,
    }

    #[async_trait]
    impl events::Rpc for MockRpcClient {
        async fn send_file_created(
            &mut self,
            request: FileCreated,
            _metadata: Metadata,
        ) -> Result<(), RpcError> {
            self.events.push(to_value(request).unwrap());

            Ok(())
        }

        async fn send_file_deleted(
            &mut self,
            request: FileDeleted,
            _metadata: Metadata,
        ) -> Result<(), RpcError> {
            self.events.push(to_value(request).unwrap());

            Ok(())
        }

        async fn send_file_moved(
            &mut self,
            request: FileMoved,
            _metadata: Metadata,
        ) -> Result<(), RpcError> {
            self.events.push(to_value(request).unwrap());

            Ok(())
        }

        async fn send_file_changed(
            &mut self,
            request: FileChanged,
            _metadata: Metadata,
        ) -> Result<(), RpcError> {
            self.events.push(to_value(request).unwrap());

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

        tx.send(event).unwrap();
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
            DebouncedEvent::Create(temp.path().join("b/1")),
            FileStatusSyncResult::Created,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            json!({
                "path": {
                    "mount_id": "mount_a",
                    "path": "b/1",
                },
            }),
            events[0]
        );
    }

    #[tokio::test]
    async fn can_handle_file_change() {
        let temp = TempDir::new().unwrap();
        let path = temp.path().join("b/1");
        let events = setup(
            &temp,
            DebouncedEvent::Write(path.clone()),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            json!({
                "path": {
                    "mount_id": "mount_a",
                    "path": "b/1",
                },
            }),
            events[0]
        );
    }

    #[tokio::test]
    async fn can_handle_file_removal() {
        let temp = TempDir::new().unwrap();
        let path = temp.path().join("b/1");
        let events = setup(
            &temp,
            DebouncedEvent::Remove(path.clone()),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());
        assert_eq!(
            json!({
                "path": {
                    "mount_id": "mount_a",
                    "path": "b/1",
                },
            }),
            events[0]
        );
    }

    #[tokio::test]
    async fn can_handle_file_rename() {
        let temp = TempDir::new().unwrap();
        let events = setup(
            &temp,
            DebouncedEvent::Rename(temp.path().join("b/0"), temp.path().join("b/1")),
            FileStatusSyncResult::Modified,
        )
        .await;
        assert_eq!(1, events.len());

        assert_eq!(
            json!({
                "from": {
                    "mount_id": "mount_a",
                    "path": "b/0",
                },
                "to": {
                    "mount_id": "mount_a",
                    "path": "b/1",
                },
            }),
            events[0]
        );
    }
}
