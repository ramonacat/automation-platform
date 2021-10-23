FROM rust:1.53-alpine

RUN apk add --no-cache openssl-dev libc-dev curl
RUN cargo install refinery_cli
RUN curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/download/v1.12.1/dbmate-linux-amd64
RUN chmod +x /usr/local/bin/dbmate

ADD scripts/migrate.sh /opt/svc-events-migrations/migrate.sh
ADD migrations/ /opt/svc-events-migrations/migrations
WORKDIR /opt/svc-events-migrations
CMD ["sh", "/opt/svc-events-migrations/migrate.sh"]