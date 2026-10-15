FROM php:8.3-apache

# pdo_mysql para o banco; gd e exif para reduzir e endireitar as fotos (JPG, PNG e WebP).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd exif \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite remoteip

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/operacoes.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY . /var/www/html
RUN mkdir -p /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/storage

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
