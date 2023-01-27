FROM rust:1.67.0-slim-bullseye AS build

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*

COPY . /opt/directory-watcher/
WORKDIR /opt/directory-watcher/services/directory-watcher/

RUN cargo build --release

FROM debian:bullseye-slim

RUN apt-get update && apt-get install --no-install-recommends -y libssl1.1=1.1.1n-0+deb11u3 && rm -rf /var/lib/apt/lists/*

COPY --from=build /opt/directory-watcher/services/directory-watcher/target/release/directory-watcher /opt/directory-watcher/
COPY services/directory-watcher/runtime.configuration.json /etc/ap/

# todo remove this once we actually mount something
RUN mkdir /tmp/a/ \
    && touch /tmp/a/a \
    && mkdir /tmp/a/b/ \
    && touch /tmp/a/b/c

ENTRYPOINT ["/opt/directory-watcher/directory-watcher"]
