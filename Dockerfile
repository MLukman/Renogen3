ARG PHP_VERSION=7.4
FROM php:${PHP_VERSION}-apache AS symfony_php

RUN apt-get update && apt-get install -y libldap2-dev libonig-dev libicu-dev wget vim git zip unzip \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install -j$(nproc) ldap mbstring pdo pdo_mysql opcache intl \
    && apt-get autoremove -y libldap2-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

ARG APCU_VERSION=5.1.18
RUN pecl install apcu-${APCU_VERSION} \
    && docker-php-ext-enable apcu \
    && pecl clear-cache \
    && a2enmod rewrite \
    && wget -O /usr/local/bin/dumb-init --no-verbose https://github.com/Yelp/dumb-init/releases/download/v1.2.2/dumb-init_1.2.2_amd64 \
    && chmod +x /usr/local/bin/dumb-init

RUN echo 'TLS_REQCERT never' >> /etc/ldap/ldap.conf \
    && echo 'upload_max_filesize = 100M' > /usr/local/etc/php/conf.d/max.ini \
    && echo 'post_max_size = 120M' >> /usr/local/etc/php/conf.d/max.ini \
    && wget -O /usr/local/etc/cacert.pem --no-verbose https://curl.haxx.se/ca/cacert.pem \
    && echo 'curl.cainfo=/usr/local/etc/cacert.pem' > /usr/local/etc/php/conf.d/openssl_cacert.ini \
    && echo 'openssl.cafile=/usr/local/etc/cacert.pem' >> /usr/local/etc/php/conf.d/openssl_cacert.ini \
    && sed -i 's_DocumentRoot /var/www/html_DocumentRoot /var/www/html/public_' /etc/apache2/sites-enabled/000-default.conf

HEALTHCHECK CMD sleep 10 && curl -sSf http://localhost/healthcheck.php || exit 1

ENTRYPOINT ["/usr/local/bin/dumb-init", "--"]

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
# install Symfony Flex globally to speed up download of Composer packages (parallelized prefetching)
RUN set -eux; \
	composer global require "symfony/flex" --prefer-dist --no-progress --no-suggest --classmap-authoritative; \
	composer clear-cache
ENV PATH="${PATH}:/root/.composer/vendor/bin"

# build for production
ARG APP_ENV=prod
# Allow to use development versions of Symfony
ARG STABILITY="stable"
ENV STABILITY ${STABILITY:-stable}

COPY composer.* /var/www/html/
COPY bin /var/www/html/bin

RUN set -eux \
	&& mkdir -p var/cache var/log && chown -R www-data:www-data var/ \
	&& composer install --prefer-dist --no-progress --no-scripts --no-interaction \
	&& chown -R www-data:www-data vendor/

COPY --chown=www-data:www-data . /var/www/html/
COPY --chown=www-data:www-data .env /var/www/html/
RUN sed -i 's#APP_ENV=dev#APP_ENV=prod#' .env \
	&& composer dump-autoload --classmap-authoritative \
	&& chmod +x init_renogen.sh bin/*

# Database info
ENV DB_HOST=localhost
ENV DB_PORT=3306
ENV DB_NAME=renogen
ENV DB_USER=renogen
ENV DB_PASSWORD=reno123gen
# Or one string connection string using format mysql://user:password@host:port/name
ENV DATABASE_URL=

# RECAPTCHA SITE & SECRET KEYS
ENV GOOGLE_RECAPTCHA_SITE_KEY=''
ENV GOOGLE_RECAPTCHA_SECRET=''

# Timezone using Region/City format
ENV TZ=Asia/Kuala_Lumpur

# If behind reverse proxy using path (e.g. /renogen), put the path here (without the preceding slash, e.g renogen). 
# Also ensure the reverse proxy retains the path when proxying to this container.
ENV BASE_PATH=''

CMD bash -c '. ./init_renogen.sh && apache2-foreground'
