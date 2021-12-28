use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {
    pub source: String,
    pub id: ::uuid::Uuid,
    #[serde(with = "crate::system_time_serializer")]
    pub created_time: std::time::SystemTime,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileOnMountPath {
    pub mount_id: String,
    pub path: String,
}

#[derive(Serialize, Deserialize, Debug)]
#[serde(tag = "type")]
pub enum MessagePayload {
    FileCreated {
        path: FileOnMountPath,
    },
    FileMoved {
        to: FileOnMountPath,
        from: FileOnMountPath,
    },
    FileChanged {
        path: FileOnMountPath,
    },
    FileDeleted {
        path: FileOnMountPath,
    },
}
#[derive(Serialize, Deserialize, Debug)]
pub struct Message {
    pub metadata: Metadata,
    pub payload: MessagePayload,
}
