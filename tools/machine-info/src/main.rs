fn main() {
    let arguments: Vec<String> = std::env::args().collect();

    if let Some(action) = arguments.get(1) {
        match action.as_str() {
            "logical-cores" => println!("{}", num_cpus::get()),
            "physical-cores" => println!("{}", num_cpus::get_physical()),
            _ => usage(&arguments),
        };
    } else {
        usage(&arguments);
    }
}

fn usage(arguments: &[String]) {
    eprintln!(
        "Usage {} [logical-cores|physical-cores]",
        arguments.get(0).unwrap_or(&"machine-info".to_string())
    );

    std::process::exit(1);
}
