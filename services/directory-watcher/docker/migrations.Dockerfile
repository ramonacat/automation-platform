FROM alpine:3

RUN apk add --no-cache curl
RUN curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/download/v1.12.1/dbmate-linux-amd64
RUN chmod +x /usr/local/bin/dbmate

ADD scripts/migrate.sh /opt/svc-directory-watcher-migrations/migrate.sh
ADD migrations/ /opt/svc-directory-watcher-migrations/migrations
WORKDIR /opt/svc-directory-watcher-migrations
CMD ["sh", "/opt/svc-directory-watcher-migrations/migrate.sh"]