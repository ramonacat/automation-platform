use crate::platform::events::EventSender;
use crate::{platform, MountRelativePath};
use chrono::{DateTime, Utc};
use uuid::Uuid;

#[derive(Serialize)]
struct FileCreated<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    path: &'a str,
    mount_id: &'a str,
}

#[derive(Serialize)]
struct FileChanged<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    path: &'a str,
    mount_id: &'a str,
}

#[derive(Serialize)]
struct FileDeleted<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    path: &'a str,
    mount_id: &'a str,
}

#[derive(Serialize)]
struct FileMoved<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    from: &'a str,
    to: &'a str,
    mount_id: &'a str,
}

impl<'a> platform::events::Event for FileCreated<'a> {}
impl<'a> platform::events::Event for FileChanged<'a> {}
impl<'a> platform::events::Event for FileDeleted<'a> {}
impl<'a> platform::events::Event for FileMoved<'a> {}

pub async fn send_file_created(
    es: &EventSender,
    path: &MountRelativePath<'_>,
) -> Result<(), platform::events::Error> {
    es.send(FileCreated {
        id: Uuid::new_v4(),
        created_timestamp: Utc::now(),
        type_name: "file.status.created",
        path: &path.path,
        mount_id: path.mount_id,
    })
    .await?;

    Ok(())
}

pub async fn send_file_changed(
    es: &EventSender,
    path: &MountRelativePath<'_>,
) -> Result<(), platform::events::Error> {
    es.send(FileChanged {
        id: Uuid::new_v4(),
        created_timestamp: Utc::now(),
        type_name: "file.status.changed",
        path: &path.path,
        mount_id: path.mount_id,
    })
    .await?;

    Ok(())
}

pub async fn send_file_deleted(
    es: &EventSender,
    path: &MountRelativePath<'_>,
) -> Result<(), platform::events::Error> {
    es.send(FileDeleted {
        id: Uuid::new_v4(),
        created_timestamp: Utc::now(),
        type_name: "file.status.deleted",
        path: &path.path,
        mount_id: path.mount_id,
    })
    .await?;

    Ok(())
}

pub async fn send_file_moved(
    es: &EventSender,
    from: &MountRelativePath<'_>,
    to: &MountRelativePath<'_>,
) -> Result<(), platform::events::Error> {
    assert_eq!(from.mount_id, to.mount_id, "File moved between mounts");
    es.send(FileMoved {
        id: Uuid::new_v4(),
        created_timestamp: Utc::now(),
        type_name: "file.status.deleted",
        from: &from.path,
        to: &from.path,
        mount_id: from.mount_id,
    })
    .await?;

    Ok(())
}
