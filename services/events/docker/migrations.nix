{tag ? "latest"}: 
  let
    pkgs = import <nixpkgs> { }; 
    package = import ../../../nix/dbmate.nix;
    migrations = ../migrations;
    migrateScript = pkgs.writeShellApplication {
      name = "migrate";
      text = ''
      echo "Reading secrets..."

      USERNAME=$(cat /etc/svc-events/secrets/events.ap-events.credentials/username)
      PASSWORD=$(cat /etc/svc-events/secrets/events.ap-events.credentials/password)

      echo "Running migrations..."

      DATABASE_URL="postgres://$USERNAME:$PASSWORD@ap-events:5432/events" \
        dbmate --migrations-dir "${migrations}" migrate
      '';
      runtimeInputs = [ package pkgs.coreutils ];
    };
    in 
      pkgs.dockerTools.streamLayeredImage {
          name = "svc-events-migrations";
          tag = tag;
          contents = [
            migrateScript
          ];
          config.Cmd = [ "${migrateScript}/bin/migrate" ];
      }