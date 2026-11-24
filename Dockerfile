FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite headers \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/edusmart-security.conf \
    && a2enconf edusmart-security

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/health || exit 1
