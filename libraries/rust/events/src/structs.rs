use serde::{Deserialize, Serialize};
#[derive(Serialize, Deserialize, Debug)]
pub struct Metadata {
    #[serde(with = "crate::system_time_serializer")]
    pub created_time: std::time::SystemTime,
    pub id: ::uuid::Uuid,
    pub source: String,
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
    FileChanged {
        path: FileOnMountPath,
    },
    FileDeleted {
        path: FileOnMountPath,
    },
    FileCreated {
        path: FileOnMountPath,
    },
}
#[derive(Serialize, Deserialize, Debug)]
pub struct Message {
    pub metadata: Metadata,
    pub payload: MessagePayload,
}
