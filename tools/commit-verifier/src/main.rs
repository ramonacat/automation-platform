use git2::{BranchType, Repository};
use std::path::Path;

fn main() {
    let repository = Repository::discover(".").expect("Failed to open the git repository");
    // todo check if the repository is dirty
    // todo allow checking commits other than HEAD

    let head = repository.head().expect("Failed to get repository HEAD");
    let mut commit = head.peel_to_commit().expect("Failed to get HEAD commit");

    let arguments: Vec<String> = std::env::args().collect();

    let merge_base = arguments.get(1).map_or_else(
        || {
            repository
                .merge_base(
                    commit.id(),
                    repository
                        .find_branch("origin/main", BranchType::Remote)
                        .expect("Failed to get the main branch")
                        .get()
                        .peel_to_commit()
                        .expect("Failed to get the commit on main branch")
                        .id(),
                )
                .expect("Failed to get the merge base of the current branch and main")
        },
        |raw_ref| {
            repository
                .find_commit(repository.revparse(raw_ref).expect("Invalid commit sha").from().expect("No \"to\" for the specified reference").id())
                .expect("Failed to find the supplied commit")
                .id()
        },
    );

    loop {
        let message = commit.message().expect("No commit message").to_string();

        println!("Checking {}", commit.id());
        println!(
            "Commit message:\n {}",
            message
                .trim_end()
                .lines()
                .map(|line| String::from("    ") + line)
                .collect::<Vec<String>>()
                .join("\n")
        );

        let workdir = repository
            .workdir()
            .expect("No working directory found for the repository");

        let allowed_prefixes = find_allowed_prefixes(workdir);

        if allowed_prefixes
            .iter()
            .any(|prefix| message.starts_with(prefix))
        {
            println!("* Commit message prefix is VALID");
        } else if commit.parent_count() > 1 {
            println!("* This is a merge commit, accepting an invalid message");
        } else if commit.author().name() == Some("dependabot[bot]") {
            println!("* This commit was made by dependabot, accepting an invalid message");
        } else {
            println!("* Commit message prefix is INVALID");
            std::process::exit(1);
        }

        if commit.id() == merge_base {
            break;
        }

        println!();

        commit = commit.parent(0).expect("Failed to get commit parent");
    }
}

fn find_subprojects(workdir: &Path, subproject_type: &str) -> Result<Vec<String>, std::io::Error> {
    let mut result = vec![];
    let services = std::fs::read_dir(workdir.join(subproject_type))?;
    for service in services {
        let service = service.expect("Failed to read DirEntry");

        if !service.metadata().expect("Failed to get metadata").is_dir() {
            continue;
        }

        result.push(service.file_name().to_string_lossy().into());
    }

    Ok(result)
}

fn find_allowed_prefixes(workdir: &Path) -> Vec<String> {
    let subprojects_to_prefixes = |subproject_type: &str| -> Vec<String> {
        let mut prefixes = vec![];
        let subprojects = find_subprojects(workdir, subproject_type);
        if let Ok(subproejcts) = subprojects {
            for subproject in subproejcts {
                prefixes.push(format!(
                    "[{}/{}]",
                    subproject_type
                        .split('/')
                        .map(|part| String::from(&part[0..1].to_uppercase()) + &part[1..])
                        .collect::<Vec<String>>()
                        .join("/"),
                    subproject
                        .split('-')
                        .map(|part| String::from(&part[0..1].to_uppercase()) + &part[1..])
                        .collect::<String>()
                ));
            }
        }

        prefixes
    };

    let mut all_prefixes = vec!["[All]".to_string()];
    all_prefixes.append(&mut subprojects_to_prefixes("services"));
    all_prefixes.append(&mut subprojects_to_prefixes("tools"));
    all_prefixes.append(&mut subprojects_to_prefixes("libraries/rust"));
    all_prefixes.append(&mut subprojects_to_prefixes("libraries/php"));

    all_prefixes
}
