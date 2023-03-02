use std::{
    ops::Add,
    time::{Duration, SystemTime},
};

use events::{Client, Rpc as EventsRpc};
use lib_directory_watcher::{FilesystemEvent, FilesystemEventKind, Metadata, Rpc};
use platform::mounts::PathInside;
use rpc_support::{rpc_error::RpcError, RawRpcClient};
use time::OffsetDateTime;
use uuid::Uuid;

use crate::file_status_store::FileStatusStore;

pub struct RpcServer<T: FileStatusStore + Sync + Send, TRawRpcClient: RawRpcClient + Send + Sync> {
    file_status_store: T,
    event_service: Client<TRawRpcClient>,
    generate_uuid: Box<dyn Fn() -> Uuid + Send + Sync>,
}

impl<T: FileStatusStore + Sync + Send, TRawRpcClient: RawRpcClient + Send + Sync>
    RpcServer<T, TRawRpcClient>
{
    pub const fn new(
        file_status_store: T,
        event_service: events::Client<TRawRpcClient>,
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
impl<T: FileStatusStore + Sync + Send, TRawRpcClient: RawRpcClient + Send + Sync> Rpc
    for RpcServer<T, TRawRpcClient>
{
    #[allow(clippy::too_many_lines)]
    async fn file_changed(
        &mut self,
        event: FilesystemEvent,
        _metadata: Metadata,
    ) -> Result<(), RpcError> {
        info!("Received file changed event: {:?}", event);

        match event.kind {
            FilesystemEventKind::Created {} | FilesystemEventKind::Modified {} => {
                let timestamp = OffsetDateTime::from_unix_timestamp(
                    event
                        .timestamp
                        .try_into()
                        .map_err(|e| RpcError::Custom(format!("{e}")))?,
                )
                .map_err(|e| RpcError::Custom(format!("{e}")))?;

                let sync_status = self
                    .file_status_store
                    .sync(
                        &PathInside::new(event.mount_id.clone(), event.path.clone()),
                        timestamp,
                    )
                    .await
                    .map_err(|e| RpcError::Custom(format!("{e}")))?;

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
                    .send_event(
                        events::Event {
                            id: (self.generate_uuid)(),
                            created_time: timestamp.into(),
                            data,
                        },
                        events::Metadata {
                            // TODO pass the correlation ID from parent scope!
                            correlation_id: (self.generate_uuid)(),
                            source: "directory-watcher".to_string(),
                        },
                    )
                    .await?;
            }
            FilesystemEventKind::Moved { to } => {
                self.file_status_store
                    .rename(
                        &PathInside::new(event.mount_id.clone(), event.path.clone()),
                        &PathInside::new(event.mount_id.clone(), to.clone()),
                    )
                    .await
                    .map_err(|e| RpcError::Custom(format!("{e}")))?;

                self.event_service
                    .send_event(
                        events::Event {
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
                        },
                        events::Metadata {
                            // TODO pass the correlation ID from parent scope!
                            correlation_id: (self.generate_uuid)(),
                            source: "directory-watcher".to_string(),
                        },
                    )
                    .await?;
            }
            FilesystemEventKind::Deleted {} => {
                self.file_status_store
                    .delete(&PathInside::new(event.mount_id.clone(), event.path.clone()))
                    .await
                    .map_err(|e| RpcError::Custom(format!("{e}")))?;

                self.event_service
                    .send_event(
                        events::Event {
                            id: (self.generate_uuid)(),
                            created_time: SystemTime::UNIX_EPOCH
                                .add(Duration::from_secs(event.timestamp)),
                            data: events::EventKind::FileDeleted {
                                path: events::FileOnMountPath {
                                    path: event.path,
                                    mount_id: event.mount_id,
                                },
                            },
                        },
                        events::Metadata {
                            // TODO pass the correlation ID from parent scope!
                            correlation_id: (self.generate_uuid)(),
                            source: "directory-watcher".to_string(),
                        },
                    )
                    .await?;
            }
        }

        Ok(())
    }
}

#[cfg(test)]
mod tests {
    use std::str::FromStr;
    use std::sync::Arc;

    use rpc_support::ResponseStream;
    use serde::{de::DeserializeOwned, Serialize};
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

    struct MockRawRpcClient {
        pub rpcs: Arc<Mutex<Vec<String>>>,
    }

    #[async_trait::async_trait]
    impl RawRpcClient for MockRawRpcClient {
        // TODO rename to send_rpc_request
        async fn send_rpc<TRequest, TMetadata, TResponse>(
            &mut self,
            id: u64,
            method_name: &str,
            request: &TRequest,
            metadata: &TMetadata,
        ) -> Result<TResponse, RpcError>
        where
            TRequest: Serialize + Sync + Send,
            TMetadata: Serialize + Sync + Send,
            TResponse: DeserializeOwned,
        {
            self.rpcs.lock().await.push(format!(
                "send_rpc {id} {method_name} {:?} {:?}",
                serde_json::to_value(request),
                serde_json::to_value(metadata)
            ));

            Ok(serde_json::from_str("null").unwrap())
        }

        async fn send_rpc_stream_request<TRequest, TMetadata, TResponse>(
            &mut self,
            _request_id: u64,
            _method_name: &str,
            _request: &TRequest,
            _metadata: &TMetadata,
        ) -> Result<ResponseStream<TResponse>, RpcError>
        where
            TRequest: Serialize + Sync + Send,
            TMetadata: Serialize + Sync + Send,
            TResponse: DeserializeOwned,
        {
            todo!()
        }
    }

    #[tokio::test]
    async fn test_file_created() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Created);
        let rpcs = Arc::new(Mutex::new(vec![]));
        let raw_rpc_client = MockRawRpcClient { rpcs: rpcs.clone() };

        let event_service = events::Client::new(raw_rpc_client);

        let mut rpc_server = RpcServer::new(
            file_status_store,
            event_service,
            Box::new(|| Uuid::from_str("00000000-0000-0000-0000-000000000000").unwrap()),
        );

        let event = FilesystemEvent {
            kind: FilesystemEventKind::Created {},
            path: "/test".to_string(),
            mount_id: "test".to_string(),
            timestamp: 1024,
        };

        rpc_server.file_changed(event, Metadata {}).await.unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.events);

        let rpcs = rpcs.lock().await;

        insta::assert_debug_snapshot!(rpcs.clone());
    }

    #[tokio::test]
    async fn test_file_moved() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Created);
        let rpcs = Arc::new(Mutex::new(vec![]));
        let raw_rpc_client = MockRawRpcClient { rpcs: rpcs.clone() };

        let event_service = events::Client::new(raw_rpc_client);

        let mut rpc_server = RpcServer::new(
            file_status_store,
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

        rpc_server.file_changed(event, Metadata {}).await.unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.events);

        let rpcs = rpcs.lock().await;

        insta::assert_debug_snapshot!(rpcs.clone());
    }

    #[tokio::test]
    async fn test_file_deleted() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Created);
        let rpcs = Arc::new(Mutex::new(vec![]));
        let raw_rpc_client = MockRawRpcClient { rpcs: rpcs.clone() };

        let event_service = events::Client::new(raw_rpc_client);

        let mut rpc_server = RpcServer::new(
            file_status_store,
            event_service,
            Box::new(|| Uuid::from_str("00000000-0000-0000-0000-000000000000").unwrap()),
        );

        let event = FilesystemEvent {
            kind: FilesystemEventKind::Deleted {},
            path: "/test".to_string(),
            mount_id: "test".to_string(),
            timestamp: 1024,
        };

        rpc_server.file_changed(event, Metadata {}).await.unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.events);

        let rpcs = rpcs.lock().await;

        insta::assert_debug_snapshot!(rpcs.clone());
    }

    #[tokio::test]
    async fn test_file_modified() {
        let file_status_store = MockFileStatusStore::new(FileStatusSyncResult::Modified);
        let rpcs = Arc::new(Mutex::new(vec![]));
        let raw_rpc_client = MockRawRpcClient { rpcs: rpcs.clone() };

        let event_service = events::Client::new(raw_rpc_client);

        let mut rpc_server = RpcServer::new(
            file_status_store,
            event_service,
            Box::new(|| Uuid::from_str("00000000-0000-0000-0000-000000000000").unwrap()),
        );

        let event = FilesystemEvent {
            kind: FilesystemEventKind::Modified {},
            path: "/test".to_string(),
            mount_id: "test".to_string(),
            timestamp: 1024,
        };

        rpc_server.file_changed(event, Metadata {}).await.unwrap();

        insta::assert_debug_snapshot!(rpc_server.file_status_store.events);

        let rpcs = rpcs.lock().await;

        insta::assert_debug_snapshot!(rpcs.clone());
    }
}
