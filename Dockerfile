FROM php:8.2-apache

# 1. Install System Dependencies & GD Library (untuk WebP)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd mysqli

# 3. Enable Mod Rewrite
RUN a2enmod rewrite

# Mengubah AllowOverride None menjadi All agar file .htaccess terbaca
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 4. Setup Working Directory
WORKDIR /var/www/html

# 5. Copy Files
COPY . /var/www/html/

# 7. Permissions
RUN chown -R www-data:www-data /var/www/html