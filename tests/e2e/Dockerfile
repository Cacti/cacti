# +-------------------------------------------------------------------------+
# | Copyright (C) 2004-2026 The Cacti Group                                 |
# |                                                                         |
# | This program is free software; you can redistribute it and/or           |
# | modify it under the terms of the GNU General Public License             |
# | as published by the Free Software Foundation; either version 2          |
# | of the License, or (at your option) any later version.                  |
# +-------------------------------------------------------------------------+
# | Cacti: The Complete RRDtool-based Graphing Solution                     |
# +-------------------------------------------------------------------------+
# | http://www.cacti.net/                                                   |
# +-------------------------------------------------------------------------+

FROM php:8.3-apache AS runtime

ENV DEBIAN_FRONTEND=noninteractive

# System packages Cacti needs at runtime plus build-time extras for PHP
# extensions (gd, intl, ldap, gmp, snmp, mysqli). mariadb-client is kept so
# the entrypoint can seed the schema before Apache forks.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		ca-certificates \
		cron \
		curl \
		fping \
		git \
		libfreetype6-dev \
		libgmp-dev \
		libicu-dev \
		libjpeg-dev \
		libldap2-dev \
		libpng-dev \
		libsnmp-dev \
		libxml2-dev \
		libsqlite3-dev \
		libzip-dev \
		mariadb-client \
		rrdtool \
		snmp \
		unzip \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	# sqlite3 ships built-in on php:8.3-apache (php -m already lists it) and its
	# ext/sqlite3 only carries config0.m4, not config.m4, so docker-php-ext-install
	# can't build it standalone; pdo_sqlite stays since libsqlite3-dev backs it.
	&& docker-php-ext-install -j"$(nproc)" \
		gd \
		gmp \
		intl \
		ldap \
		mysqli \
		pcntl \
		pdo_mysql \
		pdo_sqlite \
		snmp \
		sockets \
		zip \
	&& apt-get clean \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy repo contents; .dockerignore trims node_modules, .git, worktrees, etc.
COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist --no-dev \
	&& chown -R www-data:www-data /var/www/html \
	&& a2enmod rewrite \
	&& cp include/config.php.dist include/config.php

COPY tests/e2e/entrypoint.sh /usr/local/bin/cacti-entrypoint
RUN chmod +x /usr/local/bin/cacti-entrypoint

# Optional instrumentation for the disposable test environment.
ARG CACTI_TEST_COVERAGE=0
RUN if [ "$CACTI_TEST_COVERAGE" = 1 ]; then \
        pecl install xdebug-3.5.3 \
        && docker-php-ext-enable xdebug \
        && printf '%s\n' 'xdebug.mode=coverage' 'auto_prepend_file=/var/www/html/tests/Helpers/RequestCoverage.php' > /usr/local/etc/php/conf.d/cacti-test-coverage.ini \
        && install -d -o www-data -g www-data -m 0750 /tmp/cacti-pr-coverage; \
    fi

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/cacti-entrypoint"]
CMD ["apache2-foreground"]

# Build managed assets inside the image; do not copy generated package source
# into the repository just to run a profile from a clean checkout.
FROM node:22-bookworm-slim AS profile-assets
WORKDIR /assets
COPY package.json package-lock.json ./
COPY build/ build/
RUN npm ci

# Cache the extension independently of source changes.
FROM php:8.3-apache AS profiler-extension
RUN pecl install xdebug-3.5.3 \
	&& docker-php-ext-enable xdebug

# Optional local profiling image. Regular E2E builds use the final stage below.
FROM runtime AS profiling
COPY --from=profile-assets /assets/include/js/ /var/www/html/include/js/
COPY --from=profiler-extension /usr/local/lib/php/extensions/no-debug-non-zts-20230831/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-20230831/xdebug.so
RUN docker-php-ext-enable xdebug \
	&& printf '%s\n' 'xdebug.mode=off' 'xdebug.use_compression=0' > /usr/local/etc/php/conf.d/zz-profile.ini

FROM runtime AS e2e
