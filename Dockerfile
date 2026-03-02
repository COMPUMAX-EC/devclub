FROM php:8.1-fpm

WORKDIR /var/www/html

# System deps
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy app
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 9000

CMD ["php-fpm"]
