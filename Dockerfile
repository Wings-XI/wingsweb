FROM php:7.4-apache

# INSTALL ZIP TO USE COMPOSER
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    unzip
RUN docker-php-ext-install zip mysqli

# INSTALL AND UPDATE COMPOSER
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer self-update

WORKDIR /usr/local/lib/php
# INSTALL YOUR DEPENDENCIES
RUN composer require phpmailer/phpmailer
RUN composer require php-amqplib/php-amqplib
RUN composer require RobThree/TwoFactorAuth

WORKDIR /var/www/html
