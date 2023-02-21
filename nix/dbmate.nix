let
  pkgs = import <nixpkgs> { }; 
  packageSource = pkgs.fetchFromGitHub {
    owner = "amacneil";
    repo = "dbmate";
    rev = "v1.16.2";
    sha256 = "sha256-5hjAP2+0hbYcA9G7YJyRqqp1ZC8LzFDomjeFjl4z4FY=";
  };
  in
  pkgs.buildGoModule {
    name = "dbmate";
    src = packageSource;
    vendorSha256 = "sha256-7fC1jJMY/XK+GX5t2/o/k+EjFxAlRAmiemMcWaZhL9o=";
    doCheck = false;

  }