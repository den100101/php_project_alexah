
FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libonig-dev \
    && docker-php-ext-install mysqli mbstring \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
