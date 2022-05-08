FROM alpine:3

RUN apk add --no-cache curl=7.80.0-r1
RUN curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/download/v1.12.1/dbmate-linux-amd64 \
    && chmod +x /usr/local/bin/dbmate

COPY scripts/migrate.sh /opt/svc-events-migrations/migrate.sh
COPY migrations/ /opt/svc-events-migrations/migrations
WORKDIR /opt/svc-events-migrations
CMD ["sh", "/opt/svc-events-migrations/migrate.sh"]
