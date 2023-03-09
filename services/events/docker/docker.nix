{tag ? "latest", name} @ args:
    let
        pkgs = import <nixpkgs> { };
        crate = pkgs.callPackage ../Cargo.nix { };
    in
        pkgs.dockerTools.streamLayeredImage {
          fromImage = null;
            inherit name tag;
            config.Cmd = [ 
                "${crate.workspaceMembers.svc-events.build}/bin/svc-events" 
            ];
        }