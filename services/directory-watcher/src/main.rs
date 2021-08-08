#![deny(clippy::all, clippy::pedantic, clippy::nursery)]

use chrono::{DateTime, Utc};
use native_tls::TlsConnector;
use postgres::Client;
use postgres_native_tls::MakeTlsConnector;
use std::collections::VecDeque;
use std::fs::read_dir;
use std::thread::sleep;
use std::time::Duration;
use notify::{Watcher, RecursiveMode, DebouncedEvent};

#[derive(Debug)]
enum FileStatusSyncResult {
    Modified,
    NotModified,
}

fn sync_file_status(
    pg_client: &mut Client,
    mount_id: &str,
    path: &str,
    modified_at: DateTime<Utc>,
) -> FileStatusSyncResult {
    let mut transaction = pg_client.transaction().expect("Failed to start transaction");

    let rows = transaction
        .query(
            "SELECT modified_date FROM files WHERE mount_id=$1 AND path=$2 FOR UPDATE",
            &[&mount_id, &path],
        )
        .expect("Failed to get the file status");

    if rows.is_empty() {
        transaction
            .execute("INSERT INTO files (id, mount_id, path, modified_date) VALUES(gen_random_uuid(), $1, $2, $3)", &[&mount_id, &path, &modified_at])
            .expect("Failed to save modified date");

        transaction.commit().expect("Failed to commit");
        return FileStatusSyncResult::Modified;
    }

    let current_modified_at = rows.get(0).expect("No row?").get::<_, DateTime<Utc>>(0);
    if current_modified_at != modified_at {
        transaction
            .execute(
                "UPDATE files SET modified_date=$1 WHERE mount_id=$2 AND path=$3",
                &[&modified_at, &mount_id, &path],
            )
            .expect("Failed to update modified date");

        transaction.commit().expect("Failed to commit");
        return FileStatusSyncResult::Modified;
    }

    transaction.commit().expect("Failed to commit");
    FileStatusSyncResult::NotModified
}

fn main() {
    let pg_user = std::fs::read_to_string(
        "/etc/svc-events/secrets/directory-watcher.ap-directory-watcher.credentials/username",
    )
    .expect("Did not find the DB username");
    let pg_password = std::fs::read_to_string(
        "/etc/svc-events/secrets/directory-watcher.ap-directory-watcher.credentials/password",
    )
    .expect("Did not find the DB password");
    println!("read secrets");

    // fixme verify the root cert
    let tls_connector = TlsConnector::builder()
        .danger_accept_invalid_certs(true)
        .build()
        .expect("Failed to build the TlsConnector");

    println!("Created tls connector");

    let mut pg_client = postgres::Client::connect(
        &format!(
            "host=ap-directory-watcher sslmode=require user={} password={}",
            pg_user, pg_password
        ),
        MakeTlsConnector::new(tls_connector),
    )
    .expect("Failed to connect to PostgreSQL");
    println!("pgsql connection created");

    let directories_from_env = std::env::var("DW_DIRECTORIES_TO_WATCH")
        .expect("The list of directories to watch must be provided in an environment variable");
    let directories: Vec<Vec<&str>> = directories_from_env
        .split(',')
        .map(|x| x.split(':').collect())
        .collect();


    let (sender, receiver) = std::sync::mpsc::channel();
    let mut watcher = notify::watcher(sender, Duration::from_secs(1)).expect("Failed to get the watcher");

    for dir in directories {
        println!("Watching: {} ({})", dir[0], dir[1]);
        watcher.watch(dir[0], RecursiveMode::Recursive).expect("Failed to watch");

        let mut to_check: VecDeque<String> = VecDeque::new();
        to_check.push_back(dir[0].to_string());

        while let Some(path) = to_check.pop_front() {
            let result = read_dir(path).expect("failed to read directory");
            for entry in result {
                let entry = entry.expect("Failed to get DirEntry");
                let metadata = entry.metadata().expect("Failed to get metadata");
                let path = entry.path().to_str().expect("Invalid path").to_string();

                if metadata.is_dir() {
                    to_check.push_back(path);
                } else {
                    let sync_status = sync_file_status(
                        &mut pg_client,
                        dir[1],
                        pathdiff::diff_paths(path.clone(), dir[0])
                            .expect("Failed to get relative path")
                            .to_str()
                            .expect("Failed to convert path to string"),
                        DateTime::from(metadata.modified().expect("Failed to get modified date"))
                    );
                    println!("Found file: {} ({:?})", path, sync_status);
                }
            }
        }
    }

    for item in receiver {
        match item {
            DebouncedEvent::NoticeWrite(x) => println!("Notice write: {}", x.to_string_lossy()),
            DebouncedEvent::NoticeRemove(x) => println!("Notice remove: {}", x.to_string_lossy()),
            DebouncedEvent::Create(x) => println!("Create: {}", x.to_string_lossy()),
            DebouncedEvent::Write(x) => println!("Write: {}", x.to_string_lossy()),
            DebouncedEvent::Chmod(x) => println!("Chmod: {}", x.to_string_lossy()),
            DebouncedEvent::Remove(x) => println!("Remove: {}", x.to_string_lossy()),
            DebouncedEvent::Rename(x, y) => println!("Rename: {} -> {}", x.to_string_lossy(), y.to_string_lossy()),
            DebouncedEvent::Rescan => println!("Rescan!"),
            DebouncedEvent::Error(x, y) => println!("Error: {} (at {})", x, y.map_or("".into(), |z| z.to_string_lossy().to_string())),
        }
    }
}
