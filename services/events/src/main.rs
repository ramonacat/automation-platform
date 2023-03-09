#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use std::error::Error;
use std::future::Future;
use std::pin::Pin;
use tracing::{debug, info};

use tokio_postgres::Client;

use events::{Event, EventKind, Metadata, RpcServer as Rpc, Server, SubscribeRequest};
use futures_lite::stream::StreamExt;
use futures_lite::Stream;
use platform::async_infra::run_with_error_handling;
use postgres_native_tls::MakeTlsConnector;
use rpc_support::{rpc_error::RpcError, Client as RpcClient};
use std::sync::{Arc, Weak};
use std::time::SystemTime;
use tokio::sync::mpsc::Sender;
use tokio::sync::Mutex;
use tokio_stream::wrappers::ReceiverStream;
use uuid::Uuid;

#[macro_use]
extern crate async_trait;

struct Subscription {
    tx: Sender<Event>,
    cursor: Option<SystemTime>,
}

struct RpcServer {
    postgres: Arc<Mutex<Client>>,
    subscription_handler: SubscriptionHandler,
}

#[derive(Clone, Debug)]
struct SavedEvent {
    event: Event,
    timestamp: SystemTime,
}

fn rpc_error_map(e: impl Error) -> RpcError {
    RpcError::Custom(e.to_string())
}

struct SubscriptionHandler {
    subscriptions: Arc<dashmap::DashMap<Uuid, Subscription>>,
    last_pushed_event_timestamp: Arc<Mutex<Option<SystemTime>>>,
}

impl SubscriptionHandler {
    fn new(
        postgres: Arc<Mutex<tokio_postgres::Client>>,
    ) -> (Self, impl Future<Output = Result<(), RpcError>>) {
        let subscriptions = Arc::new(dashmap::DashMap::new());
        let last_pushed_event_timestamp = Arc::new(Mutex::new(None));

        let handler = Self {
            subscriptions: subscriptions.clone(),
            last_pushed_event_timestamp: last_pushed_event_timestamp.clone(),
        };

        (handler, async move {
            loop {
                info!("Waiting for new events");

                // TODO this is very unoptimal, we should use a single query to get all events since the last one
                // FIXME: don't sleep here, wait for events in a different way instead!
                let last_pushed_event_timestamp = *last_pushed_event_timestamp.lock().await;
                for mut subscription in subscriptions.iter_mut() {
                    if subscription.value().cursor.map_or(true, |cursor| {
                        last_pushed_event_timestamp.map_or(true, |x| x > cursor)
                    }) {
                        let events =
                            Self::read_events(postgres.clone(), subscription.value().cursor)
                                .await?;

                        for event in events {
                            info!(
                                "Sending event {:?} to subscriber {}",
                                event,
                                subscription.key()
                            );

                            subscription.value().tx.send(event.event).await?;
                            subscription.value_mut().cursor = Some(event.timestamp);
                        }
                    }
                }

                tokio::time::sleep(std::time::Duration::from_secs(1)).await;
            }
        })
    }

    async fn read_events(
        postgres: Arc<Mutex<Client>>,
        since: Option<SystemTime>,
    ) -> Result<Vec<SavedEvent>, RpcError> {
        let mut query = "SELECT data, created_timestamp FROM events".to_string();

        let rows = if let Some(cursor) = since {
            query.push_str(" WHERE created_timestamp > $1");
            query.push_str(" ORDER BY created_timestamp ASC");

            postgres
                .lock()
                .await
                .query(&query, &[&cursor])
                .await
                .map_err(rpc_error_map)?
        } else {
            query.push_str(" ORDER BY created_timestamp ASC");

            postgres
                .lock()
                .await
                .query(&query, &[])
                .await
                .map_err(rpc_error_map)?
        };

        let mut events = Vec::new();
        for row in rows {
            let data: serde_json::Value = row.get(0);

            events.push(SavedEvent {
                event: serde_json::from_value(data).map_err(rpc_error_map)?,
                timestamp: row.get(1),
            });
        }

        Ok(events)
    }
}

impl RpcServer {
    pub fn new(postgres: Arc<Mutex<tokio_postgres::Client>>) -> Self {
        let (subscription_handler, task) = SubscriptionHandler::new(postgres.clone());
        tokio::spawn(run_with_error_handling(task));

        Self {
            postgres,
            subscription_handler,
        }
    }

    async fn save_event(&mut self, name: &str, message: Event) -> Result<(), RpcError> {
        let serde_value = serde_json::to_value(&message).map_err(rpc_error_map)?;

        self.postgres
            .lock()
            .await
            .execute(
                "INSERT INTO events(id, created_timestamp, type, data) VALUES($1,$2,$3,$4)",
                &[&message.id, &message.created_time, &name, &serde_value],
            )
            .await
            .map_err(rpc_error_map)?;

        debug!(
            "Message handled: {}",
            serde_json::to_string(&message).map_err(rpc_error_map)?
        );

        Ok(())
    }
}

#[async_trait]
impl Rpc for RpcServer {
    async fn subscribe(
        &mut self,
        request: SubscribeRequest,
        _metadata: Metadata,
        _client: Weak<Mutex<dyn RpcClient>>,
    ) -> Result<Pin<Box<dyn Stream<Item = Result<Event, RpcError>> + Unpin + Send>>, RpcError> {
        let (tx, rx) = tokio::sync::mpsc::channel(100);

        // FIXME add a method on the handler for that
        self.subscription_handler.subscriptions.insert(
            request.id,
            Subscription {
                tx,
                cursor: request.from,
            },
        );

        let stream = ReceiverStream::new(rx).map(Ok);

        Ok(Box::pin(stream))
    }

    async fn send_event(
        &mut self,
        request: Event,
        _metadata: Metadata,
        _client: Weak<Mutex<dyn RpcClient>>,
    ) -> Result<(), RpcError> {
        let created_time = request.created_time;
        self.save_event(
            match request.data {
                EventKind::FileDeleted { .. } => "FileDeleted",
                EventKind::FileCreated { .. } => "FileCreated",
                EventKind::FileMoved { .. } => "FileMoved",
                EventKind::FileChanged { .. } => "FileChanged",
            },
            request,
        )
        .await?;

        *self
            .subscription_handler
            .last_pushed_event_timestamp
            .lock()
            .await = Some(created_time);

        Ok(())
    }
}

#[tokio::main]
#[tracing::instrument]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

    let secret_provider = platform::secrets::SecretProvider::new("/etc/svc-events/secrets/");
    let secret = secret_provider.read("events.ap-events.credentials")?;

    let tls_connector = MakeTlsConnector::new(
        native_tls::TlsConnector::builder()
            .danger_accept_invalid_certs(true) // fixme don't accept invalid certs!
            .build()?,
    );

    let (client, connection) = tokio_postgres::connect(
        &format!(
            "host=ap-events user={} password={}",
            secret.username(),
            secret.password()
        ),
        tls_connector,
    )
    .await?;

    tokio::spawn(async move {
        run_with_error_handling(connection).await;
    });

    let rpc_server = Arc::new(Mutex::new(RpcServer::new(Arc::new(Mutex::new(client)))));

    // todo make the bind addr/port configurable
    let server = Server::new("0.0.0.0:7654", rpc_server).await?;
    server.run().await?;

    Ok(())
}
