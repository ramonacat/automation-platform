use serde::de::{Error, Visitor};
use serde::ser::Error as SerError;
use serde::{Deserializer, Serializer};
use std::fmt::Formatter;
use std::ops::Add;
use std::time::{Duration, SystemTime};

struct TimestampVisitor;

impl<'de> Visitor<'de> for TimestampVisitor {
    type Value = u64;

    fn expecting(&self, formatter: &mut Formatter) -> std::fmt::Result {
        write!(formatter, "u64")
    }

    fn visit_u64<E>(self, v: u64) -> Result<Self::Value, E>
    where
        E: Error,
    {
        Ok(v)
    }
}

/// # Errors
/// Can fail if the `SystemTime` was before `UNIX_EPOCH`
pub fn serialize<S>(val: &SystemTime, ser: S) -> Result<S::Ok, S::Error>
where
    S: Serializer,
{
    ser.serialize_u64(
        val.duration_since(SystemTime::UNIX_EPOCH)
            .map_err(|_| S::Error::custom("The timestamp was before Unix Epoch?"))?
            .as_secs(),
    )
}

/// # Errors
/// Can fail if the value is not `u64`
pub fn deserialize<'de, D>(des: D) -> Result<SystemTime, D::Error>
where
    D: Deserializer<'de>,
{
    Ok(SystemTime::UNIX_EPOCH.add(Duration::from_secs(des.deserialize_u64(TimestampVisitor)?)))
}
