FROM php:7.4-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid

COPY etc/apt/debian_contrib.list /etc/apt/sources.list.d/debian_contrib.list


RUN mkdir -p /usr/share/man/man1

# Install system dependencies
RUN apt-get -qq update && apt-get -qqy install \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    default-jre

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd dom

RUN pecl install xdebug-2.9.5 \
    && docker-php-ext-enable xdebug

RUN pecl install redis \
    && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

RUN curl --fail -q -L https://downloads.apache.org/pdfbox/2.0.24/pdfbox-app-2.0.24.jar > /usr/share/java/pdfbox.jar
# Set working directory
WORKDIR /var/www

USER $user