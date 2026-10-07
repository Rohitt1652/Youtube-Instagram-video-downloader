FROM php:8.3-fpm-bookworm

# Working directory
WORKDIR /var/www/html

# Non-interactive apt
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies, Nginx, Supervisor, FFmpeg, Python3, and envsubst
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    ffmpeg \
    python3 \
    python3-pip \
    python3-venv \
    gettext-base \
    curl \
    git \
    unzip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install official PHP extension installer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install required PHP extensions
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
    pdo_mysql \
    pdo_sqlite \
    curl \
    mbstring \
    xml \
    zip \
    bcmath \
    opcache

# Install latest yt-dlp standalone binary globally & link python
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp && \
    chmod a+rx /usr/local/bin/yt-dlp && \
    ln -sf /usr/bin/python3 /usr/bin/python

# Verify binaries exist
RUN yt-dlp --version && ffmpeg -version

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy configuration files
COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy full application codebase
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Expose HTTP port
EXPOSE 80 10000

# Container entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
