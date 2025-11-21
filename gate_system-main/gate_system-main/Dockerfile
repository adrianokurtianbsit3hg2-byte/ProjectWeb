FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite

# Install PHP extensions you may need
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy all files from this folder into Apache’s web root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose port 80
EXPOSE 80

