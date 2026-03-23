FROM php:8.2-apache

RUN apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install curl

COPY . /var/www/html/

RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80add .
