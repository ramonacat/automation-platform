use std::path::{Path, PathBuf};

#[derive(Debug)]
pub struct Mount {
    id: String,
    path: PathBuf,
}

impl Mount {
    pub const fn new(id: String, path: PathBuf) -> Self {
        Self { id, path }
    }

    pub fn path(&self) -> &Path {
        &self.path
    }

    pub fn id(&self) -> &str {
        &self.id
    }
}

#[derive(Debug, Clone)]
pub struct PathInside<'a> {
    mount: &'a Mount,
    path: PathBuf,
}

#[derive(Error, Debug, Eq, PartialEq)]
pub enum Error {
    #[error("The given absolute path is not within the mount")]
    PathNotInMount,
    #[error("Failed to make the path relative")]
    UnableToMakeRelative,
}

impl<'a> PathInside<'a> {
    pub fn from_absolute(mount: &'a Mount, absolute_path: &Path) -> Result<Self, Error> {
        pathdiff::diff_paths(absolute_path, &mount.path()).map_or(
            Err(Error::UnableToMakeRelative),
            |relative_path| {
                if relative_path.starts_with("..") {
                    Err(Error::PathNotInMount)
                } else {
                    Ok(PathInside {
                        mount,
                        path: relative_path.as_path().to_owned(),
                    })
                }
            },
        )
    }

    pub fn from_mount_list(mounts: &'a [Mount], path: &Path) -> Result<Self, Error> {
        for mount in mounts {
            if let Ok(mount_relative_path) = Self::from_absolute(mount, path) {
                return Ok(mount_relative_path);
            }
        }

        Err(Error::PathNotInMount)
    }

    pub fn path(&'a self) -> &'a Path {
        &self.path
    }

    pub fn mount_id(&self) -> &'a str {
        self.mount.id()
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    pub fn can_create_mount_relative_path_from_absolute() {
        let mount = Mount::new("some_id".into(), Path::new("/tmp/a/").to_path_buf());
        let result = PathInside::from_absolute(&mount, Path::new("/tmp/a/b/c/")).unwrap();

        assert_eq!(result.path(), Path::new("b/c/"));
    }

    #[test]
    pub fn can_find_the_matching_mount_from_absolute_path() {
        let mount_a = Mount::new("mount_a".into(), Path::new("/tmp/a/").to_path_buf());
        let mount_b = Mount::new("mount_b".into(), Path::new("/tmp/b/").to_path_buf());

        let mounts = &[mount_a, mount_b];
        let result = PathInside::from_mount_list(mounts, Path::new("/tmp/b/c")).unwrap();

        assert_eq!(result.mount_id(), "mount_b");
    }

    #[test]
    pub fn will_error_if_path_is_in_none_of_the_mounts() {
        let mount_a = Mount::new("mount_a".into(), Path::new("/tmp/a/").to_path_buf());
        let mount_b = Mount::new("mount_b".into(), Path::new("/tmp/b/").to_path_buf());

        let mounts = &[mount_a, mount_b];
        let result = PathInside::from_mount_list(mounts, Path::new("/tmp/c/d"));

        assert_eq!(result.unwrap_err(), Error::PathNotInMount);
    }
}
