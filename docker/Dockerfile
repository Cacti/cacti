FROM php:8.4-fpm-bookworm

# rrdtool 1.7.x from Debian meets Cacti's minimum requirement. 1.9+ (source
# build) enables graph tooltip output via graphv — not worth the image size
# trade-off for a dev environment.

RUN apt-get update && apt-get install -y --no-install-recommends \
        apache2 \
        rrdtool \
        librrd-dev \
        snmp \
        libsnmp-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libgmp-dev \
        libldap2-dev \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
        default-mysql-client \
        cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd gmp intl ldap mbstring \
        pdo pdo_mysql mysqli \
        snmp pcntl posix sockets xml \
        opcache \
    && pecl install apcu && docker-php-ext-enable apcu \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache modules for php-fpm proxying
RUN a2enmod rewrite headers proxy proxy_fcgi setenvif \
    && a2dismod mpm_prefork \
    && a2enmod mpm_event

COPY docker/apache.conf /etc/apache2/sites-available/cacti.conf
RUN a2dissite 000-default && a2ensite cacti

# Static PHP settings (runtime-tunable values are set in entrypoint.sh)
RUN { \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 10000'; \
        echo 'opcache.revalidate_freq = 2'; \
        echo 'opcache.fast_shutdown = 1'; \
        echo 'apc.enabled = 1'; \
        echo 'apc.shm_size = 128M'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/cacti-opcache.ini

RUN { \
        echo '[global]'; \
        echo 'error_log = /proc/self/fd/2'; \
        echo '[www]'; \
        echo 'user = www-data'; \
        echo 'group = www-data'; \
        echo 'listen = 127.0.0.1:9000'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 10'; \
        echo 'pm.start_servers = 2'; \
        echo 'pm.min_spare_servers = 1'; \
        echo 'pm.max_spare_servers = 5'; \
        echo 'access.log = /proc/self/fd/2'; \
        echo 'catch_workers_output = yes'; \
        echo 'decorate_workers_output = no'; \
    } > /usr/local/etc/php-fpm.d/zz-docker.conf

RUN mkdir -p /var/www/html/cacti/cache \
             /var/www/html/cacti/rra \
             /var/www/html/cacti/log \
    && chown -R www-data:www-data /var/www/html/cacti

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# App code comes from the docker-compose bind mount, not from this image.
WORKDIR /var/www/html/cacti

HEALTHCHECK --interval=30s --timeout=10s --start-period=90s --retries=3 \
    CMD pgrep -x apache2 > /dev/null && pgrep -x php-fpm > /dev/null

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
