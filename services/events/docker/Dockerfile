FROM rust:1.62.1-slim-bullseye AS planner

RUN cargo install cargo-chef

COPY . /opt/events/
WORKDIR /opt/events/services/events/

RUN cargo chef prepare --recipe-path recipe.json

FROM rust:1.62.1-slim-bullseye AS cacher

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*
RUN cargo install cargo-chef

WORKDIR /opt/events/services/events/

COPY --from=planner /opt/events/services/events/recipe.json recipe.json
COPY libraries/rust/platform/ /opt/events/libraries/rust/platform
COPY libraries/rust/events/ /opt/events/libraries/rust/events
COPY libraries/rust/rpc-support/ /opt/events/libraries/rust/rpc-support
COPY tools/message-compiler/ /opt/events/tools/message-compiler

RUN cargo chef cook --release --recipe-path recipe.json

FROM rust:1.62.1-slim-bullseye AS build

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*

COPY . /opt/events/
WORKDIR /opt/events/services/events/
COPY --from=cacher /opt/events/services/events/target target
COPY --from=cacher /usr/local/cargo /usr/local/cargo

RUN cargo build --release

FROM debian:bullseye-slim

RUN apt-get update && apt-get install --no-install-recommends -y libssl1.1=1.1.1n-0+deb11u3 && rm -rf /var/lib/apt/lists/*

COPY --from=build /opt/events/services/events/target/release/svc-events /opt/events/events

ENTRYPOINT ["/opt/events/events"]
