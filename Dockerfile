FROM php:8.2-apache

# تثبيت المكتبات المطلوبة لـ PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    curl

RUN docker-php-ext-install pdo pdo_pgsql pgsql

# تفعيل mod_rewrite لـ Apache
RUN a2enmod rewrite

# نسخ ملفات المشروع
COPY . /var/www/html/
WORKDIR /var/www/html

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# صلاحيات storage و cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80