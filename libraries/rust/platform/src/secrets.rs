use std::path::PathBuf;
use thiserror::Error;

pub struct Secret {
    username: String,
    password: String,
}

impl Secret {
    #[must_use]
    pub fn username(&self) -> &str {
        &self.username
    }

    #[must_use]
    pub fn password(&self) -> &str {
        &self.password
    }
}

pub struct SecretProvider<'a> {
    base_path: &'a str,
}

#[derive(Error, Debug)]
pub enum Error {
    #[error("Failed to read file")]
    Io(#[from] std::io::Error),
}

impl<'a> SecretProvider<'a> {
    #[must_use]
    pub const fn new(base_path: &'a str) -> Self {
        Self { base_path }
    }

    /// # Errors
    /// Will return an error if the secret does not exist or is not readable
    pub fn read(&self, name: &str) -> Result<Secret, Error> {
        let mut pathbuf = PathBuf::new();
        pathbuf.push(self.base_path);
        pathbuf.push(name);

        let username = std::fs::read_to_string(pathbuf.as_path().join("username"))?;
        let password = std::fs::read_to_string(pathbuf.as_path().join("password"))?;

        Ok(Secret { username, password })
    }
}
