{}:
let
    pkgs = import <nixpkgs> { };
    fetchCrate = name: version: fetchTarball "https://crates.io/api/v1/crates/${name}/${version}/download#${name}-${version}.tar.gz";
    llvmCovSrc = fetchCrate "cargo-llvm-cov" "0.5.9";
    llvmCov = pkgs.rustPlatform.buildRustPackage {
      name = "cargo-llvm-cov";
      src = llvmCovSrc;
      cargoHash = "sha256-ZUeqW8Hr4leu1TlBEoiBy8eKXs8NunU3hX/sW8v2cts=";

      # skip tests which require llvm-tools-preview
      checkFlags = [
        "--skip bin_crate"
        "--skip cargo_config"
        "--skip clean_ws"
        "--skip instantiations"
        "--skip merge"
        "--skip merge_failure_mode_all"
        "--skip no_test"
        "--skip open_report"
        "--skip real1"
        "--skip show_env"
        "--skip virtual1"
      ];
    };
    instaSrc = fetchCrate "cargo-insta" "1.28.0";
    insta = pkgs.rustPlatform.buildRustPackage {
      name = "cargo-insta";
      src = instaSrc;
      cargoHash = "sha256-el60bwblYSGz9hqPzGseNhXmKAF5/hedKWcZWZjj7tg=";
    };
in
  pkgs.mkShell {
    shellHook = ''
      export NIX_ENFORCE_PURITY=0;

      rustup toolchain install nightly; 
      rustup default stable; 

      cargo install cargo-audit; 

      mkdir .php-tools/;
      pushd .php-tools;
        rm composer.json composer.lock;
        composer require --dev icanhazstring/composer-unused;
        composer require --dev maglnet/composer-require-checker;
      popd;

      export PATH=$(pwd)/.php-tools/vendor/bin:$PATH;
      unset NIX_ENFORCE_PURITY;
    '';
    packages = 
      let 
        php = pkgs.php81.buildEnv {
          extensions = ({enabled, all}: enabled ++ (
            with all; [
              pcov
            ]
          ));
          extraConfig = "memory_limit = 2G";
        };
        crate2nix = fetchTarball "https://github.com/kolloch/crate2nix/tarball/master";
      in
        with pkgs; 
        [ 
          php
          php81Packages.composer 
          rustup 
          pkgconfig 
          openssl 
          alsa-lib 
          (callPackage crate2nix { })
          llvmCov
          cargo-udeps
          cargo-audit
          insta
          grype
        ];
  }