use proc_macro2::TokenStream;
use quote::{format_ident, quote, TokenStreamExt};

use crate::type_checking::{TypedEnum, TypedEnumVariant, TypedField, TypedStruct};

use super::to_rust_type;

fn generate_fields(fields: &[TypedField], is_pub: bool) -> TokenStream {
    let mut result = quote! {};

    for field in fields {
        let field_name = format_ident!("{}", &field.name);
        let field_type = to_rust_type(&field.type_id);
        let pub_ = if is_pub {
            quote! { pub }
        } else {
            quote! {}
        };

        result.append_all(quote! {
            #pub_ #field_name: #field_type,
        });
    }

    result
}

fn generate_structs(structs: &[TypedStruct]) -> TokenStream {
    let mut result = quote! {};

    for struct_ in structs {
        let struct_name = format_ident!("{}", struct_.name);
        let fields = generate_fields(&struct_.fields, true);

        result.append_all(quote! {
            #[derive(serde::Serialize, serde::Deserialize, Debug, Clone)]
            pub struct #struct_name {
                #fields
            }
        });
    }

    result
}

fn generate_enum_variants(variants: &[TypedEnumVariant]) -> TokenStream {
    let mut result = quote! {};

    for variant in variants {
        let variant_name = format_ident!("{}", variant.name);
        let fields = generate_fields(&variant.fields, false);

        result.append_all(quote! {
            #variant_name { #fields },
        });
    }

    result
}

fn generate_enums(enums: &[TypedEnum]) -> TokenStream {
    let mut result = quote! {};

    for enum_ in enums {
        let enum_name = format_ident!("{}", enum_.name);
        let variants = generate_enum_variants(&enum_.variants);

        result.append_all(quote! {
            #[derive(serde::Serialize, serde::Deserialize, Debug, Clone)]
            pub enum #enum_name {
                #variants
            }
        });
    }

    result
}

pub fn generate(structs: &[TypedStruct], enums: &[TypedEnum]) -> TokenStream {
    let mut result = quote! {};

    result.append_all(generate_structs(structs));
    result.append_all(generate_enums(enums));

    result
}
