FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        git \
        unzip \
        sqlite \
        sqlite-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring zip intl bcmath pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["sh", "-c", "if [ -f artisan ]; then php artisan serve --host=0.0.0.0 --port=8000; else tail -f /dev/null; fi"]
