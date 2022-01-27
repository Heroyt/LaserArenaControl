FROM node:17.2.0-slim AS node_base
FROM php:8.1.1-apache as setup

COPY --from=node_base / /

# Setup
RUN apt-get update && apt update && apt-get -y install apt-utils wget git build-essential

# Apache
SHELL ["/bin/bash", "-c"]
RUN ln -s ../mods-available/{expires,headers,rewrite}.load /etc/apache2/mods-enabled/
RUN sed -e '/<Directory \/var\/www\/>/,/<\/Directory>/s/AllowOverride None/AllowOverride All/' -i /etc/apache2/apache2.conf
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# PHP extensions
RUN apt-get install -y libzip-dev unzip
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN docker-php-ext-install gettext && docker-php-ext-enable gettext
RUN docker-php-ext-install sockets && docker-php-ext-enable sockets
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql
RUN docker-php-ext-install zip && docker-php-ext-enable zip
RUN apt-get install -y libicu-dev
RUN docker-php-ext-install intl && docker-php-ext-enable intl
RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y cifs-utils

#

FROM setup as composer

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" &&\
    php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" &&\
    php composer-setup.php &&\
    php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

FROM composer as project

# Project files
RUN mkdir -p "/var/www/.npm" && chown -R 33:33 "/var/www/.npm"
USER www-data
RUN git clone https://github.com/Heroyt/LaserArenaControl.git /var/www/html/

WORKDIR /var/www/html/

RUN mv private/docker-config.ini private/config.ini
RUN mkdir -p logs
RUN mkdir -p temp

RUN composer build

CMD git pull && composer build && php install.php && apache2-foreground