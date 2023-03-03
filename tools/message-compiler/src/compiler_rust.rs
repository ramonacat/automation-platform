use proc_macro2::TokenStream;
use quote::{format_ident, quote, TokenStreamExt};

use crate::type_checking::{
    TypedEnum, TypedFieldType, TypedFile, TypedMetadata, TypedRpc, TypedStruct,
};

fn generate_header() -> TokenStream {
    quote! {
        #[allow(unused)]
        use async_std::stream::Stream;
        use rpc_support::rpc_error::RpcError;
        use serde::{Deserialize, Serialize};
    }
}

fn generate_metadata(meta: &TypedMetadata) -> TokenStream {
    let mut result = quote!(
        #[derive(Serialize, Deserialize, Debug, Clone)]
    );

    let mut meta_fields = quote! {};
    for f in meta.fields() {
        let name = format_ident!("{}", f.name());
        let ty: syn::Type = syn::parse_str(&to_rust_type(f.type_name())).unwrap();
        meta_fields.append_all(quote!(pub #name: #ty,));
    }
    result.append_all(quote!(
        pub struct Metadata {
            #meta_fields
        }
    ));

    result
}

fn generate_structs(structs: &Vec<TypedStruct>) -> TokenStream {
    let mut result = quote! {};

    for s in structs {
        let struct_name = format_ident!("{}", s.name());
        let mut render_fields = quote! {};
        for f in s.fields() {
            let name = format_ident!("{}", f.name());
            let ty: syn::Type = syn::parse_str(&to_rust_type(f.type_name())).unwrap();
            render_fields.append_all(quote!(pub #name: #ty,));
        }
        result.append_all(quote!(
            #[derive(Serialize, Deserialize, Debug, Clone)]
            pub struct #struct_name {
                #render_fields
            }
        ));
    }

    result
}

/// # Panics
/// TODO make this not panic
#[must_use]
pub fn generate_enums(enums: &Vec<TypedEnum>) -> TokenStream {
    let mut result = quote! {};

    for e in enums {
        let enum_name = format_ident!("{}", e.name());
        let mut render_variants = quote! {};
        for v in e.variants() {
            let variant_name = format_ident!("{}", v.name());
            let mut render_fields = quote! {};
            for f in v.fields() {
                let name = format_ident!("{}", f.name());
                let ty: syn::Type = syn::parse_str(&to_rust_type(f.type_name())).unwrap();
                render_fields.append_all(quote!(#name: #ty,));
            }
            render_variants.append_all(quote!(
                #variant_name {
                    #render_fields
                },
            ));
        }
        result.append_all(quote!(
            #[derive(Serialize, Deserialize, Debug, Clone)]
            pub enum #enum_name {
                #render_variants
            }
        ));
    }

    result
}

fn generate_rpc_trait(rpc: &TypedRpc) -> TokenStream {
    let mut rpc_methods = quote! {};

    for r in rpc.calls() {
        match r {
            crate::type_checking::TypedRpcCall::Stream {
                name,
                request,
                response,
            } => {
                let name = format_ident!("{}", name);
                let request: syn::Type = syn::parse_str(&to_rust_type(request)).unwrap();
                let response: syn::Type = syn::parse_str(&to_rust_type(response)).unwrap();

                rpc_methods.append_all(quote!(
                    async fn #name(
                        &mut self,
                        request: #request,
                        metadata: Metadata,
                    ) -> Result<
                        std::pin::Pin<Box<dyn Stream<Item = Result<#response, RpcError>> + Unpin + Send>>,
                        RpcError,
                    >;
                ));
            }
            crate::type_checking::TypedRpcCall::Unary {
                name,
                request,
                response,
            } => {
                let name = format_ident!("{}", name);
                let request: syn::Type = syn::parse_str(&to_rust_type(request)).unwrap();
                let response: syn::Type = syn::parse_str(&to_rust_type(response)).unwrap();

                rpc_methods.append_all(quote!(
                    async fn #name(
                        &mut self,
                        request: #request,
                        metadata: Metadata,
                    ) -> Result<#response, RpcError>;
                ));
            }
        }
    }

    quote!(
        #[async_trait::async_trait]
        pub trait Rpc {
             #rpc_methods
        }
    )
}

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
        impl<TRpcClient> Rpc for Client<TRpcClient> where TRpcClient: RawRpcClient + Send + Sync {
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
                        let result = rpc.lock().await.#name_ident(serde_json::from_str(&payload_line)?, metadata).await;

                        send_stream_response(&mut write, result, request_id).await?;
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
                        let result = rpc.lock().await.#name_ident(serde_json::from_str(&payload_line)?, metadata).await;

                        send_response(&mut write, result, request_id, false).await?;
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
        use tokio::net::TcpStream;
        use tokio::io::BufReader;
        use rpc_support::send_response;
        #[allow(unused)] use rpc_support::send_stream_response;
        use rpc_support::read_request;

        pub struct Server<TRpc>
        where
            TRpc: Rpc + Send + Sync,
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
            T: Rpc + Send + Sync + 'static,
        {
            /// # Errors
            /// Will return an error when establishing the TCP Listener fails
            pub async fn new(addr: &str, rpc: Arc<Mutex<T>>) -> Result<Self, RpcError> {
                let tcp = Arc::new(Mutex::new(TcpListener::bind(addr).await?));
                Ok(Server { tcp, rpc })
            }

            async fn handle_client(socket: TcpStream, rpc: Arc<Mutex<T>>) -> Result<(), ClientError> {
                let mut socket = socket;
                let (read, mut write) = socket.split();
                let mut reader = BufReader::new(read);

                loop {
                    let (payload_line, method_name, request_id, metadata): (String, String, u64, Metadata) =
                        read_request(&mut reader).await?;

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
                        Self::handle_client(socket, rpc),
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
    use crate::type_checking::TypedField;

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

    #[test]
    pub fn generate_header_test() {
        insta::assert_snapshot!(prettyplease::unparse(
            &syn::parse_file(&generate_header().to_string()).unwrap()
        ));
    }

    #[test]
    pub fn generate_metadata_test() {
        let meta = generate_metadata(&TypedMetadata {
            fields: vec![
                TypedField {
                    name: "foo".to_string(),
                    type_id: TypedFieldType::U8,
                },
                TypedField {
                    name: "bar".to_string(),
                    type_id: TypedFieldType::U64,
                },
            ],
        });

        insta::assert_snapshot!(prettyplease::unparse(
            &syn::parse_file(&meta.to_string()).unwrap()
        ));
    }

    #[test]
    pub fn generate_structs_test() {
        let structs = generate_structs(&vec![
            TypedStruct {
                name: "Foo".to_string(),
                fields: vec![
                    TypedField {
                        name: "foo".to_string(),
                        type_id: TypedFieldType::U8,
                    },
                    TypedField {
                        name: "bar".to_string(),
                        type_id: TypedFieldType::U64,
                    },
                ],
            },
            TypedStruct {
                name: "Bar".to_string(),
                fields: vec![
                    TypedField {
                        name: "foo".to_string(),
                        type_id: TypedFieldType::U8,
                    },
                    TypedField {
                        name: "bar".to_string(),
                        type_id: TypedFieldType::U64,
                    },
                ],
            },
        ]);

        insta::assert_snapshot!(prettyplease::unparse(
            &syn::parse_file(&structs.to_string()).unwrap()
        ));
    }
}
