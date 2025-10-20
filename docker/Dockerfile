FROM php:8.4-cli-alpine3.22 AS ext-builder

# Install build dependencies
RUN apk add --no-cache --virtual .build-deps \
        wget git gettext gettext-dev icu-dev zip unzip ffmpeg libpng-dev libwebp-dev libzip-dev \
        libcurl curl-dev nano build-base autoconf make gcc g++ libc-dev linux-headers \
        pcre-dev libtool libxml2-dev oniguruma-dev icu-libs imagemagick-dev imagemagick bash

# PHP extensions
RUN docker-php-ext-install mysqli
RUN docker-php-ext-install curl
RUN docker-php-ext-install gettext
RUN docker-php-ext-install sockets
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install zip
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install intl
RUN docker-php-ext-install opcache
RUN docker-php-ext-install gd

# Imagick
RUN git clone https://github.com/Imagick/imagick.git --depth 1 /tmp/imagick && \
    cd /tmp/imagick && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    cd / && \
    rm -rf /tmp/imagick

# Igbinary, APCu, Redis
RUN pecl install igbinary apcu redis

# Enable extensions
RUN docker-php-ext-enable imagick igbinary apcu redis

# Final image
FROM php:8.4-cli-alpine AS php

# Install runtime dependencies only
RUN apk add --no-cache \
        wget git gettext icu-libs zip unzip ffmpeg libpng libwebp libzip libcurl curl nano \
        imagemagick \
        openssh-client \
        tzdata \
        nodejs npm \
        cronie

COPY docker/core/php.ini "$PHP_INI_DIR/php.ini"
COPY docker/core/php-staging.ini "$PHP_INI_DIR/php-staging.ini"

# Copy built extensions from builder
COPY --from=ext-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=ext-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Enable extensions (in case conf.d is not enough)
RUN docker-php-ext-enable mysqli curl gettext sockets pdo_mysql zip pcntl intl opcache gd imagick igbinary apcu redis

# Setup gettext languages (Alpine way)
RUN apk add --no-cache --virtual .locale-build musl-locales musl-locales-lang && \
    echo "export LANG=en_US.UTF-8" >> /etc/profile

FROM ghcr.io/roadrunner-server/velox:latest AS roadrunner

ARG APP_VERSION="1.0.0"
ARG VERSION="v2025.1.4"

COPY docker/velox_rr.toml .

ENV CGO_ENABLED=0
RUN --mount=type=secret,id=RT_TOKEN,env=RT_TOKEN \
    vx build -c velox_rr.toml -o /usr/bin/

FROM node:current-slim AS node_base
FROM composer:latest AS composer
FROM php AS project

LABEL Author="Tomáš Vojík"
LABEL Name="Laser Arena Control"
LABEL Vendor="Laser Liga"
LABEL cz.laserliga.author="Tomáš Vojík"
LABEL cz.laserliga.contact="info@laserliga.cz"

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node_base /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node_base /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm || true

# Install PNPM (official method for Docker)
RUN npm install -g corepack
RUN corepack enable  \
    && corepack prepare pnpm@latest --activate
# Project files
RUN mkdir -p "/var/www/.npm" && chown -R 33:33 "/var/www/.npm"

# Move to project directory
WORKDIR /var/www/

# Initialize git and download project
RUN git init \
    && mkdir -p modules \
    && git remote add origin https://github.com/Heroyt/LaserArenaControl.git \
    && git fetch --all --tags \
    && git checkout -t origin/stable \
    && git switch stable \
    && git config pull.ff only --autostash \
    && git pull \
    && git submodule init && git submodule update --init --recursive --remote \
    && git pull --recurse-submodules=yes

# Initialize all configs and create necessary directories
RUN mv private/docker-config.ini private/config.ini
RUN mkdir -p logs
RUN mkdir -p temp
RUN mkdir -p lmx
RUN mkdir -p lmx/results
RUN mkdir -p lmx/games

ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy shell scripts
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Initialize crontab
RUN crontab -l | { cat; echo "* * * * * /usr/local/bin/php /var/www/bin/scheduler.php >> /var/www/logs/cron.log 2>&1"; } | crontab -

RUN chmod 0777 -R logs
RUN chmod 0777 -R temp

SHELL ["/bin/sh", "-c"]

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

# HEALTHCHECK for RoadRunner status plugin
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget --spider --quiet http://localhost:2114/health || exit 1

# Start command
ENTRYPOINT ["/usr/local/bin/start.sh"]
CMD ["/usr/local/bin/start.sh"]
