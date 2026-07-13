FROM php:8.2-apache

# ==============================
# System dependencies
# ==============================
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ==============================
# PHP extensions needed by Laravel + MySQL
# ==============================
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    xml \
    exif \
    pcntl

# ==============================
# Fix: Apache MPM conflict (keep only prefork, required by mod_php)
# ==============================
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
           /etc/apache2/mods-enabled/mpm_event.conf \
           /etc/apache2/mods-enabled/mpm_worker.load \
           /etc/apache2/mods-enabled/mpm_worker.conf
RUN a2enmod mpm_prefork
RUN a2enmod rewrite

# ==============================
# Composer
# ==============================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ==============================
# App files
# ==============================
WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# ==============================
# Permissions
# ==============================
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ==============================
# Point Apache DocumentRoot to Laravel's public folder
# ==============================
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's|/var/www/|/var/www/html/public|g' /etc/apache2/apache2.conf

# Allow .htaccess overrides (needed for Laravel routing)
RUN sed -i '/<Directory \/var\/www\/html\/public>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf || true

EXPOSE 80

# ==============================
# Run migrations automatically on each deploy, then start Apache
# ==============================
CMD php artisan config:cache && php artisan migrate --force && apache2-foreground
