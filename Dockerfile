FROM php:apache

# INSTALL ZIP TO USE COMPOSER
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    unzip \
    libpng-dev
# https://stackoverflow.com/questions/61228386/installing-gd-extension-in-docker
RUN docker-php-ext-configure gd

RUN docker-php-ext-install zip mysqli gd

# INSTALL AND UPDATE COMPOSER
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer self-update

WORKDIR /usr/local/lib/php
# INSTALL YOUR DEPENDENCIES
RUN composer require phpmailer/phpmailer
RUN composer require php-amqplib/php-amqplib
RUN composer require RobThree/TwoFactorAuth
RUN composer require endroid/qr-code
RUN composer require bacon/bacon-qr-code

WORKDIR /var/www/html
