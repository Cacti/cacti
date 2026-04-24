# PHP-FPM image for the Cacti E2E stack. PHP 7.4 matches the 1.2.x branch
# floor. Vendor tree is already committed under include/vendor, so Composer
# is intentionally not installed here.

FROM php:7.4-fpm-alpine

LABEL org.opencontainers.image.title="cacti-e2e-php" \
      org.opencontainers.image.source="https://github.com/Cacti/cacti" \
      org.opencontainers.image.description="PHP-FPM runtime for Cacti Playwright E2E stack (CSP nonce pilot)"

# Build-time packages for the ext compile, then runtime packages. Split into
# two groups so the build-deps can be dropped in a single --virtual cleanup.
RUN set -eux; \
    apk add --no-cache \
        bash \
        mariadb-client \
        fcgi \
        libpng \
        libxml2 \
        icu-libs \
        gmp \
        libldap \
        net-snmp-libs \
        freetype \
        libjpeg-turbo; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libxml2-dev \
        icu-dev \
        gmp-dev \
        openldap-dev \
        net-snmp-dev \
        freetype-dev \
        libjpeg-turbo-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        mbstring \
        xml \
        gd \
        intl \
        gmp \
        ldap \
        snmp \
        sockets; \
    apk del --no-network .build-deps; \
    rm -rf /var/cache/apk/*

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html/cacti

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
