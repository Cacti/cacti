# PHP-FPM image for the Cacti E2E stack. PHP 8.2 matches composer.json's
# "php": ">=8.1" floor (the committed include/vendor tree already requires
# PHP 8 syntax, e.g. jfcherng/php-diff's dependencies). Vendor tree is already
# committed under include/vendor, so Composer is intentionally not installed
# here.

FROM php:8.2.34-fpm-alpine3.24@sha256:67d419a46a0f68727fa387817eabb448db7ffc48ac8b6146adaa2009974324f4

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
        libjpeg-turbo \
        oniguruma; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libxml2-dev \
        icu-dev \
        gmp-dev \
        openldap-dev \
        net-snmp-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        oniguruma-dev \
        linux-headers; \
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

# Plugin checkout for the CSP harness. Defaults pin tested commits; build args
# still accept commits, branches, or tags for compatibility experiments.
# Checkouts go into a
# staging directory; the entrypoint copies them into place under the bind-
# mounted plugins/ tree at first start (the bind mount would otherwise hide
# anything baked into the image).
ARG PLUGIN_THOLD_REPO=https://github.com/Cacti/plugin_thold.git
ARG PLUGIN_THOLD_REF=6bbfb58492b1e8ce2aeee4690659e54ceb4d1226
ARG PLUGIN_MONITOR_REPO=https://github.com/Cacti/plugin_monitor.git
ARG PLUGIN_MONITOR_REF=353766eba575e6952470375550607e14b5a57a23

COPY checkout-plugin.sh /usr/local/lib/cacti-checkout-plugin.sh
RUN set -eux; \
    apk add --no-cache --virtual .plugin-fetch git; \
    mkdir -p /opt/cacti-plugins; \
    sh /usr/local/lib/cacti-checkout-plugin.sh "${PLUGIN_THOLD_REPO}" "${PLUGIN_THOLD_REF}" /opt/cacti-plugins/thold; \
    sh /usr/local/lib/cacti-checkout-plugin.sh "${PLUGIN_MONITOR_REPO}" "${PLUGIN_MONITOR_REF}" /opt/cacti-plugins/monitor; \
    apk del --no-network .plugin-fetch

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html/cacti

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
