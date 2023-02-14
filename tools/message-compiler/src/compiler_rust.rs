use quote::{format_ident, quote, TokenStreamExt};

use crate::type_checking::{TypedFieldType, TypedFile};

#[must_use]
#[allow(clippy::too_many_lines)] // TODO split into smaller functions
/// # Panics
/// TODO make this not panic
pub fn compile(file: TypedFile) -> String {
    let TypedFile {
        structs,
        meta,
        rpc,
        enums,
    } = file;

    let mut result = quote! {
        #[allow(unused)]
        use async_std::stream::Stream;
        use rpc_support::rpc_error::RpcError;
        use serde::{Deserialize, Serialize};
    };

    result.append_all(quote!(
        #[derive(Serialize, Deserialize, Debug, Clone)]
    ));

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

    result.append_all(quote!(
        #[async_trait::async_trait]
        pub trait Rpc {
             #rpc_methods
        }
    ));

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
