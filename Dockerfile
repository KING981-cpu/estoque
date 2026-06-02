FROM php:8.2-apache

RUN apt-get update && apt-get install -y zlib1g-dev libzip-dev unzip && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html

RUN sed -i 's|DocumentRoot /var/www/html$|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN echo '<Directory /var/www/html/public>' >> /etc/apache2/apache2.conf && \
    echo '    AllowOverride All' >> /etc/apache2/apache2.conf && \
    echo '    Require all granted' >> /etc/apache2/apache2.conf && \
    echo '</Directory>' >> /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/html
