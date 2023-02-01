    let 
        rust_overlay = import (builtins.fetchTarball "https://github.com/oxalica/rust-overlay/archive/master.tar.gz");
        pkgs = import <nixpkgs> { overlays = [ rust_overlay ]; };
        rustPlatform = pkgs.makeRustPlatform { 
            cargo = pkgs.rust-bin.stable.latest.default;
            rustc = pkgs.rust-bin.stable.latest.default;
         };
        rustBuild = rustPlatform.buildRustPackage {
            name = "directory-watcher";
            version = "0.1.0";
            src = pkgs.nix-gitignore.gitignoreRecursiveSource [] ../../../.;
            cargoRoot = "services/directory-watcher";
            sourceRoot = ".";
            cargoLock = {
                lockFile = ../Cargo.lock;
            };
        };
    in pkgs.dockerTools.buildLayeredImage {
        name = "automation-platform-directory-watcher";
        tag = "latest";
        config.Cmd = [ "${rustBuild}/bin/directory-watcher" ];
    }