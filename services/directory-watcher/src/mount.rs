use std::path::{Path, PathBuf};

pub struct Mount {
    path: PathBuf,
    id: String,
}

impl Mount {
    pub const fn new(id: String, path: PathBuf) -> Self {
        Self { path, id }
    }

    pub fn path(&self) -> &Path {
        &self.path
    }

    pub fn id(&self) -> &str {
        &self.id
    }
}

pub struct PathInside<'a> {
    mount_id: &'a str,
    path: PathBuf,
}

#[derive(Error, Debug)]
pub enum Error {
    #[error("The given absolute path is not within the mount")]
    PathNotInMount,
}

impl<'a> PathInside<'a> {
    pub fn from_absolute(mount: &'a Mount, absolute_path: PathBuf) -> Result<Self, Error> {
        pathdiff::diff_paths(absolute_path, &mount.path).map_or(
            Err(Error::PathNotInMount),
            |relative_path| {
                Ok(PathInside {
                    mount_id: &mount.id,
                    path: relative_path.as_path().to_owned(),
                })
            },
        )
    }

    pub fn from_mount_list(mounts: &'a [Mount], path: &Path) -> Result<Self, Error> {
        for mount in mounts {
            if let Ok(mount_relative_path) = Self::from_absolute(mount, path.to_path_buf()) {
                return Ok(mount_relative_path);
            }
        }

        Err(Error::PathNotInMount)
    }

    pub fn path(&'a self) -> &'a Path {
        &self.path
    }

    pub const fn mount_id(&self) -> &'a str {
        self.mount_id
    }
}
