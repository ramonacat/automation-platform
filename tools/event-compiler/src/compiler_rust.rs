use crate::type_checking::{TypedField, TypedFieldType, TypedFile};

#[must_use]
pub fn compile(file: TypedFile) -> String {
    let TypedFile {
        structs,
        messages,
        meta,
    } = file;

    let mut result = String::new();

    result += "use serde::{Deserialize, Serialize};\n";

    result += "#[derive(Serialize, Deserialize, Debug)]\n";
    result += "pub struct Metadata {\n";
    result += &render_fields(meta.fields(), true, 1);
    result += "}\n";

    for s in structs {
        result += "#[derive(Serialize, Deserialize, Debug)]\n";
        result += &format!("pub struct {} {{\n", s.name());
        result += &render_fields(s.fields(), true, 1);
        result += "}\n";
    }

    result += "\n";

    result += "#[derive(Serialize, Deserialize, Debug)]\n";
    result += "#[serde(tag = \"type\")]\n";
    result += "pub enum MessagePayload {\n";
    for m in messages {
        result += &format!("    {} {{\n", m.name());
        result += &render_fields(m.fields(), false, 2);
        result += "    },\n";
    }

    result += "}\n";

    result += "#[derive(Serialize, Deserialize, Debug)]\n";
    result += "pub struct Message {\n";
    result += "    pub metadata: Metadata,\n";
    result += "    pub payload: MessagePayload,\n";
    result += "}\n";

    result
}

fn render_fields(fields: &[TypedField], public: bool, depth: usize) -> std::string::String {
    let mut result = String::new();
    let indent = (0..(depth * 4)).map(|_| " ").collect::<String>();

    for f in fields {
        if let TypedFieldType::Instant = f.type_name() {
            result += &indent;
            result += "#[serde(with = \"crate::system_time_serializer\")]\n";
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

fn to_rust_type(type_: &TypedFieldType) -> &str {
    match type_ {
        TypedFieldType::U8 => "u8",
        TypedFieldType::U16 => "u16",
        TypedFieldType::U32 => "u32",
        TypedFieldType::U64 => "u64",
        TypedFieldType::S8 => "s8",
        TypedFieldType::S16 => "s16",
        TypedFieldType::S32 => "s32",
        TypedFieldType::S64 => "s64",
        TypedFieldType::Instant => "std::time::SystemTime",
        TypedFieldType::Guid => "::uuid::Uuid",
        TypedFieldType::String => "String",
        TypedFieldType::OtherStruct(name) => name,
    }
}
