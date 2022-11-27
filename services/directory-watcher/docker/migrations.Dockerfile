FROM alpine:3

RUN apk add --no-cache curl=7.86.0-r1
RUN curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/download/v1.15.0/dbmate-linux-amd64 \
    && chmod +x /usr/local/bin/dbmate

COPY scripts/migrate.sh /opt/svc-directory-watcher-migrations/migrate.sh
COPY migrations/ /opt/svc-directory-watcher-migrations/migrations
WORKDIR /opt/svc-directory-watcher-migrations
CMD ["sh", "/opt/svc-directory-watcher-migrations/migrate.sh"]
