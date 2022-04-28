FROM node:17.2.0-slim AS node_base
FROM php:8.1.1-apache as setup

COPY --from=node_base / /

# Setup
RUN apt-get update && apt update && apt-get -y install apt-utils wget git build-essential gettext cron

# Apache
SHELL ["/bin/bash", "-c"]
RUN ln -s ../mods-available/{expires,headers,rewrite}.load /etc/apache2/mods-enabled/
RUN sed -e '/<Directory \/var\/www\/>/,/<\/Directory>/s/AllowOverride None/AllowOverride All/' -i /etc/apache2/apache2.conf
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# PHP extensions
RUN apt-get install -y libzip-dev unzip
RUN apt install -y curl libcurl4-openssl-dev
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN docker-php-ext-install curl && docker-php-ext-enable curl
RUN docker-php-ext-install gettext && docker-php-ext-enable gettext
RUN docker-php-ext-install sockets && docker-php-ext-enable sockets
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql
RUN docker-php-ext-install zip && docker-php-ext-enable zip
RUN docker-php-ext-install pcntl && docker-php-ext-enable pcntl
RUN apt-get install -y libicu-dev
RUN docker-php-ext-install intl && docker-php-ext-enable intl
RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y cifs-utils

FROM setup as langs

RUN apt-get update
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
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" &&\
    php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" &&\
    php composer-setup.php &&\
    php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

FROM composer as project

COPY cron /etc/cron.d/lac-cron
RUN chmod 0644 /etc/cron.d/lac-cron &&\
    crontab /etc/cron.d/lac-cron
COPY cron.sh /var/www/html/cron.sh
RUN chmod +x /var/www/html/cron.sh

# Project files
RUN mkdir -p "/var/www/.npm" && chown -R 33:33 "/var/www/.npm"
RUN apt-get -y install sudo
RUN echo "www-data:pass" | chpasswd && adduser www-data sudo
RUN cron
USER www-data
RUN echo "Cloning..."
#RUN git clone https://github.com/Heroyt/LaserArenaControl.git /var/www/html/

WORKDIR /var/www/html/

RUN git init && git remote add origin https://github.com/Heroyt/LaserArenaControl.git && git fetch && git checkout -t origin/master
RUN git config pull.ff only
RUN git pull --recurse-submodules
RUN git submodule init
RUN git submodule update
RUN git submodule update --init --recursive --remote

RUN mv private/docker-config.ini private/config.ini
RUN mkdir -p logs
RUN mkdir -p temp
RUN mkdir -p lmx
RUN mkdir -p lmx/results
RUN mkdir -p lmx/games

RUN composer update

COPY start.sh .
COPY resultsCheck.sh .

COPY cron.txt .

RUN crontab cron.txt

CMD git pull --recurse-submodules && git submodule update --init --recursive --remote && composer build && php install.php && sh ./start.sh