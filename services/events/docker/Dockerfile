FROM php:8.0.8-cli

RUN apt-get update && apt-get install -y libpq-dev libzip-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install -j$(nproc) pdo_pgsql sockets zip

ADD . /opt/events/

WORKDIR /opt/events/

# todo separate stage for composer
ADD ./docker/composer.sh /usr/local/bin/install-composer
RUN chmod +x /usr/local/bin/install-composer \
    && install-composer \
    && rm /usr/local/bin/install-composer \
    && mv composer.phar /usr/local/bin/composer

RUN composer install --prefer-dist --no-dev

CMD ["php", "bin/start.php"]