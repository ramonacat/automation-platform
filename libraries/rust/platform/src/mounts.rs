use std::collections::HashMap;
use std::path::{Path, PathBuf};
use thiserror::Error;

#[derive(Debug, Error, PartialEq, Eq)]
pub enum MountError {
    #[error("No mounts were defined")]
    NoMountsDefined,
    #[error("The given absolute path is not within the mount")]
    UnableToMakeRelative,
    #[error("Failed to make the path mount-relative")]
    PathNotInMount,
    #[error("Mount with id \"{0}\" does not exist")]
    NoSuchMount(String),
}

pub struct Provider {
    mounts: HashMap<String, Mount>,
}

#[derive(Debug, Clone)]
pub struct Mount {
    id: String,
    path: PathBuf,
}

impl Mount {
    #[must_use]
    pub const fn new(id: String, path: PathBuf) -> Self {
        Self { id, path }
    }

    #[must_use]
    pub fn id(&self) -> &str {
        &self.id
    }

    #[must_use]
    pub fn path(&self) -> &Path {
        &self.path
    }
}

#[derive(Debug, Clone)]
pub struct PathInside {
    // FIXME should the mount be a reference?
    mount_id: String,
    path: String,
}

impl PathInside {
    #[must_use]
    pub const fn new(mount_id: String, path: String) -> Self {
        Self { mount_id, path }
    }

    #[must_use]
    pub fn mount_id(&self) -> &str {
        &self.mount_id
    }

    #[must_use]
    pub fn path(&self) -> &str {
        &self.path
    }
}

impl Provider {
    #[must_use]
    pub fn new(mounts: Vec<Mount>) -> Self {
        Self {
            mounts: mounts
                .into_iter()
                .map(|mount| (mount.id.clone(), mount))
                .collect(),
        }
    }

    #[must_use]
    pub fn from_raw_string(directories_from_env: &str) -> Self {
        Self::new(
            directories_from_env
                .split(',')
                .map(|x| x.split(':').collect())
                .map(|x: Vec<&str>| Mount::new(x[1].into(), x[0].into()))
                .collect(),
        )
    }

    // FIXME can we avoid the clones?
    #[must_use]
    pub fn mounts(&self) -> Vec<Mount> {
        self.mounts.values().cloned().collect()
    }

    // FIXME this needs some careful security review
    /// # Errors
    /// If the path is not within any of the mounts, or if no mounts are defined
    pub fn path_inside_from_filesystem_path(&self, path: &Path) -> Result<PathInside, MountError> {
        let mut result = Err(MountError::NoMountsDefined);
        for mount in self.mounts.values() {
            result = self.path_inside_from_filesystem_path_with_mount(path, mount);

            if result.is_ok() {
                return result;
            }
        }

        result
    }

    /// # Errors
    /// - `MountError::UnableToMakeRelative` if the pathdiff fails
    /// - `MountError::PathNotInMount` if the path is not within the mount
    pub fn path_inside_from_filesystem_path_with_mount(
        &self,
        path: &Path,
        mount: &Mount,
    ) -> Result<PathInside, MountError> {
        pathdiff::diff_paths(path, &mount.path).map_or(
            Err(MountError::UnableToMakeRelative),
            |relative_path| {
                if relative_path.starts_with("..") {
                    Err(MountError::PathNotInMount)
                } else {
                    Ok(PathInside {
                        mount_id: mount.id.clone(),
                        path: relative_path.as_path().to_string_lossy().replace('\\', "/"),
                    })
                }
            },
        )
    }

    /// # Errors
    /// Returns an error if there's no mount for the given path
    pub fn mount_relative_to_filesystem_path(
        &self,
        path_inside: PathInside,
    ) -> Result<PathBuf, MountError> {
        let mount = self
            .mounts
            .get(&path_inside.mount_id)
            .ok_or_else(|| MountError::NoSuchMount(path_inside.mount_id.clone()))?;
        let mut path = mount.path.clone();
        path.push(path_inside.path);

        Ok(path)
    }

    /// # Errors
    /// Returns `Err` if the mount is not found.
    pub fn mount_relative_to_filesystem_path_by_mount_id(
        &self,
        mount_id: &str,
        path_inside: &str,
    ) -> Result<PathBuf, MountError> {
        let mount = self
            .mounts
            .get(mount_id)
            .ok_or_else(|| MountError::NoSuchMount(mount_id.into()))?;
        let mut path = mount.path.clone();
        path.push(path_inside);

        Ok(path)
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::path::Path;

    #[test]
    pub fn can_create_mount_relative_path_from_absolute() {
        let mount = Mount::new("some_id".into(), Path::new("/tmp/a/").to_path_buf());
        let provider = Provider::new(vec![mount.clone()]);

        let result = provider
            .path_inside_from_filesystem_path_with_mount(Path::new("/tmp/a/b/c/"), &mount)
            .unwrap();

        assert_eq!(result.path(), "b/c");
    }

    #[test]
    pub fn can_find_the_matching_mount_from_absolute_path() {
        let mount_a = Mount::new("mount_a".into(), Path::new("/tmp/a/").to_path_buf());
        let mount_b = Mount::new("mount_b".into(), Path::new("/tmp/b/").to_path_buf());
        let provider = Provider::new(vec![mount_a, mount_b]);

        let result = provider
            .path_inside_from_filesystem_path(&Path::new("/tmp/b/c"))
            .unwrap();

        assert_eq!(result.mount_id(), "mount_b");
    }

    #[test]
    pub fn will_error_if_path_is_in_none_of_the_mounts() {
        let mount_a = Mount::new("mount_a".into(), Path::new("/tmp/a/").to_path_buf());
        let mount_b = Mount::new("mount_b".into(), Path::new("/tmp/b/").to_path_buf());

        let provider = Provider::new(vec![mount_a, mount_b]);
        let result = provider.path_inside_from_filesystem_path(Path::new("/tmp/c/d"));

        assert_eq!(result.unwrap_err(), MountError::PathNotInMount);
    }

    #[test]
    pub fn mount_has_an_id() {
        let mount = Mount::new("some_id".into(), Path::new("/tmp/a/").to_path_buf());

        assert_eq!(mount.id(), "some_id");
    }
}
