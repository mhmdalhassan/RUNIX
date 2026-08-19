FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    mysql-client \
    redis \
    git \
    curl \
    npm \
    nodejs \
    icu-libs \
    libzip

# Install PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    icu-dev \
    zip \
    libzip-dev && \
    docker-php-ext-install \
        pdo \
        pdo_mysql \
        fileinfo \
        tokenizer \
        dom \
        mbstring \
        intl \
        zip && \
    apk del .build-deps

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN npm install && npm run build

RUN mkdir -p storage/logs && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
