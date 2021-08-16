use crate::WatchableMount;

pub struct MountRelativePath<'a> {
    mount_id: &'a str,
    path: String,
}

#[derive(Error, Debug)]
pub enum Error {
    #[error("The given absolute path is not within the mount")]
    PathNotInMount,
}

impl<'a> MountRelativePath<'a> {
    ///
    /// # Errors
    /// Will return an error if the absolute path is not within the mount
    pub fn from_absolute(mount: &'a WatchableMount, absolute_path: &str) -> Result<Self, Error> {
        pathdiff::diff_paths(absolute_path, &mount.path).map_or(
            Err(Error::PathNotInMount),
            |relative_path| {
                Ok(MountRelativePath {
                    mount_id: &mount.mount_id,
                    path: relative_path.to_string_lossy().to_string(),
                })
            },
        )
    }

    pub fn path(&'a self) -> &'a str {
        &self.path
    }

    pub const fn mount_id(&self) -> &'a str {
        self.mount_id
    }
}
