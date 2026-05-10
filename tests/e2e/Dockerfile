# PHP-FPM image for the Cacti E2E stack. PHP 7.4 matches the 1.2.x branch
# floor. Vendor tree is already committed under include/vendor, so Composer
# is intentionally not installed here.

FROM php:7.4-fpm-alpine

LABEL org.opencontainers.image.title="cacti-e2e-php" \
      org.opencontainers.image.source="https://github.com/Cacti/cacti" \
      org.opencontainers.image.description="PHP-FPM runtime for Cacti Playwright E2E stack (CSP nonce pilot)"

# Build-time packages for the ext compile, then runtime packages. Split into
# two groups so the build-deps can be dropped in a single --virtual cleanup.
# `git` is kept in the runtime layer because the plugin clone step below needs
# it; alternative would be to clone in a builder stage, but a single image
# stays simpler for CI debugging.
RUN set -eux; \
    apk add --no-cache \
        bash \
        git \
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
        oniguruma-dev; \
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

# Plugin clone for the CSP harness. Build args let CI pin a branch/tag without
# editing the Dockerfile. Defaults track the 1.2.x development line because
# that matches the Cacti branch this harness exercises. Clones go into a
# staging directory; the entrypoint copies them into place under the bind-
# mounted plugins/ tree at first start (the bind mount would otherwise hide
# anything baked into the image).
ARG PLUGIN_THOLD_REPO=https://github.com/Cacti/plugin_thold.git
ARG PLUGIN_THOLD_REF=develop
ARG PLUGIN_MONITOR_REPO=https://github.com/Cacti/plugin_monitor.git
ARG PLUGIN_MONITOR_REF=develop

RUN set -eux; \
    mkdir -p /opt/cacti-plugins; \
    git clone --depth=1 --branch "${PLUGIN_THOLD_REF}" \
        "${PLUGIN_THOLD_REPO}" /opt/cacti-plugins/thold; \
    git clone --depth=1 --branch "${PLUGIN_MONITOR_REF}" \
        "${PLUGIN_MONITOR_REPO}" /opt/cacti-plugins/monitor; \
    rm -rf /opt/cacti-plugins/thold/.git /opt/cacti-plugins/monitor/.git

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html/cacti

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
