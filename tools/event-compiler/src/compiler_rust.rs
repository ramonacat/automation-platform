use crate::type_checking::{TypedField, TypedFieldType, TypedFile};

pub fn compile(file: TypedFile) -> String {
    let TypedFile {
        structs,
        messages,
        meta,
    } = file;

    let mut result = String::new();

    result += "use serde::{Deserialize, Serialize};\n";

    result += "#[derive(Serialize, Deserialize)]\n";
    result += "pub struct Metadata {\n";
    result += &render_fields(meta.fields(), true);
    result += "}\n";

    for s in structs {
        result += "#[derive(Serialize, Deserialize)]\n";
        result += &format!("pub struct {} {{\n", s.name());
        result += &render_fields(s.fields(), true);
        result += "}\n";
    }

    result += "\n";

    result += "#[derive(Serialize, Deserialize)]\n";
    result += "#[serde(tag = \"type\")]\n";
    result += "pub enum MessagePayload {\n";
    for m in messages {
        result += &format!("    {} {{\n", m.name());
        result += &render_fields(m.fields(), false);
        result += "    },\n";
    }

    result += "}\n";

    result += "#[derive(Serialize, Deserialize)]\n";
    result += "pub struct Message {\n";
    result += "    pub metadata: Metadata,\n";
    result += "    pub payload: MessagePayload,\n";
    result += "}\n";

    result
}

fn render_fields(fields: &[TypedField], public: bool) -> String {
    let mut result = String::new();

    for f in fields {
        if let TypedFieldType::Instant = f.type_name() {
            result += "        #[serde(with=\"crate::system_time_serializer\")]\n";
        }
        result += &format!(
            "        {}{}: {},\n",
            if public { "pub " } else { "" },
            f.name(),
            to_rust_type(f.type_name())
        )
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
