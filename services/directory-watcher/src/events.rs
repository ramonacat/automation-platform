use crate::mount::PathInside;
use crate::platform;
use chrono::{DateTime, Utc};
use uuid::Uuid;

#[derive(Serialize, Copy, Clone, Debug)]
pub struct FileCreated<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    path: &'a str,
    mount_id: &'a str,
}

impl<'a> FileCreated<'a> {
    pub fn new(path: &'a PathInside) -> Self {
        Self {
            id: Uuid::new_v4(),
            created_timestamp: Utc::now(),
            type_name: "file.status.created",
            path: path
                .path()
                .to_str()
                .expect("This path cannot be converted to a string"), // fixme this probably shouldn't panic...
            mount_id: path.mount_id(),
        }
    }
}

#[derive(Serialize, Copy, Clone, Debug)]
pub struct FileChanged<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    path: &'a str,
    mount_id: &'a str,
}

impl<'a> FileChanged<'a> {
    pub fn new(path: &'a PathInside) -> Self {
        Self {
            id: Uuid::new_v4(),
            created_timestamp: Utc::now(),
            type_name: "file.status.changed",
            path: path
                .path()
                .to_str()
                .expect("This path cannot be converted to a string"), // fixme this probably shouldn't panic...
            mount_id: path.mount_id(),
        }
    }
}

#[derive(Serialize, Copy, Clone, Debug)]
pub struct FileDeleted<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    path: &'a str,
    mount_id: &'a str,
}

impl<'a> FileDeleted<'a> {
    pub fn new(path: &'a PathInside) -> Self {
        Self {
            id: Uuid::new_v4(),
            created_timestamp: Utc::now(),
            type_name: "file.status.deleted",
            path: path
                .path()
                .to_str()
                .expect("This path cannot be converted to a string"), // fixme this probably shouldn't panic...
            mount_id: path.mount_id(),
        }
    }
}

#[derive(Serialize, Copy, Clone, Debug)]
pub struct FileMoved<'a> {
    id: Uuid,
    created_timestamp: DateTime<Utc>,
    #[serde(rename = "type")]
    type_name: &'a str,
    from: &'a str,
    to: &'a str,
    mount_id: &'a str,
}

impl<'a> FileMoved<'a> {
    pub fn new(from: &'a PathInside, to: &'a PathInside) -> Self {
        assert_eq!(from.mount_id(), to.mount_id());

        Self {
            id: Uuid::new_v4(),
            created_timestamp: Utc::now(),
            type_name: "file.status.moved",
            from: from
                .path()
                .to_str()
                .expect("This path cannot be converted to a string"), // fixme this probably shouldn't panic...
            to: to
                .path()
                .to_str()
                .expect("This path cannot be converted to string"),
            mount_id: from.mount_id(),
        }
    }
}

impl<'a> platform::events::Event for FileCreated<'a> {}
impl<'a> platform::events::Event for FileChanged<'a> {}
impl<'a> platform::events::Event for FileDeleted<'a> {}
impl<'a> platform::events::Event for FileMoved<'a> {}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::mount::Mount;
    use std::path::PathBuf;

    #[test]
    pub fn can_create_file_created() {
        let mount = Mount::new("id1".into(), PathBuf::from("/tmp/a/"));
        let path_inside =
            PathInside::from_absolute(&mount, &PathBuf::from("/tmp/a/file1")).unwrap();
        let event = FileCreated::new(&path_inside);

        assert_eq!(event.type_name, "file.status.created");
        assert_eq!(event.path, "file1");
        assert_eq!(event.mount_id, "id1");
    }

    #[test]
    pub fn can_create_file_changed() {
        let mount = Mount::new("id1".into(), PathBuf::from("/tmp/a/"));
        let path_inside =
            PathInside::from_absolute(&mount, &PathBuf::from("/tmp/a/file1")).unwrap();
        let event = FileChanged::new(&path_inside);

        assert_eq!(event.type_name, "file.status.changed");
        assert_eq!(event.path, "file1");
        assert_eq!(event.mount_id, "id1");
    }

    #[test]
    pub fn can_create_file_deleted() {
        let mount = Mount::new("id1".into(), PathBuf::from("/tmp/a/"));
        let path_inside =
            PathInside::from_absolute(&mount, &PathBuf::from("/tmp/a/file1")).unwrap();
        let event = FileDeleted::new(&path_inside);

        assert_eq!(event.type_name, "file.status.deleted");
        assert_eq!(event.path, "file1");
        assert_eq!(event.mount_id, "id1");
    }

    #[test]
    pub fn can_create_file_moved() {
        let mount = Mount::new("id1".into(), PathBuf::from("/tmp/a/"));
        let from = PathInside::from_absolute(&mount, &PathBuf::from("/tmp/a/file1")).unwrap();
        let to = PathInside::from_absolute(&mount, &PathBuf::from("/tmp/a/dir1/file2")).unwrap();
        let event = FileMoved::new(&from, &to);

        assert_eq!(event.type_name, "file.status.moved");
        assert_eq!(event.from, "file1");
        assert_eq!(PathBuf::from(event.to), PathBuf::from("dir1/file2"));
        assert_eq!(event.mount_id, "id1");
    }
}
