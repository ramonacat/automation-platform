{tag ? "latest"} @ args:
    let
        pkgs = import <nixpkgs> { };
        crate = pkgs.callPackage ../Cargo.nix { };
    in
        pkgs.dockerTools.streamLayeredImage {
            name = "svc-music";
            fromImage = null;
            tag = tag;
            config.Cmd = [ 
                "${crate.workspaceMembers.svc-music.build}/bin/svc-music" 
            ];
            contents = [ (pkgs.writeTextDir "/etc/ap/runtime.configuration.json" (builtins.readFile ../runtime.configuration.json)) ];
        }