use std::{pin::Pin, sync::atomic::AtomicU64};

use crate::structs::{
    AllAlbums, AllAlbumsRequest, AllArtists, AllTracks, AllTracksRequest, Metadata, Rpc,
    StreamTrackRequest, TrackData,
};
use async_std::stream::Stream;
use rpc_support::{rpc_error::RpcError, RawRpcClient};

pub struct Client<TRpcClient: RawRpcClient> {
    id: AtomicU64,
    raw: TRpcClient,
}

impl<TRpcClient: RawRpcClient> Client<TRpcClient> {
    /// # Errors
    /// Will return an error when the TCP connection fails.
    pub fn new(raw_rpc_client: TRpcClient) -> Result<Self, RpcError> {
        Ok(Client {
            raw: raw_rpc_client,
            id: AtomicU64::new(0),
        })
    }
}

#[async_trait::async_trait]
impl<TRpcClient: RawRpcClient + Send + Sync> Rpc for Client<TRpcClient> {
    async fn stream_track(
        &mut self,
        request: StreamTrackRequest,
        metadata: Metadata,
    ) -> Result<Pin<Box<dyn Stream<Item = Result<TrackData, RpcError>> + Unpin + Send>>, RpcError>
    {
        self.raw
            .send_rpc_stream_request(
                self.id.fetch_add(1, std::sync::atomic::Ordering::AcqRel),
                "stream_track",
                &request,
                &metadata,
            )
            .await
    }

    async fn all_artists(
        &mut self,
        request: (),
        metadata: Metadata,
    ) -> Result<AllArtists, RpcError> {
        self.raw
            .send_rpc(
                self.id.fetch_add(1, std::sync::atomic::Ordering::AcqRel),
                "all_artists",
                &request,
                &metadata,
            )
            .await
    }

    async fn all_albums(
        &mut self,
        request: AllAlbumsRequest,
        metadata: Metadata,
    ) -> Result<AllAlbums, RpcError> {
        self.raw
            .send_rpc(
                self.id.fetch_add(1, std::sync::atomic::Ordering::AcqRel),
                "all_albums",
                &request,
                &metadata,
            )
            .await
    }

    async fn all_tracks(
        &mut self,
        request: AllTracksRequest,
        metadata: Metadata,
    ) -> Result<AllTracks, RpcError> {
        self.raw
            .send_rpc(
                self.id.fetch_add(1, std::sync::atomic::Ordering::AcqRel),
                "all_tracks",
                &request,
                &metadata,
            )
            .await
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use rpc_support::{RawRpcClient, ResponseStream};
    use serde::{de::DeserializeOwned, Serialize};
    use tokio::sync::mpsc::Sender;
    use uuid::Uuid;

    struct MockRawRpcClient {
        pub requests: Sender<(u64, String, serde_json::Value, serde_json::Value)>,
    }

    #[async_trait::async_trait]
    impl RawRpcClient for MockRawRpcClient {
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
            self.requests
                .send((
                    id,
                    method_name.to_string(),
                    serde_json::to_value(request).unwrap(),
                    serde_json::to_value(metadata).unwrap(),
                ))
                .await
                .unwrap();

            Ok(serde_json::from_str("{\"tracks\": []}").unwrap())
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
            unimplemented!();
        }
    }

    #[tokio::test]
    pub async fn can_request_all_tracks() {
        let (requests_tx, mut requests_rx) = tokio::sync::mpsc::channel(1);
        let raw_client = MockRawRpcClient {
            requests: requests_tx,
        };
        let mut client = Client::new(raw_client).unwrap();
        let album_id = Uuid::new_v4();
        let request = AllTracksRequest { album_id };
        let metadata = Metadata {};
        client.all_tracks(request, metadata).await.unwrap();

        let request_sent = requests_rx.recv().await.unwrap();

        assert_eq!(request_sent.0, 0);
        assert_eq!(request_sent.1, "all_tracks");
        assert_eq!(
            request_sent.2,
            serde_json::to_value(&AllTracksRequest { album_id }).unwrap()
        );
        assert_eq!(request_sent.3, serde_json::to_value(&Metadata {}).unwrap());
    }
}
