FROM alpine:3

RUN apk add --no-cache curl=7.87.0-r0
RUN curl -fsSL -o /usr/local/bin/dbmate https://github.com/amacneil/dbmate/releases/download/v1.15.0/dbmate-linux-amd64 \
    && chmod +x /usr/local/bin/dbmate

COPY scripts/migrate.sh /opt/svc-music/migrate.sh
COPY migrations/ /opt/svc-music/migrations
WORKDIR /opt/svc-music

CMD ["sh", "/opt/svc-music/migrate.sh"]
