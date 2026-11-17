FROM php:8.3-apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public COMPOSER_ALLOW_SUPERUSER=1
RUN apt-get update && apt-get install -y --no-install-recommends unzip libxml2-dev libonig-dev libpq-dev \
 && docker-php-ext-install pdo_pgsql mbstring dom \
 && a2enmod rewrite headers expires \
 && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && mkdir -p storage/logs storage/uploads \
 && chown -R www-data:www-data storage vendor \
 && printf '\n<Directory /var/www/html/public>\n AllowOverride All\n Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf
EXPOSE 80
CMD ["apache2-foreground"]
