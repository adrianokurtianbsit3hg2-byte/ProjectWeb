# Use official PHP image with Apache
FROM php:8.2-apache

# Enable Apache mod_rewrite (often needed for routing)
RUN a2enmod rewrite

# Install required PHP extensions (curl, mbstring, etc.)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project files into container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose port 80
EXPOSE 80
