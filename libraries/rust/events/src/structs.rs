use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {
    pub source: String,
    #[serde(with = "crate::system_time_serializer")]
    pub created_time: std::time::SystemTime,
    pub id: ::uuid::Uuid,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileOnMountPath {
    pub path: String,
    pub mount_id: String,
}

#[derive(Serialize, Deserialize, Debug)]
#[serde(tag = "type")]
pub enum MessagePayload {
    FileMoved {
        from: FileOnMountPath,
        to: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
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
