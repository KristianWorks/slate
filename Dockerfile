# Slate — production image
# PHP 8.2 + Apache 2.4 with /public as the document root and .htaccess overrides enabled.
#
# Build:  docker build -t slate-app .
# Run:    docker run --rm -p 8080:80 --env-file .env slate-app

FROM php:8.2-apache

# ---- OS packages -----------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# ---- PHP extensions --------------------------------------------------------
RUN docker-php-ext-install pdo pdo_mysql zip

# ---- Apache modules + vhost config -----------------------------------------
RUN a2enmod rewrite headers

# Point Apache's DocumentRoot at /public and allow .htaccess overrides
# everywhere under /var/www/html (so the security headers + rewrite rules in
# /public/.htaccess actually take effect).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# ---- Composer --------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Install vendor first for layer caching — only invalidates when composer.json
# or composer.lock change.
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist --no-autoloader

# ---- Application source ----------------------------------------------------
COPY . .

# Regenerate autoloader with all source files present.
RUN composer dump-autoload --no-dev --optimize

# ---- Writable paths --------------------------------------------------------
RUN mkdir -p storage/cache/ratelimit storage/logs public/uploads \
 && chown -R www-data:www-data storage public/uploads \
 && chmod -R 775 storage public/uploads

# ---- Production-grade php.ini ----------------------------------------------
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

EXPOSE 80

# The base image already declares CMD ["apache2-foreground"].
