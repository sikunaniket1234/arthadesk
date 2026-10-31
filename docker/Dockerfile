FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    bcmath \
    mbstring \
    intl \
    opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache virtualhost
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

EXPOSE 80
CMD ["apache2-foreground"]
