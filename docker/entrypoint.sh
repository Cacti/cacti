#!/bin/bash
set -e

# Write runtime PHP settings from environment
cat > /usr/local/etc/php/conf.d/cacti-runtime.ini <<INI
date.timezone = ${TIMEZONE:-UTC}
memory_limit = ${PHP_MEMORY_LIMIT:-512M}
max_execution_time = 60
INI

# Ensure volume directories have correct ownership
chown -R www-data:www-data /var/www/html/cacti/cache \
                           /var/www/html/cacti/rra \
                           /var/www/html/cacti/log 2>/dev/null || true

# Start php-fpm in background
php-fpm -D

# Run Apache in foreground
exec apachectl -D FOREGROUND
