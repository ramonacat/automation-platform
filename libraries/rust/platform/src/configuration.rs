use jsonpath_rust::JsonPathQuery;
use serde_json::Value;
use thiserror::Error;

pub struct Configuration {
    data: Value,
}

#[derive(Debug, Error)]
pub enum Error {
    #[error("IO Error: {0}")]
    IoError(#[from] std::io::Error),
    #[error("JSON Error: {0}")]
    JsonError(#[from] serde_json::Error),
    #[error("JSON Path Error: {0}")]
    JsonPath(String),
    #[error("Invalid value type")]
    InvalidValueType,
}

impl Configuration {
    /// # Errors
    /// Will return errors if the configuration file cannot be read or is not valid JSON
    pub fn new() -> Result<Self, Error> {
        let data: Value = serde_json::from_str(&std::fs::read_to_string(
            "/etc/ap/runtime.configuration.json",
        )?)?;

        Ok(Self { data })
    }

    /// # Errors
    /// Will return errors if the structure of the config file is not correct
    pub fn get_string(&self, path: &str) -> Result<String, Error> {
        let value = self.data.clone().path(path).map_err(Error::JsonPath)?;

        Ok(value
            .as_array()
            .ok_or(Error::InvalidValueType)?
            .first() // todo assert len=1
            .ok_or(Error::InvalidValueType)?
            .as_str()
            .ok_or(Error::InvalidValueType)?
            .to_string())
    }
}
