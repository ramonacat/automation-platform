use async_walkdir::WalkDir;
use clap::{arg, command, value_parser};
use futures_util::StreamExt;
use lib_directory_watcher::{Client, FilesystemEvent, Metadata, Rpc};
use notify::{
    event::{CreateKind, ModifyKind, RemoveKind, RenameMode},
    Watcher,
};
use rpc_support::{rpc_error::RpcError, DefaultRawRpcClient, RawRpcClient};
use std::{error::Error, path::PathBuf, time::SystemTime};
use tokio::net::TcpStream;

async fn send_event<TRawRpcClient: RawRpcClient + Send + Sync>(
    client: &mut Client<TRawRpcClient>,
    event: FilesystemEvent,
) -> Result<(), RpcError> {
    client.file_changed(event, Metadata {}).await
}

fn make_path_relative(base: &PathBuf, path: &PathBuf) -> PathBuf {
    // TODO proper error handling...
    pathdiff::diff_paths(path, base).unwrap()
}

/// # Panics
/// TODO remove panics
/// # Errors
/// May fail
#[allow(clippy::too_many_lines)]
pub async fn main_inner() -> Result<(), Box<dyn Error>> {
    let matches = command!()
        .arg(
            arg!(-p --path <PATH> "Path to the directory to be watched")
                .value_parser(value_parser!(PathBuf)),
        )
        .arg(arg!(-n --name <MOUNT_NAME> "Name of the mount").value_parser(value_parser!(String)))
        .arg(
            arg!(-u --url <URL> "URL of the directory watcher service")
                .value_parser(value_parser!(String)),
        )
        .get_matches();

    let path = matches.get_one::<PathBuf>("path").unwrap().canonicalize()?;
    let mount_id = matches.get_one::<String>("name").unwrap();
    let (tx, rx) = std::sync::mpsc::channel();
    let mut notify = notify::recommended_watcher(move |e| {
        tx.send(e).unwrap();
    })
    .unwrap();
    notify
        .watch(&path, notify::RecursiveMode::Recursive)
        .unwrap();

    let raw_rpc_client = DefaultRawRpcClient::new(
        TcpStream::connect(matches.get_one::<String>("url").unwrap())
            .await
            .unwrap(),
    );
    let mut client = Client::new(raw_rpc_client);

    let mut walkdir = WalkDir::new(path.clone());

    while let Some(entry) = walkdir.next().await {
        let entry = entry.unwrap();

        if !entry.metadata().await.unwrap().is_file() {
            // TODO handle symlinks
            continue;
        }

        send_event(
            &mut client,
            FilesystemEvent {
                kind: lib_directory_watcher::FilesystemEventKind::Created {},
                mount_id: mount_id.clone(),
                path: make_path_relative(&path, &entry.path())
                    .to_string_lossy()
                    .to_string(),
                timestamp: entry
                    .metadata()
                    .await
                    .unwrap()
                    .modified()
                    .unwrap()
                    .duration_since(SystemTime::UNIX_EPOCH)
                    .unwrap()
                    .as_secs(),
            },
        )
        .await
        .unwrap();
    }

    while let Ok(event) = rx.recv() {
        let event = event?;
        match event.kind {
            notify::EventKind::Create(CreateKind::File)
            | notify::EventKind::Modify(ModifyKind::Name(RenameMode::To)) => {
                for current_path in event.paths {
                    send_event(
                        &mut client,
                        FilesystemEvent {
                            kind: lib_directory_watcher::FilesystemEventKind::Created {},
                            mount_id: mount_id.clone(),
                            path: make_path_relative(&path, &current_path)
                                .to_string_lossy()
                                .to_string(),
                            timestamp: std::fs::metadata(current_path)
                                .unwrap()
                                .modified()
                                .unwrap()
                                .duration_since(SystemTime::UNIX_EPOCH)
                                .unwrap()
                                .as_secs(),
                        },
                    )
                    .await
                    .unwrap();
                }
            }
            notify::EventKind::Remove(RemoveKind::File)
            | notify::EventKind::Modify(ModifyKind::Name(RenameMode::From)) => {
                let timestamp = std::fs::metadata(&path)
                    .map_or_else(|_| std::time::SystemTime::now(), |x| x.modified().unwrap())
                    .duration_since(SystemTime::UNIX_EPOCH)?
                    .as_secs();

                for current_path in event.paths {
                    send_event(
                        &mut client,
                        FilesystemEvent {
                            kind: lib_directory_watcher::FilesystemEventKind::Deleted {},
                            mount_id: mount_id.clone(),
                            path: make_path_relative(&path, &current_path)
                                .to_string_lossy()
                                .to_string(),
                            timestamp,
                        },
                    )
                    .await
                    .unwrap();
                }
            }
            notify::EventKind::Modify(ModifyKind::Name(RenameMode::Both)) => {
                send_event(
                    &mut client,
                    FilesystemEvent {
                        kind: lib_directory_watcher::FilesystemEventKind::Moved {
                            to: make_path_relative(&path, &event.paths[1])
                                .to_string_lossy()
                                .to_string(),
                        },
                        mount_id: mount_id.clone(),
                        path: make_path_relative(&path, &event.paths[0])
                            .to_string_lossy()
                            .to_string(),
                        timestamp: std::fs::metadata(&event.paths[1])
                            .unwrap()
                            .modified()
                            .unwrap()
                            .duration_since(SystemTime::UNIX_EPOCH)
                            .unwrap()
                            .as_secs(),
                    },
                )
                .await
                .unwrap();
            }
            notify::EventKind::Modify(_) => {
                for current_path in event.paths {
                    client
                        .file_changed(
                            FilesystemEvent {
                                kind: lib_directory_watcher::FilesystemEventKind::Modified {},
                                mount_id: mount_id.clone(),
                                path: make_path_relative(&path, &current_path)
                                    .to_string_lossy()
                                    .to_string(),
                                timestamp: std::fs::metadata(current_path)
                                    .unwrap()
                                    .modified()
                                    .unwrap()
                                    .duration_since(SystemTime::UNIX_EPOCH)
                                    .unwrap()
                                    .as_secs(),
                            },
                            Metadata {},
                        )
                        .await
                        .unwrap();
                }
            }
            e => {
                println!("Unhandled event: {e:?}");
            }
        }
    }

    Ok(())
}

#[tokio::main]
async fn main() {
    main_inner().await.unwrap();
}
