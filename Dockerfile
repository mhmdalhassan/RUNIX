FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx mysql-client redis composer npm nodejs git curl

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader && npm install && npm run build

RUN mkdir -p storage/logs && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
