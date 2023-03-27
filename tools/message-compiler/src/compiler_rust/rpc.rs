use proc_macro2::TokenStream;
use quote::{format_ident, quote, TokenStreamExt};
use syn::{Ident, Type};

use crate::{
    compiler_rust::to_rust_type,
    type_checking::{TypedReverseRpc, TypedRpc, TypedRpcCall},
};

fn generate_responder_rpc(raw_calls: &[TypedRpcCall], other_side_type: &Type) -> TokenStream {
    let mut calls = quote!();

    for call in raw_calls {
        let call_compiled = match call {
            TypedRpcCall::Unary {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request_type = to_rust_type(request);
                let response_type = to_rust_type(response);

                quote! {
                    async fn #name_ident(&self, request: #request_type, other_side: std::sync::Arc<dyn #other_side_type>) -> Result<#response_type, Error>;
                }
            }
            TypedRpcCall::Stream {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request_type = to_rust_type(request);
                let response_type = to_rust_type(response);

                quote! {
                    async fn #name_ident(&self, request: #request_type, other_side: std::sync::Arc<dyn #other_side_type>) -> Result<Box<dyn futures::Stream<Item = Result<#response_type, Error>> + Send + Sync + 'static>, rpc_support::connection::Error>;
                }
            }
        };

        calls.append_all(call_compiled);
    }

    calls
}

fn generate_requester_rpc(raw_calls: &[TypedRpcCall]) -> TokenStream {
    let mut calls = quote!();

    for call in raw_calls {
        let call_compiled = match call {
            TypedRpcCall::Unary {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request_type = to_rust_type(request);
                let response_type = to_rust_type(response);

                quote! {
                    async fn #name_ident(&self, request: #request_type) -> Result<Result<#response_type, Error>, rpc_support::connection::Error>;
                }
            }
            TypedRpcCall::Stream {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request_type = to_rust_type(request);
                let response_type = to_rust_type(response);

                quote! {
                    async fn #name_ident(&self, request: #request_type) -> Result<Box<dyn futures::Stream<Item = Result<#response_type, Error>> + Send + Sync + 'static>, rpc_support::connection::Error>;
                }
            }
        };

        calls.append_all(call_compiled);
    }

    calls
}

fn generate_request_dispatcher(
    raw_calls: &[TypedRpcCall],
    name: &Ident,
    responder_type: &Type,
    other_side_requester_type: &Type,
) -> TokenStream {
    let mut method_cases = quote! {};

    for call in raw_calls {
        let compiled_case = match call {
            TypedRpcCall::Unary {
                name,
                request: _,
                response: _,
            } => {
                let name_ident = format_ident!("{}", name);

                quote! {
                    #name => {
                        let response = self.implementation.#name_ident(serde_json::from_slice(&request.data).unwrap(), self.other_side.clone()).await;

                        let stream = futures::stream::once(async move {match response {
                            Ok(ok) => rpc_support::wire_format::Frame::ResponseOk(rpc_support::wire_format::ResponseOk {
                                request_id: request.id,
                                // TODO: error handling
                                data: serde_json::to_vec(&ok).unwrap()
                            }),
                            Err(_err) => rpc_support::wire_format::Frame::ResponseError(rpc_support::wire_format::ResponseError {
                                request_id: request.id,
                                // TODO: no unwrap
                                // TODO: actually maybe pass the error lol
                                data: serde_json::to_vec(&Error {}).unwrap()
                            })
                        }});

                        Box::new(stream)
                    }
                }
            }
            TypedRpcCall::Stream {
                name,
                request: _,
                response: _,
            } => {
                let name_ident = format_ident!("{}", name);

                quote! {
                    #name => {
                        let response = self.implementation.#name_ident(serde_json::from_slice(&request.data).unwrap(), self.other_side.clone()).await;

                        match response {
                            // TODO no unwrap
                            Ok(ok) => {
                                let mut ok = Box::into_pin(ok);

                                Box::new(async_stream::stream!{
                                    while let Some(item) = ok.next().await {
                                        yield rpc_support::wire_format::Frame::ResponseOk(rpc_support::wire_format::ResponseOk { data: serde_json::to_vec(&item.unwrap()).unwrap(), request_id: request.id })
                                    }
                                    yield rpc_support::wire_format::Frame::ResponseEndStream(rpc_support::wire_format::ResponseEndStream { request_id: request.id })
                                })
                            },
                            Err(_err) => Box::new(futures::stream::once(async move {rpc_support::wire_format::Frame::ResponseError(rpc_support::wire_format::ResponseError {
                                request_id: request.id,
                                // TODO: no unwrap
                                // TODO: actually maybe pass the error lol
                                data: serde_json::to_vec(&Error {}).unwrap()
                            })}))
                        }
                    }
                }
            }
        };

        method_cases.append_all(compiled_case);
    }

    quote! {
        pub struct #name<TOtherSide: #other_side_requester_type> {
            pub implementation: std::sync::Arc<dyn #responder_type>,
            pub other_side: std::sync::Arc<TOtherSide>
        }

        #[async_trait::async_trait]
        impl<TOtherSide: #other_side_requester_type> rpc_support::connection::RequestDispatcher for #name<TOtherSide> {
            #[allow(clippy::too_many_lines, clippy::match_single_binding)]
            async fn dispatch(&self, request: rpc_support::wire_format::Request) -> Box<dyn futures::Stream<Item = rpc_support::wire_format::Frame> + Send + Sync> {
                match request.method_name.as_str() {
                    #method_cases
                    _ => todo!("Return an error, method not found...")
                }
            }
        }

    }
}

fn generate_requester(
    calls: &[TypedRpcCall],
    name: &Ident,
    rpc_to_implement: &Type,
) -> TokenStream {
    let mut methods = quote! {};

    for call in calls {
        let compiled_call = match call {
            TypedRpcCall::Unary {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request_type = to_rust_type(request);
                let response_type = to_rust_type(response);

                quote! {
                    async fn #name_ident(&self, request: #request_type) -> Result<Result<#response_type, Error>, rpc_support::connection::Error> {
                        self.request_sender.send_request(#name.to_string(), request).await
                    }
                }
            }
            TypedRpcCall::Stream {
                name,
                request,
                response,
            } => {
                let name_ident = format_ident!("{}", name);
                let request_type = to_rust_type(request);
                let response_type = to_rust_type(response);

                quote! {
                    async fn #name_ident(&self, request: #request_type) -> Result<Box<dyn futures::Stream<Item = Result<#response_type, Error>> + Send + Sync + 'static>, rpc_support::connection::Error> {
                        self.request_sender.send_stream_request(#name.to_string(), request).await
                    }
                }
            }
        };

        methods.append_all(compiled_call);
    }

    quote! {
        pub struct #name {
            pub request_sender: std::sync::Arc<rpc_support::connection::RequestSender>
        }

        #[async_trait::async_trait]
        impl #rpc_to_implement for #name {
            #methods
        }
    }
}

pub fn generate(rpc: &TypedRpc, reverse_rpc: &TypedReverseRpc) -> TokenStream {
    let responder_rpc =
        generate_responder_rpc(&rpc.calls, &syn::parse_str("RequesterReverseRpc").unwrap());
    let requester_rpc = generate_requester_rpc(&rpc.calls);
    let responder_reverse_rpc =
        generate_responder_rpc(&reverse_rpc.calls, &syn::parse_str("RequesterRpc").unwrap());
    let requester_reverse_rpc = generate_requester_rpc(&reverse_rpc.calls);

    let request_dispatcher = generate_request_dispatcher(
        &rpc.calls,
        &format_ident!("RpcDispatcher"),
        &syn::parse_str("ResponderRpc").unwrap(),
        &syn::parse_str("RequesterReverseRpc").unwrap(),
    );

    let reverse_request_dispatcher = generate_request_dispatcher(
        &reverse_rpc.calls,
        &format_ident!("ReverseRpcDispatcher"),
        &syn::parse_str("ResponderReverseRpc").unwrap(),
        &syn::parse_str("RequesterRpc").unwrap(),
    );

    let requester = generate_requester(
        &rpc.calls,
        &format_ident!("Requester"),
        &syn::parse_str("RequesterRpc").unwrap(),
    );
    let reverse_requester = generate_requester(
        &reverse_rpc.calls,
        &format_ident!("ReverseRequester"),
        &syn::parse_str("RequesterReverseRpc").unwrap(),
    );

    quote! {
        #[derive(thiserror::Error, Debug, serde::Serialize, serde::Deserialize)]
        pub struct Error {}

        impl std::fmt::Display for Error {
            fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
                write!(f, "TODO")
            }
        }

        #[async_trait::async_trait]
        pub trait ResponderRpc : Send + Sync + 'static {
            #responder_rpc
        }

        #[async_trait::async_trait]
        pub trait RequesterRpc : Send + Sync + 'static {
            #requester_rpc
        }

        #[async_trait::async_trait]
        pub trait ResponderReverseRpc : Send + Sync + 'static {
            #responder_reverse_rpc
        }

        #[async_trait::async_trait]
        pub trait RequesterReverseRpc : Send + Sync + 'static {
            #requester_reverse_rpc
        }

        #request_dispatcher
        #reverse_request_dispatcher

        #requester
        #reverse_requester
    }
}
