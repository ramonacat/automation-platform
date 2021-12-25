use crate::parsing::{DefinitionRaw, FieldRaw, FileRaw, IdentifierRaw};
use petgraph::algo::toposort;
use petgraph::graph::DiGraph;
use std::collections::HashMap;
use std::error::Error;
use std::fmt::{Display, Formatter};

#[derive(Debug)]
pub enum TypeCheckError {
    RepeatedName(String),
    RepeatedFieldName {
        field_name: String,
        struct_name: String,
    },
    StructNotFound(String),
    StructNotFoundMessageExists(String),
}

impl Display for TypeCheckError {
    fn fmt(&self, f: &mut Formatter<'_>) -> std::fmt::Result {
        match self {
            TypeCheckError::RepeatedName(name) => write!(f, "The type with name \"{}\" already exists", name),
            TypeCheckError::RepeatedFieldName { field_name, struct_name } => write!(f, "A field with name \"{}\" already exists in struct \"{}\"", field_name, struct_name),
            TypeCheckError::StructNotFound(name) => write!(f, "A struct with name \"{}\" does not exist", name),
            TypeCheckError::StructNotFoundMessageExists(name) => write!(f, "A struct with name \"{}\" does not exist, but a message does. Did you mean for it to be a struct?", name)
        }
    }
}

impl Error for TypeCheckError {}

#[derive(Debug)]
pub enum TypedFieldType {
    U8,
    U16,
    U32,
    U64,
    S8,
    S16,
    S32,
    S64,
    Instant,
    Guid,
    String,
    OtherStruct(String),
}

#[derive(Debug)]
pub struct TypedField {
    name: String,
    type_id: TypedFieldType,
}

impl TypedField {
    pub fn name(&self) -> &str {
        &self.name
    }

    pub fn type_name(&self) -> &TypedFieldType {
        &self.type_id
    }
}

#[derive(Debug)]
pub struct TypedStruct {
    name: String,
    fields: Vec<TypedField>,
}

#[derive(Debug)]
pub struct TypedMessage {
    name: String,
    fields: Vec<TypedField>,
}

impl TypedStruct {
    pub fn name(&self) -> &str {
        &self.name
    }

    pub fn fields(&self) -> &[TypedField] {
        &self.fields
    }
}

impl TypedMessage {
    pub fn name(&self) -> &str {
        &self.name
    }

    pub fn fields(&self) -> &[TypedField] {
        &self.fields
    }
}

#[derive(Debug)]
enum TypeCheckableFieldType<'a> {
    U8,
    U16,
    U32,
    U64,
    S8,
    S16,
    S32,
    S64,
    Instant,
    Guid,
    String,
    ToBeResolved(&'a str),
}

#[derive(Debug)]
struct TypeCheckableStructDefinition<'input> {
    name: String,
    fields: HashMap<&'input str, TypeCheckableFieldType<'input>>,
}

#[derive(Debug)]
struct TypeCheckableMessageDefinition<'input> {
    name: String,
    fields: HashMap<&'input str, TypeCheckableFieldType<'input>>,
}

pub struct TypedMetadata {
    fields: Vec<TypedField>,
}

impl TypedMetadata {
    pub fn fields(&self) -> &[TypedField] {
        &self.fields
    }
}

pub struct TypedFile {
    pub structs: Vec<TypedStruct>,
    pub messages: Vec<TypedMessage>,
    pub meta: TypedMetadata,
}

#[derive(Default)]
pub struct TypeChecker<'input> {
    structs: HashMap<String, TypeCheckableStructDefinition<'input>>,
    messages: HashMap<String, TypeCheckableMessageDefinition<'input>>,
}

impl<'input> TypeChecker<'input> {
    pub fn new() -> Self {
        Self::default()
    }

    fn check_duplicate(&'input self, name: &'input IdentifierRaw) -> Result<(), TypeCheckError> {
        if self.structs.contains_key(name.0) || self.messages.contains_key(name.0) {
            return Err(TypeCheckError::RepeatedName(name.0.to_string()));
        }

        Ok(())
    }

    fn map_fields(
        &self,
        fields_raw: &[FieldRaw<'input>],
        struct_name: &str,
    ) -> Result<HashMap<&'input str, TypeCheckableFieldType<'input>>, TypeCheckError> {
        let mut fields = HashMap::new();

        for field_raw in fields_raw {
            let type_id = match field_raw.1 .0 {
                "u8" => TypeCheckableFieldType::U8,
                "u16" => TypeCheckableFieldType::U16,
                "u32" => TypeCheckableFieldType::U32,
                "u64" => TypeCheckableFieldType::U64,
                "s8" => TypeCheckableFieldType::S8,
                "s16" => TypeCheckableFieldType::S16,
                "s32" => TypeCheckableFieldType::S32,
                "s64" => TypeCheckableFieldType::S64,
                "instant" => TypeCheckableFieldType::Instant,
                "guid" => TypeCheckableFieldType::Guid,
                "string" => TypeCheckableFieldType::String,
                other => TypeCheckableFieldType::ToBeResolved(other),
            };

            if fields.contains_key(field_raw.0 .0) {
                return Err(TypeCheckError::RepeatedFieldName {
                    field_name: field_raw.0 .0.to_string(),
                    struct_name: struct_name.to_string(),
                });
            }
            fields.insert(field_raw.0 .0, type_id);
        }

        Ok(fields)
    }

    fn type_check_fields(
        &self,
        raw_fields: &HashMap<&str, TypeCheckableFieldType>,
    ) -> Result<Vec<TypedField>, TypeCheckError> {
        let mut fields = vec![];

        for (field_name, field_type) in raw_fields {
            fields.push(TypedField {
                name: field_name.to_string(),
                type_id: match field_type {
                    TypeCheckableFieldType::U8 => TypedFieldType::U8,
                    TypeCheckableFieldType::U16 => TypedFieldType::U16,
                    TypeCheckableFieldType::U32 => TypedFieldType::U32,
                    TypeCheckableFieldType::U64 => TypedFieldType::U64,
                    TypeCheckableFieldType::S8 => TypedFieldType::S8,
                    TypeCheckableFieldType::S16 => TypedFieldType::S16,
                    TypeCheckableFieldType::S32 => TypedFieldType::S32,
                    TypeCheckableFieldType::S64 => TypedFieldType::S64,
                    TypeCheckableFieldType::Instant => TypedFieldType::Instant,
                    TypeCheckableFieldType::Guid => TypedFieldType::Guid,
                    TypeCheckableFieldType::String => TypedFieldType::String,
                    TypeCheckableFieldType::ToBeResolved(type_name) => {
                        if !self.structs.contains_key(*type_name) {
                            return if self.messages.contains_key(*type_name) {
                                Err(TypeCheckError::StructNotFoundMessageExists(
                                    type_name.to_string(),
                                ))
                            } else {
                                Err(TypeCheckError::StructNotFound(type_name.to_string()))
                            };
                        }
                        TypedFieldType::OtherStruct(type_name.to_string())
                    }
                },
            });
        }

        Ok(fields)
    }

    pub fn check(mut self, file: FileRaw<'input>) -> Result<TypedFile, TypeCheckError> {
        for definition_raw in file.definitions() {
            match definition_raw {
                DefinitionRaw::Struct(name, fields) => {
                    self.check_duplicate(name)?;

                    let fields = self.map_fields(fields, name.0)?;

                    self.structs.insert(
                        name.0.to_string(),
                        TypeCheckableStructDefinition {
                            name: name.0.to_string(),
                            fields,
                        },
                    );
                }
                DefinitionRaw::Message(name, fields) => {
                    self.check_duplicate(name)?;

                    let fields = self.map_fields(fields, name.0)?;

                    self.messages.insert(
                        name.0.to_string(),
                        TypeCheckableMessageDefinition {
                            name: name.0.to_string(),
                            fields,
                        },
                    );
                }
            }
        }

        let mut metadata_fields = HashMap::new();
        if let Some(metadata) = file.metadata() {
            metadata_fields = self.map_fields(metadata.fields(), "metadata")?;
        }

        let mut graph = DiGraph::new();
        let mut node_ids = HashMap::new();
        for struct_definition in self.structs.values() {
            let ix = graph.add_node(struct_definition);
            node_ids.insert(struct_definition.name.to_string(), ix);
        }

        for struct_definition in self.structs.values() {
            for field_definition in struct_definition.fields.values() {
                if let TypeCheckableFieldType::ToBeResolved(name) = field_definition {
                    graph.add_edge(
                        *node_ids.get(*name).unwrap(),
                        *node_ids.get(&struct_definition.name).unwrap(),
                        name.to_string(),
                    );
                }
            }
        }

        let sorted = toposort(&graph, None).unwrap();
        let mut structs_typed = HashMap::new();
        let mut messages_typed = vec![];

        for x in sorted {
            let node = graph.node_weight(x).unwrap();

            let typed_struct = TypedStruct {
                name: node.name.clone(),
                fields: self.type_check_fields(&node.fields)?,
            };
            structs_typed.insert(node.name.clone(), typed_struct);
        }

        for x in self.messages.values() {
            let typed_message = TypedMessage {
                name: x.name.clone(),
                fields: self.type_check_fields(&x.fields)?,
            };
            messages_typed.push(typed_message);
        }

        let meta_fields = self.type_check_fields(&metadata_fields)?;

        Ok(TypedFile {
            structs: structs_typed.into_iter().map(|(_, v)| v).collect(),
            messages: messages_typed,
            meta: TypedMetadata {
                fields: meta_fields,
            },
        })
    }
}
