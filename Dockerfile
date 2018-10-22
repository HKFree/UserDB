FROM php:7.2.11-apache-stretch

RUN a2enmod rewrite
RUN a2enmod ssl
RUN a2enmod headers

RUN apt-get update

# Install extenstions: MySQL PDO, GD
RUN apt-get install -y \
        libpq-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libmcrypt-dev \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install pdo pdo_mysql

# Enable and configure xdebug
#RUN pecl install xdebug
#RUN docker-php-ext-enable xdebug

#RUN echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
##RUN echo "xdebug.remote_connect_back=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN echo "xdebug.remote_host=172.17.0.1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini


RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/log.ini
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/log.ini
RUN echo "zend.multibyte = On" >> /usr/local/etc/php/conf.d/mb.ini

RUN echo "date.timezone = Europe/Prague" > /usr/local/etc/php/conf.d/timezone.ini

COPY apache.conf /etc/apache2/sites-enabled/000-default.conf

RUN echo "<?php header('Location: /userdb/');" > /var/www/html/index.php

WORKDIR /opt/userdb
