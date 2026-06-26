FROM node:22-alpine AS node-build
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
ARG APP_ENV=production
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-alpine
WORKDIR /var/www/html

ARG APP_ENV=production

# System dependencies
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    curl \
    jq \
    openjdk21-jre-headless \
    imagemagick \
    ghostscript \
    ghostscript-fonts \
    && apk add --no-cache --virtual .build-deps \
        imagemagick-dev \
    && install-php-extensions \
        imagick \
        pdo_mysql \
        pdo_sqlite \
        redis \
        pcntl \
        opcache \
        zip \
    && apk del .build-deps

# ImageMagick policy to allow PDF processing
COPY docker/imagemagic_policy.xml /tmp/imagemagick-policy.xml
RUN for dir in /etc/ImageMagick-6 /etc/ImageMagick-7; do \
      mkdir -p "$dir" && cp /tmp/imagemagick-policy.xml "$dir/policy.xml"; \
    done && rm /tmp/imagemagick-policy.xml

# PDFBox jar (latest 3.x via Apache projects API)
COPY docker/pdfbox/install_pdfbox.sh /tmp/install_pdfbox.sh
RUN mkdir -p /usr/share/java \
    && bash /tmp/install_pdfbox.sh \
    && rm /tmp/install_pdfbox.sh

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Create all directories Laravel needs before any PHP/composer commands run
RUN mkdir -p \
    bootstrap/cache \
    database/seeds \
    database/factories \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    storage/app/thumbnails

# PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .
COPY --from=node-build /var/www/html/public/build public/build
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && rm .env

# Permissions
# database/ is chowned non-recursively so PHP files (migrations/seeds/factories)
# stay root-owned and non-writable; only the dir + sqlite file need www-data access.
RUN chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache \
    && chown www-data:www-data database \
    && chmod 775 database \
    && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite \
    && chmod 664 database/database.sqlite

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data

ENV OCTANE_PORT=8000
EXPOSE ${OCTANE_PORT}

ARG APP_VERSION=dev
ARG APP_PR_NUMBER=
ARG APP_BRANCH=

ENV APP_VERSION=$APP_VERSION
ENV APP_PR_NUMBER=$APP_PR_NUMBER
ENV APP_BRANCH=$APP_BRANCH

LABEL org.opencontainers.image.version=$APP_VERSION \
      org.opencontainers.image.revision=$APP_PR_NUMBER \
      org.opencontainers.image.ref.name=$APP_BRANCH

ENTRYPOINT ["/entrypoint.sh"]
