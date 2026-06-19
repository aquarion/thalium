FROM node:22-alpine AS node-deps
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci

FROM dunglas/frankenphp:1-php8.4-alpine
WORKDIR /var/www/html

ARG APP_ENV=production

# System dependencies
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    jq \
    openjdk21-jre-headless \
    imagemagick \
    imagemagick-dev \
    ghostscript \
    ghostscript-fonts \
    && install-php-extensions \
        imagick \
        redis \
        pcntl \
        opcache \
        zip

# PDFBox jar (version 3.x)
RUN mkdir -p /usr/share/java \
    && PDFBOX_VERSION=$(curl -fs https://projects.apache.org/json/projects/pdfbox.json \
         | jq -r '[.release[] | select(.revision | test("^3")) | .revision][0]') \
    && echo "Installing PDFBox ${PDFBOX_VERSION}" \
    && curl -fL "https://dlcdn.apache.org/pdfbox/${PDFBOX_VERSION}/pdfbox-app-${PDFBOX_VERSION}.jar" \
         -o /usr/share/java/pdfbox.jar

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Node dependencies + Vite build
COPY --from=node-deps /var/www/html/node_modules node_modules
COPY . .
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan package:discover --ansi \
    && APP_ENV=$APP_ENV npm run build \
    && rm .env \
    && rm -rf node_modules

# Permissions
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
             storage/logs storage/app/public storage/app/thumbnails bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache

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
