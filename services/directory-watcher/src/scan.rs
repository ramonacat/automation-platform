use crate::create_event_metadata;
use crate::file_status_store::{FileStatusStore, FileStatusSyncResult};
use async_walkdir::{DirEntry, WalkDir};
use events::{Event, EventKind, FileOnMountPath};
use futures_lite::stream::StreamExt;
use platform::mounts::Mount;
use std::fs::Metadata as FsMetadata;
use std::sync::Arc;
use time::OffsetDateTime;
use tokio::sync::Mutex;
use uuid::Uuid;

#[derive(Error, Debug)]
pub enum Error {
    #[error("Notify failed")]
    Notify(#[from] notify::Error),
    #[error("IO error")]
    Io(#[from] std::io::Error),
    #[error("Failed to get mount relative path")]
    MountRelativePath(#[from] platform::mounts::MountError),
    #[error("Failed to sync the state")]
    Sync(#[from] crate::file_status_store::Error),
    #[error("RPC call failed")]
    Rpc(#[from] rpc_support::rpc_error::RpcError),
}

pub struct Scanner<T: events::Rpc + Sync + Send> {
    event_sender: Arc<Mutex<T>>,
    file_status_store: Arc<Mutex<dyn FileStatusStore + Send>>,
    mounts: Arc<Mutex<platform::mounts::Provider>>,
}

impl<T: events::Rpc + Sync + Send> Scanner<T> {
    pub fn new(
        event_sender: Arc<Mutex<T>>,
        file_status_store: Arc<Mutex<dyn FileStatusStore + Send>>,
        mounts: Arc<Mutex<platform::mounts::Provider>>,
    ) -> Self {
        Self {
            event_sender,
            file_status_store,
            mounts,
        }
    }

    pub async fn scan(&mut self) -> Result<(), Error> {
        // todo check for deleted files that are still in the DB
        // todo push this to its own thread/task?
        let mounts = self.mounts.lock().await.mounts();
        for dir in mounts {
            info!("Watching: {} ({})", dir.path().to_string_lossy(), dir.id());

            let mut walkdir = WalkDir::new(dir.path());

            loop {
                match walkdir.next().await {
                    Some(Ok(entry)) => {
                        let metadata = entry.metadata().await?;

                        if metadata.is_dir() {
                            continue;
                        }

                        self.sync_file(&dir, entry, metadata).await?;
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
        metadata: FsMetadata,
    ) -> Result<(), Error> {
        let path = entry.path();

        let mount_relative_path = self
            .mounts
            .lock()
            .await
            .path_inside_from_filesystem_path_with_mount(&path, dir)?;
        let sync_status = self
            .file_status_store
            .lock()
            .await
            .sync(
                &mount_relative_path,
                OffsetDateTime::from(metadata.modified()?),
            )
            .await?;

        info!("Found file: {} ({:?})", path.to_string_lossy(), sync_status);
        match sync_status {
            FileStatusSyncResult::Created => {
                self.event_sender
                    .lock()
                    .await
                    .send_event(
                        Event {
                            id: Uuid::new_v4(),
                            created_time: std::time::SystemTime::now(),
                            data: EventKind::FileCreated {
                                path: FileOnMountPath {
                                    path: mount_relative_path.path().to_string(),
                                    mount_id: mount_relative_path.mount_id().to_string(),
                                },
                            },
                        },
                        create_event_metadata(),
                    )
                    .await?;
            }
            FileStatusSyncResult::Modified => {
                self.event_sender
                    .lock()
                    .await
                    .send_event(
                        Event {
                            id: Uuid::new_v4(),
                            created_time: std::time::SystemTime::now(),
                            data: EventKind::FileChanged {
                                path: FileOnMountPath {
                                    path: mount_relative_path.path().to_string(),
                                    mount_id: mount_relative_path.mount_id().to_string(),
                                },
                            },
                        },
                        create_event_metadata(),
                    )
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
    use events::{Metadata, SubscribeRequest};
    use futures_lite::Stream;
    use platform::mounts::{PathInside, Provider};
    use rpc_support::rpc_error::RpcError;
    use serde_json::{to_value, Value};
    use std::path::PathBuf;
    use std::pin::Pin;

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

    struct MockFileStatusStore;
    #[async_trait]
    impl FileStatusStore for MockFileStatusStore {
        async fn delete(
            &mut self,
            _path: &PathInside,
        ) -> Result<(), crate::file_status_store::Error> {
            todo!()
        }

        async fn rename(
            &mut self,
            _from: &PathInside,
            _to: &PathInside,
        ) -> Result<(), crate::file_status_store::Error> {
            todo!()
        }

        async fn sync(
            &mut self,
            path: &PathInside,
            _modified_at: OffsetDateTime,
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
        let sender = Arc::new(Mutex::new(MockRpcClient { events: vec![] }));
        let tempdir = tempfile::TempDir::new().unwrap();
        let temp = tempdir.path();
        let mut scanner = Scanner::new(
            sender.clone(),
            Arc::new(Mutex::new(MockFileStatusStore)),
            Arc::new(Mutex::new(Provider::new(vec![Mount::new(
                "mount_a".into(),
                temp.to_path_buf(),
            )]))),
        );

        std::fs::create_dir(temp.join("a")).unwrap();
        std::fs::create_dir(temp.join("b")).unwrap();
        std::fs::create_dir(temp.join("c")).unwrap();

        std::fs::write(temp.join("a/b"), "test").unwrap();
        std::fs::write(temp.join("b/c"), "test").unwrap();
        std::fs::write(temp.join("c/d"), "test").unwrap();

        scanner.scan().await.unwrap();

        let mut events = sender.lock().await.events.clone();
        events.sort_by(|a, b| {
            a.as_object()
                .unwrap()
                .get("data")
                .unwrap()
                .as_object()
                .unwrap()
                .values()
                .next()
                .unwrap()
                .get("path")
                .unwrap()
                .get("path")
                .unwrap()
                .as_str()
                .unwrap()
                .cmp(
                    &b.as_object()
                        .unwrap()
                        .get("data")
                        .unwrap()
                        .as_object()
                        .unwrap()
                        .values()
                        .next()
                        .unwrap()
                        .get("path")
                        .unwrap()
                        .get("path")
                        .unwrap()
                        .as_str()
                        .unwrap(),
                )
        });

        assert_eq!(2, events.len());

        let index = events
            .iter()
            .position(|e| {
                PathBuf::from(
                    e.get("data")
                        .unwrap()
                        .get("FileCreated")
                        .expect(format!("Not a FileCreated event {:?}", e).as_str())
                        .get("path")
                        .unwrap()
                        .get("path")
                        .unwrap()
                        .as_str()
                        .unwrap(),
                ) == PathBuf::from("a/b")
            })
            .unwrap();

        assert_eq!(
            &Value::String("mount_a".into()),
            events[index]
                .get("data")
                .unwrap()
                .get("FileCreated")
                .unwrap()
                .get("path")
                .unwrap()
                .get("mount_id")
                .unwrap()
        );
        assert_eq!(
            PathBuf::from("a/b"),
            PathBuf::from(
                events[index]
                    .get("data")
                    .unwrap()
                    .get("FileCreated")
                    .unwrap()
                    .get("path")
                    .unwrap()
                    .get("path")
                    .unwrap()
                    .as_str()
                    .unwrap()
            )
        );

        let index_b = events
            .iter()
            .position(|e| {
                PathBuf::from(
                    (if let Some(x) = e.get("data").unwrap().get("FileCreated") {
                        Some(x)
                    } else {
                        e.get("data").unwrap().get("FileChanged")
                    })
                    .unwrap()
                    .get("path")
                    .unwrap()
                    .get("path")
                    .unwrap()
                    .as_str()
                    .unwrap(),
                ) == PathBuf::from("b/c")
            })
            .unwrap();

        assert_eq!(
            &Value::String("mount_a".into()),
            events[index_b]
                .get("data")
                .unwrap()
                .get("FileChanged")
                .unwrap()
                .get("path")
                .unwrap()
                .get("mount_id")
                .unwrap()
        );

        assert_eq!(
            PathBuf::from("b/c"),
            PathBuf::from(
                events[index_b]
                    .get("data")
                    .unwrap()
                    .get("FileChanged")
                    .unwrap()
                    .get("path")
                    .unwrap()
                    .get("path")
                    .unwrap()
                    .as_str()
                    .unwrap()
            )
        );
    }
}
