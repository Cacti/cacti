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

FROM node:22-bookworm-slim AS assets
WORKDIR /assets
COPY package.json package-lock.json ./
COPY build/ build/
COPY patches/ patches/
RUN npm ci

FROM runtime AS discovery
WORKDIR /var/www/html
COPY . /var/www/html
RUN composer install --no-interaction --prefer-dist --no-dev \
    && composer check-platform-reqs --no-dev \
    && a2enmod rewrite
COPY --from=assets /assets/include/js/ /var/www/html/include/js/
COPY tests/e2e/discovery/entrypoint.sh /usr/local/bin/discovery-entrypoint
RUN chmod +x /usr/local/bin/discovery-entrypoint
ENTRYPOINT ["/usr/local/bin/discovery-entrypoint"]
CMD ["apache2-foreground"]
