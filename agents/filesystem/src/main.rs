use async_walkdir::WalkDir;
use clap::{arg, command, value_parser};
use futures::StreamExt;
use lib_directory_watcher::{
    ClientConnection, FilesystemEvent, Requester, RequesterRpc, ResponderReverseRpc,
};
use notify::{
    event::{CreateKind, ModifyKind, RemoveKind, RenameMode},
    Watcher,
};
use std::{error::Error, path::PathBuf, sync::Arc, time::SystemTime};
use tokio::net::TcpStream;

async fn send_event(
    client: &Arc<Requester>,
    event: FilesystemEvent,
) -> Result<Result<(), lib_directory_watcher::Error>, rpc_support::connection::Error> {
    client.file_changed(event).await
}

fn make_path_relative(base: &PathBuf, path: &PathBuf) -> PathBuf {
    // TODO proper error handling...
    pathdiff::diff_paths(path, base).unwrap()
}

struct FilesystemReverseRpc;

#[async_trait::async_trait]
impl ResponderReverseRpc for FilesystemReverseRpc {
    async fn read_file(
        &self,
        _request: String,
        _other_side: std::sync::Arc<dyn RequesterRpc>,
    ) -> Result<Vec<u8>, lib_directory_watcher::Error> {
        Ok("test69".as_bytes().to_owned())
    }
}

/// # Panics
/// TODO remove panics
/// # Errors
/// May fail
#[allow(clippy::too_many_lines)]
pub async fn main_inner() -> Result<(), Box<dyn Error>> {
    let subscriber = tracing_subscriber::FmtSubscriber::new();
    tracing::subscriber::set_global_default(subscriber)?;

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

    let tcp_stream = TcpStream::connect(matches.get_one::<String>("url").unwrap())
        .await
        .unwrap();

    let client = ClientConnection::from_tcp_stream(tcp_stream);
    let requester = client.run(Arc::new(FilesystemReverseRpc)).await;

    let mut walkdir = WalkDir::new(path.clone());

    while let Some(entry) = walkdir.next().await {
        let entry = entry.unwrap();

        if !entry.metadata().await.unwrap().is_file() {
            // TODO handle symlinks
            continue;
        }

        send_event(
            &requester,
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
        .unwrap()
        .unwrap();
    }

    while let Ok(event) = rx.recv() {
        let event = event?;
        match event.kind {
            notify::EventKind::Create(CreateKind::File)
            | notify::EventKind::Modify(ModifyKind::Name(RenameMode::To)) => {
                for current_path in event.paths {
                    send_event(
                        &requester,
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
                    .unwrap()
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
                        &requester,
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
                    .unwrap()
                    .unwrap();
                }
            }
            notify::EventKind::Modify(ModifyKind::Name(RenameMode::Both)) => {
                send_event(
                    &requester,
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
                .unwrap()
                .unwrap();
            }
            notify::EventKind::Modify(_) => {
                for current_path in event.paths {
                    requester
                        .file_changed(FilesystemEvent {
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
                        })
                        .await
                        .unwrap()
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
