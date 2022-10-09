use crate::type_checking::{TypedField, TypedFieldType, TypedFile};

#[must_use]
pub fn compile(file: TypedFile) -> String {
    let TypedFile {
        structs,
        meta,
        rpc,
        enums,
    } = file;

    let mut result = String::new();

    result += "#[allow(unused)]\nuse async_std::stream::Stream;\n";
    result += "use rpc_support::rpc_error::RpcError;\n";
    result += "use serde::{Deserialize, Serialize};\n";

    result += "#[derive(Serialize, Deserialize, Debug, Clone)]\n";
    if meta.fields().is_empty() {
        result += "pub struct Metadata {}\n";
    } else {
        result += "pub struct Metadata {\n";
        result += &render_fields(meta.fields(), true, 1);
        result += "}\n";
    }

    for s in structs {
        result += "#[derive(Serialize, Deserialize, Debug, Clone)]\n";
        result += &format!("pub struct {} {{\n", s.name());
        result += &render_fields(s.fields(), true, 1);
        result += "}\n";
    }

    for e in enums {
        result += "#[derive(Serialize, Deserialize, Debug, Clone)]\n";
        result += &format!("pub enum {} {{\n", e.name());
        for v in e.variants() {
            result += "    ";
            result += v.name();
            result += " {\n";
            result += &render_fields(v.fields(), false, 2);
            result += "    },\n";
        }
        result += "}\n";
    }

    result += "\n";

    result += "#[async_trait::async_trait]\n";
    result += "pub trait Rpc {\n";

    for r in rpc.calls() {
        result += &format!(
            r#"    async fn {}(
        &mut self,
        request: {},
        metadata: Metadata,
    ) -> {};
"#,
            r.name(),
            to_rust_type(r.request()),
            if r.is_stream() {
                format!(
                    // {}
                    "Result<\n        std::pin::Pin<Box<dyn Stream<Item = Result<{}, RpcError>> + Unpin + Send>>,\n        RpcError,\n    >",
                    to_rust_type(r.response())
                )
            } else {
                format!("Result<{}, RpcError>", to_rust_type(r.response()))
            }
        );
    }
    result += "}\n";

    result
}

fn render_fields(fields: &[TypedField], public: bool, depth: usize) -> String {
    let mut result = String::new();
    let indent = (0..(depth * 4)).map(|_| " ").collect::<String>();

    for f in fields {
        if let TypedFieldType::Instant = f.type_name() {
            result += &indent;
            result += "#[serde(with = \"rpc_support::system_time_serializer\")]\n";
        }
        result += &format!(
            "{}{}{}: {},\n",
            indent,
            if public { "pub " } else { "" },
            f.name(),
            to_rust_type(f.type_name())
        );
    }

    result
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
        TypedFieldType::Guid => "::uuid::Uuid".to_string(),
        TypedFieldType::String => "String".to_string(),
        TypedFieldType::Void => "()".to_string(),
        TypedFieldType::OtherStruct(name) | TypedFieldType::Enum(name) => name.clone(),
        TypedFieldType::Optional(type_) => format!("Option<{}>", to_rust_type(type_)),
    }
}
