FROM php:8.3-apache-bookworm
RUN php -r 'if (version_compare(PHP_VERSION, "8.3.36", "<")) { fwrite(STDERR, "PHP 8.3.36 or newer is required for security fixes.\n"); exit(1); }'
COPY deploy/docker/security.ini /usr/local/etc/php/conf.d/zz-security.ini
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libyaml-dev libonig-dev libxml2-dev \
 && docker-php-ext-install -j"$(nproc)" bcmath mbstring pdo_mysql zip opcache \
 && pecl install redis yaml && docker-php-ext-enable redis yaml \
 && a2enmod rewrite && rm -rf /var/lib/apt/lists/*
RUN apt-get update && apt-get install -y --no-install-recommends libgmp-dev && docker-php-ext-install gmp && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
 && cp config/appprofile.example.php config/appprofile.php \
 && mkdir -p storage/framework/smarty/compile storage/framework/smarty/cache \
 && chown -R www-data:www-data storage
COPY deploy/docker/config.php config/.config.php
COPY deploy/docker/apache.conf /etc/apache2/sites-available/000-default.conf
