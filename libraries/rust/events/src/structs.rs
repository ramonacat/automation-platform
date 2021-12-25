use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize)]
pub struct Metadata {
        pub source: String,
        pub id: ::uuid::Uuid,
        #[serde(with="crate::system_time_serializer")]
        pub created_time: std::time::SystemTime,
}
#[derive(Serialize, Deserialize)]
pub struct FileOnMountPath {
        pub path: String,
        pub mount_id: String,
}

#[derive(Serialize, Deserialize)]
#[serde(tag = "type")]
pub enum MessagePayload {
    FileDeleted {
        path: FileOnMountPath,
    },
    FileChanged {
        path: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
    },
    FileMoved {
        from: FileOnMountPath,
        to: FileOnMountPath,
    },
}
#[derive(Serialize, Deserialize)]
pub struct Message {
    pub metadata: Metadata,
    pub payload: MessagePayload,
}
