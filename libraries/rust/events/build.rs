use message_compiler::type_checking::TypeChecker;
use std::error::Error;
use std::fs::read_to_string;

fn main() -> Result<(), Box<dyn Error>> {
    println!("cargo:rerun-if-changed=events.evd");

    let parser = message_compiler::parsing::grammar::RFileParser::new();
    let events = read_to_string("events.evd")?;
    let ast = parser.parse(&events).unwrap();

    let type_checker = TypeChecker::new();
    let typed_file = type_checker.check(&ast)?;

    let rust = message_compiler::compiler_rust::compile(typed_file);
    let out_dir = std::env::var_os("OUT_DIR").unwrap();
    let dest_path = std::path::Path::new(&out_dir).join("structs.rs");
    std::fs::write(dest_path, rust).unwrap();

    Ok(())
}
