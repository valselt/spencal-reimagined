# Gunakan image PHP versi stabil dengan Apache
FROM php:8.2-apache

# Install ekstensi MySQLi (Wajib untuk koneksi database)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Aktifkan mod_rewrite Apache (Opsional, bagus untuk pengembangan kedepan)
RUN a2enmod rewrite

# Salin semua file project ke dalam container
COPY . /var/www/html/

# Ubah hak akses agar Apache bisa membaca file
RUN chown -R www-data:www-data /var/www/html