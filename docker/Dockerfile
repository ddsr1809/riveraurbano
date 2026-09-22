FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers expires \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
COPY . /var/www/html/
RUN mkdir -p /var/www/html/cache && chown -R www-data:www-data /var/www/html/cache
