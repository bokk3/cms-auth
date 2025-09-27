FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    && docker-php-ext-enable pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/cms-auth.conf \
    && a2enconf cms-auth

# PHP Configuration
RUN echo 'session.save_handler = files\n\
session.save_path = "/tmp"\n\
session.gc_maxlifetime = 1800\n\
memory_limit = 256M\n\
upload_max_filesize = 50M\n\
post_max_size = 50M\n\
max_execution_time = 300\n\
date.timezone = "UTC"' > /usr/local/etc/php/conf.d/cms-auth.ini

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/login.php || exit 1

EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]