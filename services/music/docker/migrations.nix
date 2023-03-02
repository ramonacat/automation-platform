{tag ? "latest"}: 
  let
    pkgs = import <nixpkgs> { }; 
    package = import ../../../nix/dbmate.nix;
    migrations = ../migrations;
    migrateScript = pkgs.writeShellApplication {
      name = "migrate";
      text = ''
      echo "Reading secrets..."

      USERNAME=$(cat /etc/svc-events/secrets/music.ap-music.credentials/username)
      PASSWORD=$(cat /etc/svc-events/secrets/music.ap-music.credentials/password)

      echo "Running migrations..."

      DATABASE_URL="postgres://$USERNAME:$PASSWORD@ap-music:5432/music" \
        dbmate --migrations-dir "${migrations}" migrate
      '';
      runtimeInputs = [ package pkgs.coreutils ];
    };
    in 
      pkgs.dockerTools.streamLayeredImage {
          name = "svc-music-migrations";
          fromImage = null;
          tag = tag;
          contents = [
            migrateScript
          ];
          config.Cmd = [ "${migrateScript}/bin/migrate" ];
      }