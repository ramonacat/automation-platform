use proc_macro2::TokenStream;
use quote::{format_ident, quote, TokenStreamExt};

use crate::type_checking::{TypedEnum, TypedMetadata, TypedRpc, TypedRpcCall, TypedStruct};

use super::to_rust_type;

pub(crate) fn generate_header() -> TokenStream {
    quote! {
        #[allow(unused)]
        use futures::stream::Stream;
        use rpc_support::rpc_error::RpcError;
        use serde::{Deserialize, Serialize};
    }
}

pub(crate) fn generate_metadata(meta: &TypedMetadata) -> TokenStream {
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

pub(crate) fn generate_structs(structs: &Vec<TypedStruct>) -> TokenStream {
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
pub(crate) fn generate_enums(enums: &Vec<TypedEnum>) -> TokenStream {
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

fn generate_rpc_methods(call: &TypedRpcCall, client: bool) -> TokenStream {
    let client_param = if client {
        quote!()
    } else {
        quote!(client: std::sync::Weak<tokio::sync::Mutex<dyn rpc_support::Client>>)
    };

    match call {
        crate::type_checking::TypedRpcCall::Stream {
            name,
            request,
            response,
        } => {
            let name = format_ident!("{}", name);
            let request: syn::Type = syn::parse_str(&to_rust_type(request)).unwrap();
            let response: syn::Type = syn::parse_str(&to_rust_type(response)).unwrap();

            quote!(
                async fn #name(
                    &mut self,
                    request: #request,
                    metadata: Metadata,
                    #client_param
                ) -> Result<
                    std::pin::Pin<Box<dyn Stream<Item = Result<#response, RpcError>> + Unpin + Send>>,
                    RpcError,
                >;
            )
        }
        crate::type_checking::TypedRpcCall::Unary {
            name,
            request,
            response,
        } => {
            let name = format_ident!("{}", name);
            let request: syn::Type = syn::parse_str(&to_rust_type(request)).unwrap();
            let response: syn::Type = syn::parse_str(&to_rust_type(response)).unwrap();

            quote!(
                async fn #name(
                    &mut self,
                    request: #request,
                    metadata: Metadata,
                    #client_param
                ) -> Result<#response, RpcError>;
            )
        }
    }
}

pub(crate) fn generate_rpc_trait(rpc: &TypedRpc) -> TokenStream {
    let mut server_rpc_methods = quote! {};

    for r in rpc.calls() {
        server_rpc_methods.append_all(generate_rpc_methods(r, false));
    }

    let mut client_rpc_methods = quote! {};

    for r in rpc.calls() {
        client_rpc_methods.append_all(generate_rpc_methods(r, true));
    }

    quote!(
        #[async_trait::async_trait]
        pub trait RpcServer {
             #server_rpc_methods
        }

        #[async_trait::async_trait]
        pub trait RpcClient {
             #client_rpc_methods
        }
    )
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::type_checking::{
        TypedEnum, TypedEnumVariant, TypedField, TypedFieldType, TypedMetadata, TypedStruct,
    };

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

    #[test]
    pub fn generate_enums_test() {
        let enums = generate_enums(&vec![TypedEnum {
            name: "Something".to_string(),
            variants: vec![
                TypedEnumVariant {
                    name: "A".to_string(),
                    fields: vec![TypedField {
                        name: "field_a".to_string(),
                        type_id: TypedFieldType::String,
                    }],
                },
                TypedEnumVariant {
                    name: "B".to_string(),
                    fields: vec![
                        TypedField {
                            name: "a".to_string(),
                            type_id: TypedFieldType::S16,
                        },
                        TypedField {
                            name: "b".to_string(),
                            type_id: TypedFieldType::String,
                        },
                    ],
                },
            ],
        }]);

        insta::assert_snapshot!(prettyplease::unparse(
            &syn::parse_file(&enums.to_string()).unwrap()
        ));
    }
}
