FROM php:8.3-cli
RUN apt-get update && apt-get install -y curl git unzip && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install zip
WORKDIR /var/www
COPY . .
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader
RUN apt-get update && apt-get install -y nodejs npm && rm -rf /var/lib/apt/lists/*
RUN npm install && npm run build
RUN mkdir -p storage/logs && chmod -R 775 storage bootstrap/cache
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
