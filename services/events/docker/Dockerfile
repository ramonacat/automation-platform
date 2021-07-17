FROM php:8.0.8-cli-alpine

RUN apk --no-cache add postgresql-dev=13.3-r0
RUN docker-php-ext-install -j$(nproc) pdo_pgsql

ADD . /opt/events/

WORKDIR /opt/events/

CMD ["php", "bin/start.php"]