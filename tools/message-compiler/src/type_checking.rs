use crate::parsing::{
    EnumDefinitionRaw, EnumVariantRaw, FieldRaw, FileRaw, IdentifierRaw, StructDefinitionRaw,
};
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
}

impl Display for TypeCheckError {
    fn fmt(&self, f: &mut Formatter<'_>) -> std::fmt::Result {
        match self {
            TypeCheckError::RepeatedName(name) => {
                write!(f, "The type with name \"{}\" already exists", name)
            }
            TypeCheckError::RepeatedFieldName {
                field_name,
                struct_name,
            } => write!(
                f,
                "A field with name \"{}\" already exists in struct \"{}\"",
                field_name, struct_name
            ),
            TypeCheckError::StructNotFound(name) => {
                write!(f, "A struct with name \"{}\" does not exist", name)
            }
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
    Void,
    OtherStruct(String),
    Enum(String),
}

#[derive(Debug)]
pub struct TypedField {
    name: String,
    type_id: TypedFieldType,
}

impl TypedField {
    #[must_use]
    pub fn name(&self) -> &str {
        &self.name
    }

    #[must_use]
    pub fn type_name(&self) -> &TypedFieldType {
        &self.type_id
    }
}

#[derive(Debug)]
pub struct TypedStruct {
    name: String,
    fields: Vec<TypedField>,
}

impl TypedStruct {
    #[must_use]
    pub fn name(&self) -> &str {
        &self.name
    }

    #[must_use]
    pub fn fields(&self) -> &[TypedField] {
        &self.fields
    }
}

#[derive(Debug)]
pub struct TypedEnumVariant {
    name: String,
    fields: Vec<TypedField>,
}

impl TypedEnumVariant {
    #[must_use]
    pub fn name(&self) -> &str {
        &self.name
    }

    #[must_use]
    pub fn fields(&self) -> &[TypedField] {
        &self.fields
    }
}

#[derive(Debug)]
pub struct TypedEnum {
    name: String,
    variants: Vec<TypedEnumVariant>,
}

impl TypedEnum {
    #[must_use]
    pub fn name(&self) -> &str {
        &self.name
    }

    #[must_use]
    pub fn variants(&self) -> &[TypedEnumVariant] {
        &self.variants
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
    Void,
    ToBeResolved(&'a str),
}

#[derive(Debug)]
struct TypeCheckableStructDefinition<'input> {
    name: String,
    fields: HashMap<&'input str, TypeCheckableFieldType<'input>>,
}

#[derive(Debug)]
struct TypeCheckableEnumVariant<'input> {
    name: String,
    fields: HashMap<&'input str, TypeCheckableFieldType<'input>>,
}

#[derive(Debug)]
struct TypeCheckableEnumDefinition<'input> {
    variants: HashMap<&'input str, TypeCheckableEnumVariant<'input>>,
}

pub struct TypedMetadata {
    fields: Vec<TypedField>,
}

impl TypedMetadata {
    #[must_use]
    pub fn fields(&self) -> &[TypedField] {
        &self.fields
    }
}

pub struct TypedRpcCall {
    name: String,
    request: TypedFieldType,
    response: TypedFieldType,
    is_stream: bool,
}

impl TypedRpcCall {
    #[must_use]
    pub fn name(&self) -> &str {
        &self.name
    }

    #[must_use]
    pub fn request(&self) -> &TypedFieldType {
        &self.request
    }

    #[must_use]
    pub fn response(&self) -> &TypedFieldType {
        &self.response
    }

    #[must_use]
    pub fn is_stream(&self) -> bool {
        self.is_stream
    }
}

pub struct TypedRpc {
    pub calls: Vec<TypedRpcCall>,
}

impl TypedRpc {
    #[must_use]
    pub fn calls(&self) -> &[TypedRpcCall] {
        &self.calls
    }
}

pub struct TypedFile {
    pub structs: Vec<TypedStruct>,
    pub enums: Vec<TypedEnum>,
    pub meta: TypedMetadata,
    pub rpc: TypedRpc,
}

#[derive(Default)]
pub struct TypeChecker<'input> {
    structs: HashMap<String, TypeCheckableStructDefinition<'input>>,
    enums: HashMap<String, TypeCheckableEnumDefinition<'input>>,
}

impl<'input> TypeChecker<'input> {
    #[must_use]
    pub fn new() -> Self {
        Self::default()
    }

    fn check_duplicate(&'input self, name: &'input IdentifierRaw) -> Result<(), TypeCheckError> {
        if self.structs.contains_key(name.0) {
            return Err(TypeCheckError::RepeatedName(name.0.to_string()));
        }

        Ok(())
    }

    fn map_fields(
        fields_raw: &[FieldRaw<'input>],
        struct_name: &str,
    ) -> Result<HashMap<&'input str, TypeCheckableFieldType<'input>>, TypeCheckError> {
        let mut fields = HashMap::new();

        for field_raw in fields_raw {
            let type_id = Self::resolve_raw_type(field_raw.1 .0);

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

    fn resolve_raw_type(field_raw: &str) -> TypeCheckableFieldType {
        match field_raw {
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
            "void" => TypeCheckableFieldType::Void,
            other => TypeCheckableFieldType::ToBeResolved(other),
        }
    }

    fn type_check_fields(
        &self,
        raw_fields: &HashMap<&str, TypeCheckableFieldType>,
    ) -> Result<Vec<TypedField>, TypeCheckError> {
        let mut fields = vec![];

        for (field_name, field_type) in raw_fields {
            fields.push(TypedField {
                name: (*field_name).to_string(),
                type_id: self.resolve_type(field_type)?,
            });
        }

        Ok(fields)
    }

    fn resolve_type(
        &self,
        field_type: &TypeCheckableFieldType,
    ) -> Result<TypedFieldType, TypeCheckError> {
        Ok(match field_type {
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
            TypeCheckableFieldType::Void => TypedFieldType::Void,
            TypeCheckableFieldType::ToBeResolved(type_name) => {
                if self.structs.contains_key(*type_name) {
                    return Ok(TypedFieldType::OtherStruct((*type_name).to_string()));
                } else if self.enums.contains_key(*type_name) {
                    return Ok(TypedFieldType::Enum((*type_name).to_string()));
                }
                return Err(TypeCheckError::StructNotFound((*type_name).to_string()));
            }
        })
    }

    /// # Errors
    /// May return an error when the type check fails
    /// # Panics
    /// TODO MAKE THIS NOT EVER PANIC
    /// todo split into smaller functions
    pub fn check(mut self, file: &FileRaw<'input>) -> Result<TypedFile, TypeCheckError> {
        for StructDefinitionRaw(name, fields) in file.structs() {
            self.check_duplicate(name)?;

            let fields = Self::map_fields(fields, name.0)?;

            self.structs.insert(
                name.0.to_string(),
                TypeCheckableStructDefinition {
                    name: name.0.to_string(),
                    fields,
                },
            );
        }

        for EnumDefinitionRaw { name, variants } in file.enums() {
            // fixme check for name conflicts with structs
            // fixme check for name conflicts with other enums

            let variants = Self::map_enum_variants(variants, name.0)?;

            self.enums
                .insert(name.0.to_string(), TypeCheckableEnumDefinition { variants });
        }

        let mut metadata_fields = HashMap::new();
        if let Some(metadata) = file.metadata() {
            metadata_fields = Self::map_fields(metadata.fields(), "metadata")?;
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
                        (*name).to_string(),
                    );
                }
            }
        }

        let sorted = toposort(&graph, None).unwrap();
        let mut structs_typed = HashMap::new();

        for x in sorted {
            let node = graph.node_weight(x).unwrap();

            let typed_struct = TypedStruct {
                name: node.name.clone(),
                fields: self.type_check_fields(&node.fields)?,
            };
            structs_typed.insert(node.name.clone(), typed_struct);
        }

        let enums = self
            .enums
            .iter()
            .map(|(name, enum_definition)| {
                let variants = enum_definition.variants.iter().map(|variant| {
                    let fields = self.type_check_fields(&variant.1.fields).unwrap();

                    TypedEnumVariant {
                        name: variant.1.name.to_string(),
                        fields,
                    }
                });

                TypedEnum {
                    name: name.clone(),
                    variants: variants.collect(),
                }
            })
            .collect();

        let meta_fields = self.type_check_fields(&metadata_fields)?;
        let rpc = file.rpc().map(|rpc| {
            let mut rpc_typed = HashMap::new();
            for rpc_definition in &rpc.definitions {
                let typed_rpc = TypedRpcCall {
                    name: rpc_definition.name.0.to_string(),
                    // todo no unwraps here!
                    request: self
                        .resolve_type(&Self::resolve_raw_type(rpc_definition.request.0))
                        .unwrap(),
                    response: self
                        .resolve_type(&Self::resolve_raw_type(rpc_definition.response.0))
                        .unwrap(),
                    is_stream: rpc_definition.is_stream,
                };
                rpc_typed.insert(rpc_definition.name.0.to_string(), typed_rpc);
            }
            rpc_typed
        });

        Ok(TypedFile {
            structs: structs_typed.into_iter().map(|(_, v)| v).collect(),
            enums,
            meta: TypedMetadata {
                fields: meta_fields,
            },
            rpc: TypedRpc {
                calls: rpc.map_or_else(Vec::new, |rpc| rpc.into_iter().map(|(_, v)| v).collect()),
            },
        })
    }

    fn map_enum_variants(
        variants: &[EnumVariantRaw<'input>],
        name: &str,
    ) -> Result<HashMap<&'input str, TypeCheckableEnumVariant<'input>>, TypeCheckError> {
        variants
            .iter()
            .map(|variant| {
                let fields = Self::map_fields(&variant.fields, name)?;
                Ok((
                    variant.name.0,
                    TypeCheckableEnumVariant {
                        name: variant.name.0.to_string(),
                        fields,
                    },
                ))
            })
            .collect()
    }
}
