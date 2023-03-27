use std::{
    ops::Add,
    sync::Arc,
    time::{Duration, SystemTime},
};

use lib_directory_watcher::{FilesystemEvent, FilesystemEventKind};
use platform::mounts::PathInside;
use time::OffsetDateTime;
use tokio::sync::Mutex;
use uuid::Uuid;

use crate::file_status_store::FileStatusStore;

pub struct EventsReverseRpc;

#[async_trait::async_trait]
impl events::ResponderReverseRpc for EventsReverseRpc {}

pub struct RpcServer<T: FileStatusStore + Sync + Send> {
    file_status_store: Mutex<T>,
    event_service: Arc<dyn events::RequesterRpc>,
    generate_uuid: Box<dyn Fn() -> Uuid + Send + Sync>,
}

impl<T: FileStatusStore + Sync + Send> RpcServer<T> {
    pub const fn new(
        file_status_store: Mutex<T>,
        event_service: Arc<dyn events::RequesterRpc>,
        generate_uuid: Box<dyn Fn() -> Uuid + Send + Sync>,
    ) -> Self {
        Self {
            file_status_store,
            event_service,
            generate_uuid,
        }
    }
}

#[async_trait::async_trait]
impl<T: FileStatusStore + Sync + Send + 'static> lib_directory_watcher::ResponderRpc
    for RpcServer<T>
{
    #[allow(clippy::too_many_lines)]
    async fn file_changed(
        &self,
        event: FilesystemEvent,
        other_side: Arc<dyn lib_directory_watcher::RequesterReverseRpc>,
    ) -> Result<(), lib_directory_watcher::Error> {
        info!("Received file changed event: {:?}", event);

        match event.kind {
            FilesystemEventKind::Created {} | FilesystemEventKind::Modified {} => {
                let contents = other_side
                    .read_file(event.path.clone())
                    .await
                    .unwrap()
                    .unwrap();

                info!("File contents: {}", String::from_utf8(contents).unwrap());

                let timestamp = OffsetDateTime::from_unix_timestamp(
                    event
                        .timestamp
                        .try_into()
                        .map_err(|_| lib_directory_watcher::Error {})?,
                )
                .map_err(|_| lib_directory_watcher::Error {})?;

                let sync_status = self
                    .file_status_store
                    .lock()
                    .await
                    .sync(
                        &PathInside::new(event.mount_id.clone(), event.path.clone()),
                        timestamp,
                    )
                    .await
                    .map_err(|_| lib_directory_watcher::Error {})?;

                let data = match sync_status {
                    crate::file_status_store::FileStatusSyncResult::Created => {
                        events::EventKind::FileCreated {
                            path: events::FileOnMountPath {
                                path: event.path,
                                mount_id: event.mount_id,
                            },
                        }
                    }
                    crate::file_status_store::FileStatusSyncResult::Modified
                    | crate::file_status_store::FileStatusSyncResult::NotModified => {
                        events::EventKind::FileChanged {
                            path: events::FileOnMountPath {
                                path: event.path,
                                mount_id: event.mount_id,
                            },
                        }
                    }
                };

                self.event_service
                    .send_event(events::Event {
                        id: (self.generate_uuid)(),
                        created_time: timestamp.into(),
                        data,
                    })
                    .await
                    .map_err(|_| lib_directory_watcher::Error {})?
                    .map_err(|_| lib_directory_watcher::Error {})?;
            }
            FilesystemEventKind::Moved { to } => {
                self.file_status_store
                    .lock()
                    .await
                    .rename(
                        &PathInside::new(event.mount_id.clone(), event.path.clone()),
                        &PathInside::new(event.mount_id.clone(), to.clone()),
                    )
                    .await
                    .map_err(|_| lib_directory_watcher::Error {})?;

                self.event_service
                    .send_event(events::Event {
                        id: (self.generate_uuid)(),
                        created_time: SystemTime::UNIX_EPOCH
                            .add(Duration::from_secs(event.timestamp)),
                        data: events::EventKind::FileMoved {
                            from: events::FileOnMountPath {
                                path: event.path,
                                mount_id: event.mount_id.clone(),
                            },
                            to: events::FileOnMountPath {
                                path: to,
                                mount_id: event.mount_id,
                            },
                        },
                    })
                    .await
                    .map_err(|_| lib_directory_watcher::Error {})?
                    .map_err(|_| lib_directory_watcher::Error {})?;
            }
            FilesystemEventKind::Deleted {} => {
                self.file_status_store
                    .lock()
                    .await
                    .delete(&PathInside::new(event.mount_id.clone(), event.path.clone()))
                    .await
                    .map_err(|_| lib_directory_watcher::Error {})?;

                self.event_service
                    .send_event(events::Event {
                        id: (self.generate_uuid)(),
                        created_time: SystemTime::UNIX_EPOCH
                            .add(Duration::from_secs(event.timestamp)),
                        data: events::EventKind::FileDeleted {
                            path: events::FileOnMountPath {
                                path: event.path,
                                mount_id: event.mount_id,
                            },
                        },
                    })
                    .await
                    .map_err(|_| lib_directory_watcher::Error {})?
                    .map_err(|_| lib_directory_watcher::Error {})?;
            }
        }

        Ok(())
    }
}

#[cfg(test)]
mod tests {
    use std::str::FromStr;
    use std::sync::Arc;

    use lib_directory_watcher::ResponderRpc;
    use tokio::sync::Mutex;

    use super::*;
    use crate::file_status_store::Error;
    use crate::file_status_store::FileStatusSyncResult;

    struct MockFileStatusStore {
        pub events: Vec<String>,
        pub sync_result: FileStatusSyncResult,
    }

    impl MockFileStatusStore {
        pub fn new(sync_result: FileStatusSyncResult) -> Self {
            Self {
                events: vec![],
                sync_result,
            }
        }
    }

    #[async_trait::async_trait]
    impl FileStatusStore for MockFileStatusStore {
        async fn delete(&mut self, path: &PathInside) -> Result<(), Error> {
            self.events.push(format!("delete {path:?}"));

            Ok(())
        }

        async fn rename(&mut self, from: &PathInside, to: &PathInside) -> Result<(), Error> {
            self.events.push(format!("rename {from:?} to {to:?}"));

            Ok(())
        }

        async fn sync(
            &mut self,
            path: &PathInside,
            modified_at: OffsetDateTime,
        ) -> Result<FileStatusSyncResult, Error> {
            self.events
                .push(format!("sync {path:?} at {modified_at:?}"));

            Ok(self.sync_result)
        }
    }

    struct MockEventsRequester {}

    #[async_trait::async_trait]
    impl events::RequesterRpc for MockEventsRequester {
        async fn send_event(
            &self,
            _request: events::Event,
        ) -> Result<Result<(), events::Error>, rpc_support::connection::Error> {
            Ok(Ok(()))
        }
        async fn subscribe(
            &self,
            _request: events::SubscribeRequest,
        ) -> Result<
            Box<
                dyn futures::Stream<Item = Result<events::Event, events::Error>>
                    + Send
                    + Sync
                    + 'static,
            >,
            rpc_support::connection::Error,
        > {
            todo!()
        }
    }

    struct MockFilesResponder {}

    #[async_trait::async_trait]
    impl lib_directory_watcher::ResponderRpc for MockFilesResponder {
        async fn file_changed(
            &self,
            _request: FilesystemEvent,
            _other_side: std::sync::Arc<dyn lib_directory_watcher::RequesterReverseRpc>,
        ) -> Result<(), lib_directory_watcher::Error> {
            Ok(())
        }
    }

    struct MockFilesReverseRequester {}

    #[async_trait::async_trait]
    impl lib_directory_watcher::RequesterReverseRpc for MockFilesReverseRequester {
        async fn read_file(
            &self,
            _request: String,
        ) -> Result<Result<Vec<u8>, lib_directory_watcher::Error>, rpc_support::connection::Error>
        {
            Ok(Ok(vec![]))
        }
    }

    #[tokio::test]
    async fn test_file_created() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Created);

        let event_service = Arc::new(MockEventsRequester {});

        let rpc_server = RpcServer::new(
            Mutex::new(file_status_store),
            event_service,
            Box::new(|| Uuid::from_str("00000000-0000-0000-0000-000000000000").unwrap()),
        );

        let event = FilesystemEvent {
            kind: FilesystemEventKind::Created {},
            path: "/test".to_string(),
            mount_id: "test".to_string(),
            timestamp: 1024,
        };

        rpc_server
            .file_changed(event, Arc::new(MockFilesReverseRequester {}))
            .await
            .unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.lock().await.events);
    }

    #[tokio::test]
    async fn test_file_moved() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Created);

        let event_service = Arc::new(MockEventsRequester {});

        let rpc_server = RpcServer::new(
            Mutex::new(file_status_store),
            event_service,
            Box::new(|| Uuid::from_str("00000000-0000-0000-0000-000000000000").unwrap()),
        );

        let event = FilesystemEvent {
            kind: FilesystemEventKind::Moved {
                to: "/test2".to_string(),
            },
            path: "/test".to_string(),
            mount_id: "test".to_string(),
            timestamp: 1024,
        };

        rpc_server
            .file_changed(event, Arc::new(MockFilesReverseRequester {}))
            .await
            .unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.lock().await.events);
    }

    #[tokio::test]
    async fn test_file_deleted() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Created);
        let event_service = Arc::new(MockEventsRequester {});

        let rpc_server = RpcServer::new(
            Mutex::new(file_status_store),
            event_service,
            Box::new(|| Uuid::from_str("00000000-0000-0000-0000-000000000000").unwrap()),
        );

        let event = FilesystemEvent {
            kind: FilesystemEventKind::Deleted {},
            path: "/test".to_string(),
            mount_id: "test".to_string(),
            timestamp: 1024,
        };

        rpc_server
            .file_changed(event, Arc::new(MockFilesReverseRequester {}))
            .await
            .unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.lock().await.events);
    }

    #[tokio::test]
    async fn test_file_modified() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Modified);

        let event_service = Arc::new(MockEventsRequester {});

        let rpc_server = RpcServer::new(
            Mutex::new(file_status_store),
            event_service,
            Box::new(|| Uuid::from_str("00000000-0000-0000-0000-000000000000").unwrap()),
        );

        let event = FilesystemEvent {
            kind: FilesystemEventKind::Modified {},
            path: "/test".to_string(),
            mount_id: "test".to_string(),
            timestamp: 1024,
        };

        rpc_server
            .file_changed(event, Arc::new(MockFilesReverseRequester {}))
            .await
            .unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.lock().await.events);
    }
}
