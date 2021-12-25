use event_compiler::type_checking::TypeChecker;
use std::error::Error;
use std::fs::read_to_string;

fn main() -> Result<(), Box<dyn Error>> {
    println!("cargo:rerun-if-changed=events.evd");

    let parser = event_compiler::parsing::grammar::RFileParser::new();
    let events = read_to_string("events.evd")?;
    let ast = parser.parse(&events).unwrap();

    let type_checker = TypeChecker::new();
    let typed_file = type_checker.check(ast)?;

    let rust = event_compiler::compiler_rust::compile(typed_file);
    std::fs::write("src/structs.rs", rust).unwrap();

    Ok(())
}
