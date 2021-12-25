mod compiler_rust;
mod parsing;
mod type_checking;

#[macro_use]
extern crate lalrpop_util;

use crate::type_checking::TypeChecker;
use std::error::Error;

fn main() -> Result<(), Box<dyn Error>> {
    let ast = parsing::grammar::RFileParser::new().parse(
        "metadata { f0: u16, } message A1 { f0:u8} struct A { f0:u8 } struct B { f1:u16, f2:A } message A2 {f1:B}",
    )?;
    let type_checker = TypeChecker::new();
    let typed_file = type_checker.check(ast)?;
    let rust = compiler_rust::compile(typed_file);
    println!("{}", rust);

    Ok(())
}
