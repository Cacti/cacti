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

FROM php:8.2-apache

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
	# sqlite3 ships built-in on php:8.2-apache (php -m already lists it) and its
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

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/cacti-entrypoint"]
CMD ["apache2-foreground"]
