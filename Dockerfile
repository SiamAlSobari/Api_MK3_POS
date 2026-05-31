# ==========================================
# Stage 1: Build Frontend Assets (Vite)
# ==========================================
FROM node:20-alpine AS assets-builder

WORKDIR /app

# Copy package configurations
COPY package.json package-lock.json vite.config.js ./

# Install npm dependencies
RUN npm ci

# Copy resource files for compilation
COPY resources/ ./resources/
COPY public/ ./public/

# Build assets (output into public/build)
RUN npm run build

# ==========================================
# Stage 2: PHP-FPM Application Server
# ==========================================
FROM php:8.3-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install production dependencies and build tools
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    bash \
    curl \
    git \
    unzip

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        pdo_mysql \
        zip \
        gd \
        bcmath \
        opcache \
        intl

# Copy custom configurations
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Get latest Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install PHP production dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy the rest of the application files
COPY . .

# Copy compiled assets from Stage 1
COPY --from=assets-builder /app/public/build ./public/build

# Generate optimized autoloader files
RUN composer dump-autoload --optimize --no-dev

# Backup public directory for syncing at runtime to a volume
RUN mkdir -p /var/www_backup && cp -R /var/www/public /var/www_backup/public

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Setup entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Set entrypoint and default command
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
