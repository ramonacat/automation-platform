FROM rust:1.62.1-slim-bullseye AS planner

RUN cargo install cargo-chef

COPY . /opt/music/
WORKDIR /opt/music/services/music/

RUN cargo chef prepare --recipe-path recipe.json

FROM rust:1.62.1-slim-bullseye AS cacher

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*
RUN cargo install cargo-chef

WORKDIR /opt/music/services/music/

COPY --from=planner /opt/music/services/music/recipe.json recipe.json
COPY libraries/rust/platform/ /opt/music/libraries/rust/platform
COPY libraries/rust/events/ /opt/music/libraries/rust/events
COPY libraries/rust/music/ /opt/music/libraries/rust/music
COPY libraries/rust/rpc-support/ /opt/music/libraries/rust/rpc-support
COPY tools/message-compiler/ /opt/music/tools/message-compiler

RUN cargo chef cook --release --recipe-path recipe.json

FROM rust:1.62.1-slim-bullseye AS build

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*

COPY . /opt/music/
WORKDIR /opt/music/services/music/
COPY --from=cacher /opt/music/services/music/target target
COPY --from=cacher /usr/local/cargo /usr/local/cargo

RUN cargo build --release

FROM debian:bullseye-slim

RUN apt-get update && apt-get install --no-install-recommends -y libssl1.1=1.1.1n-0+deb11u3 && rm -rf /var/lib/apt/lists/*

COPY --from=build /opt/music/services/music/target/release/svc-music /opt/music/
COPY services/music/runtime.configuration.json /etc/ap/

ENTRYPOINT ["/opt/music/svc-music"]
