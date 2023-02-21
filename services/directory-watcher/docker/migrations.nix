{tag ? "latest"}: 
  let
    pkgs = import <nixpkgs> { }; 
    package = import ../../../nix/dbmate.nix;
    migrations = ../migrations;
    migrateScript = pkgs.writeShellApplication {
      name = "migrate";
      text = ''
      echo "Reading secrets..."

      USERNAME=$(cat /etc/svc-events/secrets/directory-watcher.ap-directory-watcher.credentials/username)
      PASSWORD=$(cat /etc/svc-events/secrets/directory-watcher.ap-directory-watcher.credentials/password)

      echo "Running migrations..."

      DATABASE_URL="postgres://$USERNAME:$PASSWORD@ap-directory-watcher:5432/directory_watcher" \
        dbmate --migrations-dir "${migrations}" migrate
      '';
      runtimeInputs = [ package pkgs.coreutils ];
    };
    in 
      pkgs.dockerTools.streamLayeredImage {
          name = "svc-directory-watcher-migrations";
          tag = tag;
          contents = [
            migrateScript
          ];
          config.Cmd = [ "${migrateScript}/bin/migrate" ];
      }