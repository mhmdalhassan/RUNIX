FROM php:8.3-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    nginx \
    mysql-client \
    redis \
    composer \
    npm \
    nodejs \
    git \
    curl \
    autoconf \
    build-base && \
    docker-php-ext-install \
        pdo \
        pdo_mysql \
        session \
        fileinfo \
        tokenizer \
        dom \
        mbstring \
        curl \
        zip

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader && npm install && npm run build

RUN mkdir -p storage/logs && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
