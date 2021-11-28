FROM php:8.0-apache

# Apache
SHELL ["/bin/bash", "-c"]
RUN ln -s ../mods-available/{expires,headers,rewrite}.load /etc/apache2/mods-enabled/
RUN sed -e '/<Directory \/var\/www\/>/,/<\/Directory>/s/AllowOverride None/AllowOverride All/' -i /etc/apache2/apache2.conf
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# PHP extensions
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN docker-php-ext-install gettext && docker-php-ext-enable gettext
RUN docker-php-ext-install sockets && docker-php-ext-enable sockets
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql
RUN apt-get update && apt-get upgrade -y

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" &&\
    php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" &&\
    php composer-setup.php &&\
    php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

# Project files
USER www-data
COPY --chown=www-data . /var/www/html
RUN mv /var/www/html/private/docker-config.ini /var/www/html/private/config.ini
RUN mkdir -p /var/www/html/logs
RUN mkdir -p /var/www/html/temp
#RUN chown -R www-data:www-data /var/www/html/
#RUN chmod -R 0777 /var/www/html/temp && rm -R /var/www/html/temp/*