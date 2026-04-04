FROM php:8.4-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install PHP extensions for MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Install mbstring dependencies and extension
RUN apt-get update && apt-get install -y libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

# Set Apache document root to project root
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy project files
COPY . /var/www/html/

# Set permissions for uploads directory
RUN mkdir -p /var/www/html/uploads/students \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
