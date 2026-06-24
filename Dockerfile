FROM php:8.4-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install dom pdo pdo_mysql mysqli

RUN pecl install pcov \
    && docker-php-ext-enable pcov \
    && echo "pcov.enabled=0" > /usr/local/etc/php/conf.d/pcov.ini \
    && echo "pcov.directory=/var/www/html/src" >> /usr/local/etc/php/conf.d/pcov.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
