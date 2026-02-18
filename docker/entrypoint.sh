#!/usr/bin/env bash
set -euo pipefail

cat > /usr/local/etc/php/conf.d/cacti-runtime.ini <<INI
date.timezone = ${TIMEZONE:-UTC}
memory_limit = ${PHP_MEMORY_LIMIT:-512M}
max_execution_time = 60
INI

for dir in /var/www/html/cacti/cache \
           /var/www/html/cacti/rra \
           /var/www/html/cacti/log; do
    [ -d "$dir" ] && chown -R www-data:www-data "$dir"
done

if [ ! -f /var/www/html/cacti/include/config.php ]; then
    cat > /var/www/html/cacti/include/config.php <<'PHPCONFIG'
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$database_type     = 'mysql';
PHPCONFIG

    # Reject quotes/backslashes that would break the PHP string literals below
    for _var in DB_NAME DB_HOST DB_USER DB_PASS DB_PORT; do
        _val="${!_var:-}"
        if [[ "$_val" =~ [\'\\] ]]; then
            echo "ERROR: $_var contains unsafe characters (single quote or backslash)" >&2
            exit 1
        fi
    done

    cat >> /var/www/html/cacti/include/config.php <<PHPCONFIG
\$database_default  = '${DB_NAME:-cacti}';
\$database_hostname = '${DB_HOST:-localhost}';
\$database_username = '${DB_USER:-cacti}';
\$database_password = '${DB_PASS:-cacti}';
\$database_port     = '${DB_PORT:-3306}';
PHPCONFIG

    cat >> /var/www/html/cacti/include/config.php <<'PHPCONFIG'
$database_retries  = 5;
$database_ssl      = false;
$database_persist  = false;
$poller_id         = 1;
$url_path          = '/cacti/';

$cacti_session_name = 'Cacti';
PHPCONFIG

    chown www-data:www-data /var/www/html/cacti/include/config.php
fi

cat > /etc/cron.d/cacti-poller <<CRON
*/5 * * * * www-data php /var/www/html/cacti/poller.php >> /proc/1/fd/1 2>> /proc/1/fd/2
CRON

chmod 0644 /etc/cron.d/cacti-poller
cron

php-fpm -D

exec apachectl -D FOREGROUND
