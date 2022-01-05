FROM php:7.4-fpm

LABEL maintainer="Nicholas Avenell <nicholas@istic.net>"

# Arguments defined in docker-compose.yml
ARG user
ARG uid

COPY docker/apt/debian_contrib.list /etc/apt/sources.list.d/debian_contrib.list

# RUN set -x \
#         && deluser www-data \
#         && addgroup -g 500 -S www-data \
#         && adduser -u 500 -D -S -G www-data www-data

COPY docker/php /usr/local/etc/php-fpm.d/

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
    jq \
    libmagickwand-dev \
    default-jre

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd dom sockets

RUN pecl install xdebug-2.9.5 \
    && docker-php-ext-enable xdebug

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN getent passwd $user || useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user


RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# RUN mkdir -p /home/$user/lockfiles && \
#     chown -R $user:$user /home/$user/lockfiles


# RUN chown -R $user:$user /var/run/thalium

COPY docker/bin/install_pdfbox.sh /usr/local/bin/install_pdfbox.sh
RUN bash /usr/local/bin/install_pdfbox.sh
# Set working directory
WORKDIR /var/www

USER $user