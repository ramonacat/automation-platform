use quote::{quote, TokenStreamExt};

use crate::type_checking::{TypedFieldType, TypedFile};

mod client;
mod data;
mod rpc;
mod server;

#[must_use]
/// # Panics
/// TODO make this not panic
pub fn compile(file: TypedFile) -> String {
    let TypedFile {
        structs,
        enums,
        reverse_rpc,
        rpc,
    } = file;

    let mut result = quote! {};

    result.append_all(quote! {
        #[allow(unused)] use futures::StreamExt;
    });
    result.append_all(data::generate(&structs, &enums));
    result.append_all(rpc::generate(&rpc, &reverse_rpc));
    result.append_all(server::generate());
    result.append_all(client::generate());

    // result.to_string()
    prettyplease::unparse(&syn::parse_file(&result.to_string()).unwrap())
}

fn to_rust_type_name(type_: &TypedFieldType) -> String {
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
        TypedFieldType::Optional(type_) => format!("Option<{}>", to_rust_type_name(type_)),
        TypedFieldType::Array(type_) => format!("Vec<{}>", to_rust_type_name(type_)),
    }
}

fn to_rust_type(type_: &TypedFieldType) -> syn::Type {
    syn::parse_str(to_rust_type_name(type_).as_str()).unwrap()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    pub fn to_rust_type_tests() {
        assert_eq!(to_rust_type_name(&TypedFieldType::U8), "u8");
        assert_eq!(to_rust_type_name(&TypedFieldType::U16), "u16");
        assert_eq!(to_rust_type_name(&TypedFieldType::U32), "u32");
        assert_eq!(to_rust_type_name(&TypedFieldType::U64), "u64");
        assert_eq!(to_rust_type_name(&TypedFieldType::S8), "s8");
        assert_eq!(to_rust_type_name(&TypedFieldType::S16), "s16");
        assert_eq!(to_rust_type_name(&TypedFieldType::S32), "s32");
        assert_eq!(to_rust_type_name(&TypedFieldType::S64), "s64");
        assert_eq!(
            to_rust_type_name(&TypedFieldType::Instant),
            "std::time::SystemTime"
        );
        assert_eq!(to_rust_type_name(&TypedFieldType::Guid), "uuid::Uuid");
        assert_eq!(to_rust_type_name(&TypedFieldType::String), "String");
        assert_eq!(to_rust_type_name(&TypedFieldType::Void), "()");
        assert_eq!(to_rust_type_name(&TypedFieldType::Binary), "Vec<u8>");
        assert_eq!(
            to_rust_type_name(&TypedFieldType::OtherStruct("Foo".to_string())),
            "Foo"
        );
        assert_eq!(
            to_rust_type_name(&TypedFieldType::Enum("Foo".to_string())),
            "Foo"
        );
        assert_eq!(
            to_rust_type_name(&TypedFieldType::Optional(Box::new(TypedFieldType::U8))),
            "Option<u8>"
        );
        assert_eq!(
            to_rust_type_name(&TypedFieldType::Array(Box::new(TypedFieldType::U8))),
            "Vec<u8>"
        );
    }
}
