FROM rust:1.56-slim-buster AS planner

RUN cargo install cargo-chef

ADD . /opt/directory-watcher/
WORKDIR /opt/directory-watcher/services/directory-watcher/

RUN cargo chef prepare --recipe-path recipe.json

FROM rust:1.56-slim-buster AS cacher

RUN apt-get update && apt-get install -y libssl-dev pkg-config && rm -rf /var/lib/apt/lists/*
RUN cargo install cargo-chef

WORKDIR /opt/directory-watcher/services/directory-watcher/

COPY --from=planner /opt/directory-watcher/services/directory-watcher/recipe.json recipe.json
COPY libraries/rust/platform/ /opt/directory-watcher/libraries/rust/platform
RUN cargo chef cook --release --recipe-path recipe.json

FROM rust:1.56-slim-buster AS build

RUN apt-get update && apt-get install -y libssl-dev pkg-config && rm -rf /var/lib/apt/lists/*

ADD . /opt/directory-watcher/
WORKDIR /opt/directory-watcher/services/directory-watcher/
COPY --from=cacher /opt/directory-watcher/services/directory-watcher/target target
COPY --from=cacher /usr/local/cargo /usr/local/cargo

RUN cargo build --release

FROM debian:buster-slim

RUN apt-get update && apt-get install -y libssl1.1 pkg-config && rm -rf /var/lib/apt/lists/*

ADD schemas/events.schema.json /etc/svc-directory-watcher/schemas/events.schema.json
COPY --from=build /opt/directory-watcher/services/directory-watcher/target/release/directory-watcher /opt/directory-watcher/
ADD services/directory-watcher/runtime.configuration.json /etc/ap/

# todo remove this once we actually mount something
RUN mkdir /tmp/a/ \
    && touch /tmp/a/a \
    && mkdir /tmp/a/b/ \
    && touch /tmp/a/b/c

ENTRYPOINT ["/opt/directory-watcher/directory-watcher"]