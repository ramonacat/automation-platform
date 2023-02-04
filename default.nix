{}:
let
    pkgs = import <nixpkgs> { };
in
  pkgs.mkShell {
    shellHook = ''
      rustup toolchain install nightly; 
      rustup default stable; 
      cargo +nightly install cargo-udeps; 
      cargo install cargo-audit; 
      cargo install cargo-llvm-cov;
      mkdir .php-tools/;
      pushd .php-tools;
        rm composer.json composer.lock;
        composer require icanhazstring/composer-unused;
        composer require maglnet/composer-require-checker;
      popd;
      export PATH=$(pwd)/.php-tools/vendor/bin:$PATH;
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
        ];
  }