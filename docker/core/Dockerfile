FROM node:19-slim AS node_base
FROM php:8.2-fpm-bullseye as setup

#COPY --from=node_base / /
COPY --from=node_base /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node_base /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

# Setup
RUN apt-get update
RUN apt-get -y install apt-utils wget git build-essential gettext cron zlib1g-dev

#RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY php.ini "$PHP_INI_DIR/php.ini"
COPY php-staging.ini "$PHP_INI_DIR/php-staging.ini"

# SPX
WORKDIR /home
RUN git clone https://github.com/NoiseByNorthwest/php-spx.git
WORKDIR /home/php-spx
RUN git checkout release/latest
RUN phpize
RUN ./configure
RUN make
RUN make install
RUN docker-php-ext-enable spx

COPY spx.ini "$PHP_INI_DIR/conf.d/docker-php-ext-spx.ini"

FROM setup as extensions

# PHP extensions
RUN apt-get install -y libzip-dev unzip libicu-dev cifs-utils ffmpeg
RUN apt install -y curl libcurl4-openssl-dev
RUN docker-php-ext-install mysqli curl gettext sockets pdo_mysql zip pcntl intl opcache
RUN docker-php-ext-enable mysqli curl gettext sockets pdo_mysql zip pcntl intl opcache

RUN apt-get -y install sudo

# APCu cache
RUN pecl install apcu
RUN docker-php-ext-enable apcu

# Redis
RUN pecl install redis
RUN docker-php-ext-enable redis

FROM extensions as langs

# Setup gettext languages
RUN apt-get install -y locales \
	&& sed -i -e 's/# cs_CZ.UTF-8 UTF-8/cs_CZ.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# de_DE.UTF-8 UTF-8/de_DE.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# fr_FR.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# sk_SK.UTF-8 UTF-8/sk_SK.UTF-8 UTF-8/' /etc/locale.gen \
	&& sed -i -e 's/# ru_RU.UTF-8 UTF-8/ru_RU.UTF-8 UTF-8/' /etc/locale.gen \
    && dpkg-reconfigure --frontend=noninteractive locales
RUN locale-gen cs_CZ.UTF-8
RUN update-locale -y

FROM langs as composer

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === 'e21205b207c3ff031906575712edab6f13eb0b361f2085f1f1237b7126d785e826a450292b6cfd1d64d92e6563bbde02') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN rm composer-setup.php
RUN mv composer.phar /usr/local/bin/composer