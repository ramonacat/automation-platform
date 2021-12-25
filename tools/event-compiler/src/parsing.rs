#[derive(Debug, PartialEq)]
pub struct IdentifierRaw<'input>(pub(crate) &'input str);

impl<'input> IdentifierRaw<'input> {
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
    pub fn new(name: IdentifierRaw<'input>, type_name: IdentifierRaw<'input>) -> Self {
        Self(name, type_name)
    }
}

#[derive(Debug, PartialEq)]
pub enum DefinitionRaw<'input> {
    Struct(IdentifierRaw<'input>, Vec<FieldRaw<'input>>),
    Message(IdentifierRaw<'input>, Vec<FieldRaw<'input>>),
}

#[derive(Debug, PartialEq)]
pub struct MetadataRaw<'input> {
    fields: Vec<FieldRaw<'input>>,
}

impl<'input> MetadataRaw<'input> {
    pub(crate) fn fields(&self) -> &[FieldRaw<'input>] {
        &self.fields
    }
}

impl<'input> MetadataRaw<'input> {
    pub fn new(fields: Vec<FieldRaw<'input>>) -> Self {
        Self { fields }
    }
}

#[derive(Debug, PartialEq)]
pub struct FileRaw<'input> {
    metadata: Option<MetadataRaw<'input>>,
    definitions: Vec<DefinitionRaw<'input>>,
}

impl<'input> FileRaw<'input> {
    pub fn new(
        metadata: Option<MetadataRaw<'input>>,
        definitions: Vec<DefinitionRaw<'input>>,
    ) -> Self {
        Self {
            metadata,
            definitions,
        }
    }

    pub fn metadata(&self) -> Option<&MetadataRaw<'input>> {
        self.metadata.as_ref()
    }

    pub fn definitions(&self) -> &[DefinitionRaw<'input>] {
        &self.definitions
    }
}

lalrpop_util::lalrpop_mod!(#[allow(clippy::all)] pub grammar);

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
                vec![DefinitionRaw::Struct(IdentifierRaw::new("A"), vec![])]
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
                    DefinitionRaw::Struct(
                        IdentifierRaw::new("A"),
                        vec![
                            FieldRaw::new(IdentifierRaw::new("f1"), IdentifierRaw::new("u32")),
                            FieldRaw::new(IdentifierRaw::new("f2"), IdentifierRaw::new("u64")),
                        ]
                    ),
                    DefinitionRaw::Struct(
                        IdentifierRaw::new("B"),
                        vec![
                            FieldRaw::new(IdentifierRaw::new("fx-1"), IdentifierRaw::new("A")),
                            FieldRaw::new(
                                IdentifierRaw::new("fx-2"),
                                IdentifierRaw::new("instant")
                            ),
                        ]
                    ),
                    DefinitionRaw::Struct(
                        IdentifierRaw::new("CoolStruct29"),
                        vec![
                            FieldRaw::new(IdentifierRaw::new("fx-1"), IdentifierRaw::new("B")),
                            FieldRaw::new(IdentifierRaw::new("fx-3"), IdentifierRaw::new("u8")),
                        ]
                    ),
                ]
            )),
            r
        );
    }
}
