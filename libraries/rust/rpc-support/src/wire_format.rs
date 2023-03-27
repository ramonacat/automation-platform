use std::fmt::Display;

use serde::{Deserialize, Serialize};

#[derive(Serialize, Deserialize, Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct RequestId(u64);

impl RequestId {
    #[must_use]
    pub fn new(value: u64) -> Self {
        Self(value)
    }
}

impl Display for RequestId {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        write!(f, "(request {})", self.0)
    }
}

#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct Request {
    pub id: RequestId,
    pub method_name: String,
    pub data: Vec<u8>,
}

#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct ResponseOk {
    pub request_id: RequestId,
    pub data: Vec<u8>,
}

#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct ResponseError {
    pub request_id: RequestId,
    pub data: Vec<u8>,
}

#[derive(Serialize, Deserialize, Debug, Clone)]
pub struct ResponseEndStream {
    pub request_id: RequestId,
}

#[derive(Serialize, Deserialize, Debug, Clone)]
pub enum Frame {
    Request(Request),
    ResponseOk(ResponseOk),
    ResponseError(ResponseError),
    ResponseEndStream(ResponseEndStream),
}
