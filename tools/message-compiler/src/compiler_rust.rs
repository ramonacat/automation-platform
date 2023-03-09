use proc_macro2::TokenStream;
use quote::{format_ident, quote, TokenStreamExt};

use crate::type_checking::{TypedFieldType, TypedFile, TypedRpc};

mod traits;

use traits::{
    generate_enums, generate_header, generate_metadata, generate_rpc_trait, generate_structs,
};

fn generate_rpc_client(rpc: &TypedRpc) -> TokenStream {
    let mut result = quote! {
        #[allow(unused)] use rpc_support::RawRpcClient;
        #[allow(unused)] use std::sync::atomic::{AtomicU64, Ordering};
        #[allow(unused)] use std::pin::Pin;

        pub struct Client<TRpcClient> where TRpcClient: RawRpcClient + Send + Sync {
            id: AtomicU64,
            raw: TRpcClient,
        }
    };

    result.append_all(quote! {
        impl<TRpcClient> Client<TRpcClient> where TRpcClient: RawRpcClient + Send + Sync {
            pub fn new(raw: TRpcClient) -> Self {
                Self {
                    id: AtomicU64::new(0),
                    raw,
                }
            }
        }
    });

    let mut rpc_methods = quote!();

    for method in &rpc.calls {
        let method_impl = match method {
            crate::type_checking::TypedRpcCall::Stream {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request: syn::Type = syn::parse_str(&to_rust_type(request)).unwrap();
                let response: syn::Type = syn::parse_str(&to_rust_type(response)).unwrap();

                quote! {
                    async fn #name_ident(
                        &mut self,
                        request: #request,
                        metadata: Metadata,
                    ) -> Result<Pin<Box<dyn Stream<Item = Result<#response, RpcError>> + Unpin + Send>>, RpcError> {
                        self.raw
                            .send_rpc_stream_request(
                                self.id.fetch_add(1, Ordering::AcqRel),
                                #name,
                                &request,
                                &metadata,
                            )
                            .await
                    }
                }
            }
            crate::type_checking::TypedRpcCall::Unary {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request: syn::Type = syn::parse_str(&to_rust_type(request)).unwrap();
                let response: syn::Type = syn::parse_str(&to_rust_type(response)).unwrap();

                quote! {
                    async fn #name_ident(&mut self, request: #request, metadata: Metadata) -> Result<#response, RpcError> {
                        self.raw
                            .send_rpc(
                                self.id.fetch_add(1, Ordering::AcqRel),
                                #name,
                                &request,
                                &metadata,
                            )
                            .await
                    }
                }
            }
        };

        rpc_methods.append_all(method_impl);
    }

    result.append_all(quote! {
        #[async_trait::async_trait]
        impl<TRpcClient> RpcClient for Client<TRpcClient> where TRpcClient: RawRpcClient + Send + Sync {
            #rpc_methods
        }
    });

    result
}

fn generate_rpc_server_method_match(rpc: &TypedRpc) -> TokenStream {
    let mut method_cases = quote! {};

    for method in &rpc.calls {
        let case = match method {
            crate::type_checking::TypedRpcCall::Stream {
                name,
                request: _,
                response: _,
            } => {
                let name_ident = format_ident!("{}", name);

                quote! {
                    #name => {
                        let result = rpc.lock().await.#name_ident(serde_json::from_str(&payload_line)?, metadata, Arc::downgrade(&client)).await;

                        send_stream_response(client.clone(), result, request_id).await?;
                    }
                }
            }
            crate::type_checking::TypedRpcCall::Unary {
                name,
                request: _,
                response: _,
            } => {
                let name_ident = format_ident!("{}", name);

                quote! {
                    #name => {
                        let result = rpc.lock().await.#name_ident(serde_json::from_str(&payload_line)?, metadata, Arc::downgrade(&client)).await;

                        send_response(client.clone(), result, request_id, false).await?;
                    }
                }
            }
        };
        method_cases.append_all(case);
    }

    quote! {
        match method_name.as_str() {
            #method_cases

            // fixme do not panic here!
            _ => panic!("Unknown method name: {method_name}"),
        }
    }
}

fn generate_rpc_server(rpc: &TypedRpc) -> TokenStream {
    let method_match = generate_rpc_server_method_match(rpc);

    let result = quote! {
        use tracing::info;
        use thiserror::Error;
        use std::sync::Arc;
        use tokio::sync::Mutex;
        use tokio::net::TcpListener;
        use rpc_support::send_response;
        #[allow(unused)] use rpc_support::send_stream_response;
        use rpc_support::read_request;

        pub struct Server<TRpc>
        where
            TRpc: RpcServer + Send + Sync,
        {
            tcp: Arc<Mutex<TcpListener>>,
            rpc: Arc<Mutex<TRpc>>,
        }

        #[derive(Debug, Error)]
        pub enum ClientError {
            #[error("{0}")]
            IoError(#[from] tokio::io::Error),
            #[error("{0}")]
            JsonError(#[from] serde_json::Error),
            #[error("{0}")]
            RpcError(#[from] RpcError),
        }

        #[derive(Debug, Error)]
        pub enum RunError {
            #[error("{0}")]
            IoError(#[from] tokio::io::Error),
        }

        impl<T> Server<T>
        where
            T: RpcServer + Send + Sync + 'static,
        {
            /// # Errors
            /// Will return an error when establishing the TCP Listener fails
            pub async fn new(addr: &str, rpc: Arc<Mutex<T>>) -> Result<Self, RpcError> {
                let tcp = Arc::new(Mutex::new(TcpListener::bind(addr).await?));
                Ok(Server { tcp, rpc })
            }

            async fn handle_client(client: Arc<Mutex<dyn rpc_support::Client>>, rpc: Arc<Mutex<T>>) -> Result<(), ClientError> {
                loop {
                    let (payload_line, method_name, request_id, metadata): (String, String, u64, Metadata) =
                        read_request(client.clone()).await?;

                    #method_match
                }
            }

            /// # Errors
            /// Will return an error if the connection fails
            pub async fn run(self) -> Result<(), RunError> {
                loop {
                    let (socket, address) = self.tcp.lock().await.accept().await?;
                    info!("New client connected: {}", address);

                    let rpc = self.rpc.clone();

                    tokio::spawn(platform::async_infra::run_with_error_handling(
                        Self::handle_client(Arc::new(Mutex::new(rpc_support::DefaultClient::new(socket))), rpc),
                    ));
                }
            }
        }
    };

    result
}

#[must_use]
/// # Panics
/// TODO make this not panic
pub fn compile(file: TypedFile) -> String {
    let TypedFile {
        structs,
        meta,
        rpc,
        enums,
    } = file;

    let mut result = generate_header();

    result.append_all(generate_metadata(&meta));
    result.append_all(generate_structs(&structs));
    result.append_all(generate_enums(&enums));
    result.append_all(generate_rpc_trait(&rpc));

    result.append_all(generate_rpc_client(&rpc));
    result.append_all(generate_rpc_server(&rpc));

    prettyplease::unparse(&syn::parse_file(&result.to_string()).unwrap())
}

fn to_rust_type(type_: &TypedFieldType) -> String {
    match type_ {
        TypedFieldType::U8 => "u8".to_string(),
        TypedFieldType::U16 => "u16".to_string(),
        TypedFieldType::U32 => "u32".to_string(),
        TypedFieldType::U64 => "u64".to_string(),
        TypedFieldType::S8 => "s8".to_string(),
        TypedFieldType::S16 => "s16".to_string(),
        TypedFieldType::S32 => "s32".to_string(),
        TypedFieldType::S64 => "s64".to_string(),
        TypedFieldType::Instant => "std::time::SystemTime".to_string(),
        TypedFieldType::Guid => "uuid::Uuid".to_string(),
        TypedFieldType::String => "String".to_string(),
        TypedFieldType::Void => "()".to_string(),
        TypedFieldType::Binary => "Vec<u8>".to_string(),
        TypedFieldType::OtherStruct(name) | TypedFieldType::Enum(name) => name.clone(),
        TypedFieldType::Optional(type_) => format!("Option<{}>", to_rust_type(type_)),
        TypedFieldType::Array(type_) => format!("Vec<{}>", to_rust_type(type_)),
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    pub fn to_rust_type_tests() {
        assert_eq!(to_rust_type(&TypedFieldType::U8), "u8");
        assert_eq!(to_rust_type(&TypedFieldType::U16), "u16");
        assert_eq!(to_rust_type(&TypedFieldType::U32), "u32");
        assert_eq!(to_rust_type(&TypedFieldType::U64), "u64");
        assert_eq!(to_rust_type(&TypedFieldType::S8), "s8");
        assert_eq!(to_rust_type(&TypedFieldType::S16), "s16");
        assert_eq!(to_rust_type(&TypedFieldType::S32), "s32");
        assert_eq!(to_rust_type(&TypedFieldType::S64), "s64");
        assert_eq!(
            to_rust_type(&TypedFieldType::Instant),
            "std::time::SystemTime"
        );
        assert_eq!(to_rust_type(&TypedFieldType::Guid), "uuid::Uuid");
        assert_eq!(to_rust_type(&TypedFieldType::String), "String");
        assert_eq!(to_rust_type(&TypedFieldType::Void), "()");
        assert_eq!(to_rust_type(&TypedFieldType::Binary), "Vec<u8>");
        assert_eq!(
            to_rust_type(&TypedFieldType::OtherStruct("Foo".to_string())),
            "Foo"
        );
        assert_eq!(
            to_rust_type(&TypedFieldType::Enum("Foo".to_string())),
            "Foo"
        );
        assert_eq!(
            to_rust_type(&TypedFieldType::Optional(Box::new(TypedFieldType::U8))),
            "Option<u8>"
        );
        assert_eq!(
            to_rust_type(&TypedFieldType::Array(Box::new(TypedFieldType::U8))),
            "Vec<u8>"
        );
    }
}
