FROM php:apache

RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    && docker-php-ext-install mysqli pdo_mysql zip
