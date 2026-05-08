FROM php:8.4-cli-alpine AS ext-builder

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# PHP extensions and their transient build dependencies are handled by the installer.
RUN install-php-extensions \
        mysqli \
        curl \
        gettext \
        sockets \
        pdo_mysql \
        zip \
        pcntl \
        intl \
        opcache \
        gd \
        imagick \
        igbinary \
        apcu \
        redis

# Final image
FROM php:8.4-cli-alpine AS php

# Install runtime dependencies only
RUN apk add --no-cache \
        wget git gettext icu-libs zip unzip ffmpeg \
        freetype libjpeg-turbo libpng libwebp libavif libxpm \
        libzip libcurl curl lz4-libs nano \
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

# Setup gettext languages (Alpine way)
RUN apk add --no-cache --virtual .locale-build musl-locales musl-locales-lang && \
    echo "export LANG=en_US.UTF-8" >> /etc/profile

FROM ghcr.io/roadrunner-server/velox:latest AS roadrunner

ARG APP_VERSION="1.0.1"
ARG VERSION="v2025.1.13"

COPY docker/velox_rr.toml .

ENV CGO_ENABLED=0
ENV GOTOOLCHAIN=auto
# Temporary workaround for go.work support in Velox until it's officially supported
RUN mv /usr/local/go/bin/go /usr/local/go/bin/go-real && \
    printf '%s\n' \
        '#!/bin/sh' \
        'if [ -f go.work ]; then' \
        '  /usr/local/go/bin/go-real work use' \
        'fi' \
        'exec /usr/local/go/bin/go-real "$@"' \
        > /usr/local/go/bin/go && \
    chmod +x /usr/local/go/bin/go
RUN --mount=type=secret,id=RT_TOKEN \
    export GITHUB_TOKEN=$(cat /run/secrets/RT_TOKEN) && \
    vx build -c velox_rr.toml -o /usr/bin/

FROM node:current-alpine AS node_base
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
    && git remote add origin https://github.com/Heroyt/LaserArenaControl.git
RUN git fetch --all --tags \
    && git config pull.ff only --autostash
#    && git checkout -t origin/stable \
#    && git switch stable \
#    && git pull \
#    && git submodule init && git submodule update --init --recursive --remote \
#    && git pull --recurse-submodules=yes

# Initialize all configs and create necessary directories
RUN mkdir -p logs && \
    mkdir -p temp && \
    mkdir -p lmx && \
    mkdir -p lmx6 && \
    mkdir -p private && \
    mkdir -p lmx/results && \
    mkdir -p lmx/games
COPY private/docker-config.ini private/config.ini

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
