FROM rust:1.56-slim-bullseye AS planner

RUN cargo install cargo-chef

COPY . /opt/events/
WORKDIR /opt/events/services/events/

RUN cargo chef prepare --recipe-path recipe.json

FROM rust:1.56-slim-bullseye AS cacher

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1k-1+deb11u1 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*
RUN cargo install cargo-chef

WORKDIR /opt/events/services/events/

COPY --from=planner /opt/events/services/events/recipe.json recipe.json
COPY libraries/rust/platform/ /opt/events/libraries/rust/platform
RUN cargo chef cook --release --recipe-path recipe.json

FROM rust:1.56-slim-bullseye AS build

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1k-1+deb11u1 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*

COPY . /opt/events/
WORKDIR /opt/events/services/events/
COPY --from=cacher /opt/events/services/events/target target
COPY --from=cacher /usr/local/cargo /usr/local/cargo

RUN cargo build --release

FROM debian:bullseye-slim

RUN apt-get update && apt-get install --no-install-recommends -y libssl1.1=1.1.1k-1+deb11u1 && rm -rf /var/lib/apt/lists/*

COPY schemas/events.schema.json /etc/svc-events/schemas/events.schema.json
COPY --from=build /opt/events/services/events/target/release/events /opt/events/

ENTRYPOINT ["/opt/events/events"]