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
set -euo pipefail

COMPOSE_PROJECT="cacti_graph_e2e_$$"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$REPO_DIR/.env.graph-e2e"
WEB_PORT=18081
DB_PORT=13307
CONFIG_EXISTED=0
CONFIG_BACKUP=""

if [ -f "$REPO_DIR/include/config.php" ]; then
	CONFIG_EXISTED=1
	CONFIG_BACKUP="$(mktemp "${TMPDIR:-/tmp}/cacti-config.XXXXXX")"
	cp "$REPO_DIR/include/config.php" "$CONFIG_BACKUP"
	rm -f "$REPO_DIR/include/config.php"
fi

cleanup() {
	cd "$REPO_DIR"
	docker compose -p "$COMPOSE_PROJECT" --env-file "$ENV_FILE" down -v --remove-orphans 2>/dev/null || true
	rm -f "$ENV_FILE"

	if [ "$CONFIG_EXISTED" -eq 1 ] && [ -n "$CONFIG_BACKUP" ] && [ -f "$CONFIG_BACKUP" ]; then
		cp "$CONFIG_BACKUP" "$REPO_DIR/include/config.php"
		rm -f "$CONFIG_BACKUP"
	elif [ "$CONFIG_EXISTED" -eq 0 ]; then
		rm -f "$REPO_DIR/include/config.php"
	fi
}

trap cleanup EXIT

cd "$REPO_DIR"

cat > "$ENV_FILE" <<EOF
WEB_PORT=$WEB_PORT
DB_ROOT_PASSWORD=testroot
DB_NAME=cacti
DB_USER=cacti
DB_PASSWORD=testpass
DB_PORT=$DB_PORT
TIMEZONE=UTC
PHP_MEMORY_LIMIT=256M
DB_MAX_CONNECTIONS=50
DB_BUFFER_POOL_SIZE=256M
EOF

echo "Building graph subsystem Docker test image..."
docker compose -p "$COMPOSE_PROJECT" --env-file "$ENV_FILE" build --quiet

echo "Starting graph subsystem Docker test stack..."
docker compose -p "$COMPOSE_PROJECT" --env-file "$ENV_FILE" up -d

echo "Waiting for Docker test stack..."
TRIES=0
while [ "$TRIES" -lt 36 ]; do
	DB_HEALTH=$(docker compose -p "$COMPOSE_PROJECT" --env-file "$ENV_FILE" ps --format json 2>/dev/null | grep -o '"Health":"[^"]*"' | head -1 | cut -d'"' -f4)
	WEB_HEALTH=$(docker compose -p "$COMPOSE_PROJECT" --env-file "$ENV_FILE" ps --format json 2>/dev/null | grep -o '"Health":"[^"]*"' | tail -1 | cut -d'"' -f4)

	if [ "$DB_HEALTH" = "healthy" ] && [ "$WEB_HEALTH" = "healthy" ]; then
		break
	fi

	sleep 5
	TRIES=$((TRIES + 1))
done

if [ "${DB_HEALTH:-}" != "healthy" ] || [ "${WEB_HEALTH:-}" != "healthy" ]; then
	docker compose -p "$COMPOSE_PROJECT" --env-file "$ENV_FILE" ps
	exit 1
fi

WEB_CONTAINER=$(docker compose -p "$COMPOSE_PROJECT" --env-file "$ENV_FILE" ps -q web)

docker exec "$WEB_CONTAINER" php -l /var/www/html/cacti/graph_image.php
docker exec "$WEB_CONTAINER" php -l /var/www/html/cacti/graph_realtime.php
docker exec "$WEB_CONTAINER" php -l /var/www/html/cacti/lib/functions.php
docker exec "$WEB_CONTAINER" php -l /var/www/html/cacti/lib/rrd.php
docker exec "$WEB_CONTAINER" php -l /var/www/html/cacti/tests/e2e/graph_subsystem_probe.php
PROBE_OUTPUT=$(docker exec "$WEB_CONTAINER" php /var/www/html/cacti/tests/e2e/graph_subsystem_probe.php)
echo "$PROBE_OUTPUT"
grep -q 'PASS graph subsystem docker probe' <<<"$PROBE_OUTPUT"
