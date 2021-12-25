mod structs;
pub use structs::*;

pub mod system_time_serializer {
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

    pub fn deserialize<'de, D>(des: D) -> Result<SystemTime, D::Error>
    where
        D: Deserializer<'de>,
    {
        Ok(SystemTime::UNIX_EPOCH.add(Duration::from_secs(des.deserialize_u64(TimestampVisitor)?)))
    }
}

#[cfg(test)]
mod test {
    use crate::{MessagePayload, Metadata, FileOnMountPath};
    use std::ops::Add;
    use std::time::{Duration, SystemTime};
    use uuid::Uuid;

    #[test]
    pub fn test_can_serialize_henlo() {
        let message = crate::Message {
            metadata: Metadata {
                id: Uuid::parse_str("936DA01F9ABD4d9d80C702AF85C822A8").unwrap(),
                created_time: SystemTime::UNIX_EPOCH.add(Duration::from_secs(1024)),
                source: "test".to_string()
            },
            payload: MessagePayload::FileCreated { path: FileOnMountPath { path: "a/b/c.txt".to_string(), mount_id: "test1".to_string() } },
        };

        let json = serde_json::to_string(&message).unwrap();

        assert_eq!("{\"metadata\":{\"source\":\"test\",\"id\":\"936da01f-9abd-4d9d-80c7-02af85c822a8\",\"created_time\":1024},\"payload\":{\"type\":\"FileCreated\",\"path\":{\"path\":\"a/b/c.txt\",\"mount_id\":\"test1\"}}}", json);
    }
}
