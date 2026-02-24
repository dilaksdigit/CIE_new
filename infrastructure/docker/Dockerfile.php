FROM php:8.3-cli
RUN docker-php-ext-install pdo_mysql

WORKDIR /app

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy app
COPY . .

# Install dependencies
RUN composer install --no-interaction

# Create required directories
RUN mkdir -p storage/cache storage/logs && chmod -R 777 storage

# Start built-in server on port 8000
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]
