use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {
    pub id: ::uuid::Uuid,
    #[serde(with = "crate::system_time_serializer")]
    pub created_time: std::time::SystemTime,
    pub source: String,
}
#[derive(Serialize, Deserialize, Debug)]
pub struct FileOnMountPath {
    pub mount_id: String,
    pub path: String,
}

#[derive(Serialize, Deserialize, Debug)]
#[serde(tag = "type")]
pub enum MessagePayload {
    FileDeleted {
        path: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
    },
    FileMoved {
        from: FileOnMountPath,
        to: FileOnMountPath,
    },
    FileChanged {
        path: FileOnMountPath,
    },
}
#[derive(Serialize, Deserialize, Debug)]
pub struct Message {
    pub metadata: Metadata,
    pub payload: MessagePayload,
}
