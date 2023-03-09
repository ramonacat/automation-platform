{tag ? "latest", name} @ args:
    let
        pkgs = import <nixpkgs> { };
        crate = pkgs.callPackage ../Cargo.nix { };
    in
        pkgs.dockerTools.streamLayeredImage {
            inherit name tag;
            fromImage = null;
            config.Cmd = [ 
                "${crate.workspaceMembers.directory-watcher.build}/bin/directory-watcher" 
            ];
            contents = [ (pkgs.writeTextDir "/etc/ap/runtime.configuration.json" (builtins.readFile ../runtime.configuration.json)) ];
        }