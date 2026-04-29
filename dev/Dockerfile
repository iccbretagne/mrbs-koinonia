FROM php:8.4-apache

RUN a2enmod rewrite
RUN apt-get update && apt-get install -y libicu-dev locales-all \
 && apt-get clean
RUN docker-php-ext-install mysqli pdo pdo_mysql intl

# Activer AllowOverride pour les .htaccess MRBS
COPY mrbs.conf /etc/apache2/sites-available/000-default.conf

# Entrypoint : corrige les permissions des volumes montés puis démarre Apache
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# MRBS source (monté en volume, pas copié — pour dev)
WORKDIR /var/www/html
ENTRYPOINT ["/entrypoint.sh"]
