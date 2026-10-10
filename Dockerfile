FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite for front routing
RUN a2enmod rewrite

# Configure Apache DocumentRoot to point to html
WORKDIR /var/www/html

# Copy application source files
COPY ./src /var/www/html/

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
