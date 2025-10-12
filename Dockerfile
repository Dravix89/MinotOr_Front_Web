FROM php:8.2-apache

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev

RUN docker-php-ext-install pdo pdo_mysql zip intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite

# Dossier de config Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# 🔥 Ajouter cette ligne pour que Apache écoute le port 8000
RUN echo "Listen 8001" >> /etc/apache2/ports.conf

COPY . .

RUN composer install --no-interaction --optimize-autoloader

RUN a2enmod rewrite
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 8001

CMD ["apache2-foreground"]
