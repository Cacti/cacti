#!/usr/bin/env bash
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

set -euo pipefail

DB_HOST="${CACTI_DB_HOST:-mariadb}"
DB_NAME="${CACTI_DB_NAME:-cacti}"
DB_USER="${CACTI_DB_USER:-cactiuser}"
DB_PASS="${CACTI_DB_PASS:-cactiuser}"
DB_ROOT_PASS="${CACTI_DB_ROOT_PASS:-cactiroot}"

# Wait for MariaDB to accept connections before we seed the schema. Some
# MariaDB/MySQL clients default to TLS certificate verification, so a plain
# ping against the private test container can fail indefinitely. --skip-ssl
# disables that handshake for this container-to-container link.
until mysqladmin ping -h "$DB_HOST" -uroot -p"$DB_ROOT_PASS" --skip-ssl --silent; do
	echo "waiting for mariadb at ${DB_HOST}..."
	sleep 2
done

# Point Cacti at the linked database. sed in place keeps the file simple.
# url_path is forced to '/' because the container serves Cacti from the Apache
# document root, not a /cacti/ subdirectory. The dist default of '/cacti/'
# makes CACTI_PATH_URL emit asset and redirect paths the e2e stack 404s on,
# including the htmx script src the specs assert against.
sed -i \
	-e "s|\$database_type.*=.*|\$database_type = 'mysql';|" \
	-e "s|\$database_default.*=.*|\$database_default = '${DB_NAME}';|" \
	-e "s|\$database_hostname.*=.*|\$database_hostname = '${DB_HOST}';|" \
	-e "s|\$database_username.*=.*|\$database_username = '${DB_USER}';|" \
	-e "s|\$database_password.*=.*|\$database_password = '${DB_PASS}';|" \
	-e "s|\$database_port.*=.*|\$database_port = '3306';|" \
	-e "s|\$url_path.*=.*|\$url_path = '/';|" \
	/var/www/html/include/config.php

# Seed the schema on first boot. The second boot will skip because tables
# already exist.
if ! mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" \
		-e "SELECT 1 FROM settings LIMIT 1" >/dev/null 2>&1; then
	echo "seeding cacti schema..."
	mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" < /var/www/html/cacti.sql

	# Mark the install complete so the UI does not redirect to the installer.
	mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" -e \
		"REPLACE INTO settings (name, value) VALUES ('install_complete', '1')"

	# cacti.sql ships version.cacti as the 'new_install' sentinel, which
	# is_install_needed() reads as older than the develop target and so forces
	# the installer redirect. Stamp the version row with CACTI_DEV_VERSION (the
	# constant the comparison runs against) so the login page renders instead.
	dev_version="$(php -r "require '/var/www/html/include/global_constants.php'; if (!defined('CACTI_DEV_VERSION')) { fwrite(STDERR, 'CACTI_DEV_VERSION is not defined' . PHP_EOL); exit(1); } echo CACTI_DEV_VERSION;")"
	if [ -z "$dev_version" ]; then
		echo "CACTI_DEV_VERSION resolved to an empty value" >&2
		exit 1
	fi
	mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" -e \
		"UPDATE version SET cacti = '${dev_version}'"

	# Seed the htmx_enabled toggle so the helper returns the enabled path.
	mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" -e \
		"REPLACE INTO settings (name, value) VALUES ('htmx_enabled', 'on')"
fi

# Cacti writes to log/, rra/, resource/, cache/. The image build drops log/
# and cache/ via .dockerignore, so recreate any missing directory before the
# chown. global.php aborts with a FATAL when log/cacti.log is not writable,
# which suppresses the login page the htmx specs assert against.
mkdir -p /var/www/html/log /var/www/html/rra /var/www/html/resource /var/www/html/cache
chown -R www-data:www-data /var/www/html/log /var/www/html/rra /var/www/html/resource /var/www/html/cache \
	2>/dev/null || true

exec "$@"
