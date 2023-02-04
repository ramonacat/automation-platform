{tag ? "latest"} @ args:
    let
        pkgs = import <nixpkgs> { };
        crate = pkgs.callPackage ../Cargo.nix { };
    in
        pkgs.dockerTools.streamLayeredImage {
            name = "svc-events";
            tag = tag;
            config.Cmd = [ 
                "${crate.workspaceMembers.svc-events.build}/bin/svc-events" 
            ];
        }