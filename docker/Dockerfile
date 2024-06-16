FROM php:8.3-cli as php

# Setup
RUN apt-get update  \
    && apt-get -y install  \
        apt-utils  \
        wget  \
        git  \
        build-essential  \
        gettext  \
        cron  \
        zlib1g-dev \
        libzip-dev \
        unzip \
        libicu-dev \
        cifs-utils \
        ffmpeg \
        libpng-dev \
        libwebp-dev \
        curl \
        libcurl4-openssl-dev \
        nano \
    && rm -rf /var/lib/apt/lists/* # Clear apt cache

#RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/core/php.ini "$PHP_INI_DIR/php.ini"
COPY docker/core/php-staging.ini "$PHP_INI_DIR/php-staging.ini"

# PHP extensions
RUN docker-php-ext-install  \
    mysqli  \
    curl  \
    gettext  \
    sockets  \
    pdo_mysql  \
    zip  \
    pcntl  \
    intl  \
    opcache  \
    gd

# Imagick
RUN apt-get update &&  \
    apt-get install -y libmagickwand-dev --no-install-recommends &&  \
    rm -rf /var/lib/apt/lists/*  # Clear apt cache

# Build imagick from source (PHP 8.3)
RUN git clone https://github.com/Imagick/imagick.git --depth 1 /tmp/imagick && \
    cd /tmp/imagick && \
    git fetch origin master && \
    git switch master && \
    cd /tmp/imagick && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    cd / && \
    rm -rf /tmp/imagick # Clean up sources

# Igbinary
RUN pecl install igbinary

# Enable extensions
RUN docker-php-ext-enable  \
    mysqli  \
    curl  \
    gettext  \
    sockets  \
    pdo_mysql  \
    zip  \
    pcntl  \
    intl  \
    opcache  \
    imagick  \
    igbinary
RUN docker-php-ext-configure  \
    gd --with-jpeg --with-freetype --with-webp &&  \
    docker-php-ext-install gd

# APCu cache
RUN pecl install apcu
RUN docker-php-ext-enable apcu

# Redis
RUN pecl install redis
RUN docker-php-ext-enable redis

# Setup gettext languages
RUN apt-get update &&  \
    apt-get install -y locales locales-all &&  \
    rm -rf /var/lib/apt/lists/* # Clear apt cache
RUN sed -i -e 's/# cs_CZ.UTF-8 UTF-8/cs_CZ.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# de_DE.UTF-8 UTF-8/de_DE.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# fr_FR.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# sk_SK.UTF-8 UTF-8/sk_SK.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# ru_RU.UTF-8 UTF-8/ru_RU.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# es_ES.UTF-8 UTF-8/es_ES.UTF-8 UTF-8/' /etc/locale.gen \
    && dpkg-reconfigure --frontend=noninteractive locales \
    && locale-gen cs_CZ.UTF-8 \
    && update-locale

FROM golang:1.22.4 as golang
FROM ghcr.io/roadrunner-server/velox:latest as roadrunner

COPY --from=golang /usr/local/go/ /usr/local/go/

ENV APP_VERSION="1.0.0"
ARG BUILD_TIME="$(date +%FT%T%z)"
ENV VERSION="v2024.1.4"
ARG RT_TOKEN="undefined"

COPY docker/velox_rr_2024.toml .

ENV CGO_ENABLED=0
RUN vx build -c velox_rr_2024.toml -o /usr/bin/

FROM node:current-slim AS node_base
FROM composer:latest AS composer
FROM php AS project

LABEL Author="Tomáš Vojík"
LABEL Name="Laser Arena Control"
LABEL Vendor="Laser Liga"
LABEL cz.laserliga.author="Tomáš Vojík"
LABEL cz.laserliga.contact="info@laserliga.cz"

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY --from=node_base /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node_base /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

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
RUN mkdir -p lmx/gamess

ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy shell scripts
COPY start.sh start.sh

# Initialize crontab
RUN crontab -l | { cat; echo "* * * * * /usr/local/bin/php /var/www/bin/scheduler.php >> /var/www/logs/cron.log 2>&1"; } | crontab -

RUN chmod 0777 -R logs
RUN chmod 0777 -R temp

SHELL ["/bin/bash"]

# Start command
# Updates project, builds it and runs a start script which starts WS event server and Apache
ENTRYPOINT ["sh", "./start.sh"]
CMD ["sh", "./start.sh"]