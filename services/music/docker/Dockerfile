FROM rust:1.66.0-slim-bullseye AS build

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*

COPY . /opt/music/
WORKDIR /opt/music/services/music/
RUN cargo build --release

FROM debian:bullseye-slim

RUN apt-get update && apt-get install --no-install-recommends -y libssl1.1=1.1.1n-0+deb11u3 && rm -rf /var/lib/apt/lists/*

COPY --from=build /opt/music/services/music/target/release/svc-music /opt/music/
COPY services/music/runtime.configuration.json /etc/ap/

ENTRYPOINT ["/opt/music/svc-music"]
