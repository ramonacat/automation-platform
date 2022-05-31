#[derive(Debug, PartialEq)]
pub struct IdentifierRaw<'input>(pub(crate) &'input str);

impl<'input> IdentifierRaw<'input> {
    #[must_use]
    pub fn new(name: &'input str) -> Self {
        Self(name)
    }
}

#[derive(Debug, PartialEq)]
pub struct FieldRaw<'input>(
    pub(crate) IdentifierRaw<'input>,
    pub(crate) IdentifierRaw<'input>,
);

impl<'input> FieldRaw<'input> {
    #[must_use]
    pub fn new(name: IdentifierRaw<'input>, type_name: IdentifierRaw<'input>) -> Self {
        Self(name, type_name)
    }
}

#[derive(Debug, PartialEq)]
pub struct StructDefinitionRaw<'input>(pub IdentifierRaw<'input>, pub Vec<FieldRaw<'input>>);

#[derive(Debug, PartialEq)]
pub struct MetadataRaw<'input> {
    fields: Vec<FieldRaw<'input>>,
}

impl<'input> MetadataRaw<'input> {
    #[must_use]
    pub(crate) fn fields(&self) -> &[FieldRaw<'input>] {
        &self.fields
    }
}

impl<'input> MetadataRaw<'input> {
    #[must_use]
    pub fn new(fields: Vec<FieldRaw<'input>>) -> Self {
        Self { fields }
    }
}

#[derive(Debug, PartialEq)]
pub struct RpcDefinitionRaw<'input> {
    pub(crate) name: IdentifierRaw<'input>,
    pub(crate) request: IdentifierRaw<'input>,
    pub(crate) response: IdentifierRaw<'input>,
    pub(crate) is_stream: bool,
}

impl<'input> RpcDefinitionRaw<'input> {
    #[must_use]
    pub fn new(
        name: IdentifierRaw<'input>,
        request: IdentifierRaw<'input>,
        response: IdentifierRaw<'input>,
        is_stream: bool,
    ) -> Self {
        Self {
            name,
            request,
            response,
            is_stream,
        }
    }
}

#[derive(Debug, PartialEq)]
pub struct RpcRaw<'input> {
    pub(crate) definitions: Vec<RpcDefinitionRaw<'input>>,
}

impl<'input> RpcRaw<'input> {
    #[must_use]
    pub fn new(definitions: Vec<RpcDefinitionRaw<'input>>) -> Self {
        Self { definitions }
    }
}

#[derive(Debug, PartialEq)]
pub struct FileRaw<'input> {
    metadata: Option<MetadataRaw<'input>>,
    structs: Vec<StructDefinitionRaw<'input>>,
    rpc: Option<RpcRaw<'input>>,
}

impl<'input> FileRaw<'input> {
    #[must_use]
    pub fn new(
        metadata: Option<MetadataRaw<'input>>,
        structs: Vec<StructDefinitionRaw<'input>>,
        rpc: Option<RpcRaw<'input>>,
    ) -> Self {
        Self {
            metadata,
            structs,
            rpc,
        }
    }

    #[must_use]
    pub fn metadata(&self) -> Option<&MetadataRaw<'input>> {
        self.metadata.as_ref()
    }

    #[must_use]
    pub fn structs(&self) -> &[StructDefinitionRaw<'input>] {
        &self.structs
    }

    #[must_use]
    pub fn rpc(&self) -> Option<&RpcRaw<'input>> {
        self.rpc.as_ref()
    }
}

lalrpop_util::lalrpop_mod!(
    #[
        allow(
            clippy::all, clippy::unnested_or_patterns, clippy::missing_errors_doc,
            clippy::trivially_copy_pass_by_ref, clippy::unnecessary_wraps,
            clippy::pedantic
        )
    ]
    pub grammar
);

#[cfg(test)]
mod test {
    use super::*;
    use crate::parsing;

    #[test]
    pub fn can_parse_empty_struct() {
        let input = "struct A {}";
        let r = parsing::grammar::RFileParser::new().parse(input);

        assert_eq!(
            Ok(FileRaw::new(
                None,
                vec![StructDefinitionRaw(IdentifierRaw::new("A"), vec![])],
                None
            )),
            r
        )
    }

    #[test]
    pub fn can_parse_some_structs() {
        let input = "struct A { f1: u32, f2: u64} struct B { fx-1: A, fx-2: instant } struct CoolStruct29 {fx-1: B, fx-3:u8,}";
        let r = parsing::grammar::RFileParser::new().parse(input);

        assert_eq!(
            Ok(FileRaw::new(
                None,
                vec![
                    StructDefinitionRaw(
                        IdentifierRaw::new("A"),
                        vec![
                            FieldRaw::new(IdentifierRaw::new("f1"), IdentifierRaw::new("u32")),
                            FieldRaw::new(IdentifierRaw::new("f2"), IdentifierRaw::new("u64")),
                        ]
                    ),
                    StructDefinitionRaw(
                        IdentifierRaw::new("B"),
                        vec![
                            FieldRaw::new(IdentifierRaw::new("fx-1"), IdentifierRaw::new("A")),
                            FieldRaw::new(
                                IdentifierRaw::new("fx-2"),
                                IdentifierRaw::new("instant")
                            ),
                        ]
                    ),
                    StructDefinitionRaw(
                        IdentifierRaw::new("CoolStruct29"),
                        vec![
                            FieldRaw::new(IdentifierRaw::new("fx-1"), IdentifierRaw::new("B")),
                            FieldRaw::new(IdentifierRaw::new("fx-3"), IdentifierRaw::new("u8")),
                        ]
                    ),
                ],
                None
            )),
            r
        );
    }

    #[test]
    pub fn can_parse_rpc_defintiion() {
        let input = "struct request { f1: u32 } struct response { f2: u64 } rpc { call(request) -> response; }";
        let r = parsing::grammar::RFileParser::new().parse(input);

        assert_eq!(
            Ok(FileRaw::new(
                None,
                vec![
                    StructDefinitionRaw(
                        IdentifierRaw::new("request"),
                        vec![FieldRaw::new(
                            IdentifierRaw::new("f1"),
                            IdentifierRaw::new("u32")
                        ),]
                    ),
                    StructDefinitionRaw(
                        IdentifierRaw::new("response"),
                        vec![FieldRaw::new(
                            IdentifierRaw::new("f2"),
                            IdentifierRaw::new("u64")
                        ),]
                    ),
                ],
                Some(RpcRaw::new(vec![RpcDefinitionRaw::new(
                    IdentifierRaw::new("call"),
                    IdentifierRaw::new("request"),
                    IdentifierRaw::new("response"),
                    false
                )]))
            )),
            r
        );
    }

    #[test]
    pub fn can_parse_rpc_stream_defintiion() {
        let input = "struct request { f1: u32 } struct response { f2: u64 } rpc { call(request) -> stream response; }";
        let r = parsing::grammar::RFileParser::new().parse(input);

        assert_eq!(
            Ok(FileRaw::new(
                None,
                vec![
                    StructDefinitionRaw(
                        IdentifierRaw::new("request"),
                        vec![FieldRaw::new(
                            IdentifierRaw::new("f1"),
                            IdentifierRaw::new("u32")
                        ),]
                    ),
                    StructDefinitionRaw(
                        IdentifierRaw::new("response"),
                        vec![FieldRaw::new(
                            IdentifierRaw::new("f2"),
                            IdentifierRaw::new("u64")
                        ),]
                    ),
                ],
                Some(RpcRaw::new(vec![RpcDefinitionRaw::new(
                    IdentifierRaw::new("call"),
                    IdentifierRaw::new("request"),
                    IdentifierRaw::new("response"),
                    true
                )]))
            )),
            r
        );
    }
}
