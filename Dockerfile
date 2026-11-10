# ==============================================================================
# Stage 1: Frontend Asset Builder (Node 22 + PHP CLI for post-build CSS fixup)
# ==============================================================================
FROM php:8.4-cli-bookworm AS assets

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build

# ==============================================================================
# Stage 2: Production PHP 8.4 + Apache Runtime for Render Free Web Service
# ==============================================================================
FROM php:8.4-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    unzip \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pdo_mysql \
        xml \
        zip \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Production PHP & OPcache hardening
RUN { \
        echo "expose_php = Off"; \
        echo "memory_limit = 256M"; \
        echo "upload_max_filesize = 12M"; \
        echo "post_max_size = 16M"; \
        echo "max_execution_time = 60"; \
        echo "opcache.enable = 1"; \
        echo "opcache.memory_consumption = 128"; \
        echo "opcache.interned_strings_buffer = 16"; \
        echo "opcache.max_accelerated_files = 10000"; \
        echo "opcache.validate_timestamps = 0"; \
    } > /usr/local/etc/php/conf.d/nsd-production.ini

# Configure Apache DocumentRoot to /var/www/html/public and hide server version tokens
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && { \
        echo "ServerTokens Prod"; \
        echo "ServerSignature Off"; \
        echo "<Directory /var/www/html/public>"; \
        echo "    Options -Indexes +FollowSymLinks"; \
        echo "    AllowOverride All"; \
        echo "    Require all granted"; \
        echo "</Directory>"; \
    } > /etc/apache2/conf-enabled/nsd-hardening.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

COPY . /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && chmod +x /var/www/html/docker/render-entrypoint.sh \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["/var/www/html/docker/render-entrypoint.sh"]

