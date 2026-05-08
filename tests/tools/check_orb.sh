#!/usr/bin/env bash
# +-------------------------------------------------------------------------+
# | Copyright (C) 2004-2026 The Cacti Group                                 |
# |                                                                         |
# | This program is free software; you can redistribute it and/or           |
# | modify it under the terms of the GNU General Public License             |
# | as published by the Free Software Foundation; either version 2          |
# | of the License, or (at your option) any later version.                  |
# |                                                                         |
# | This program is distributed in the hope that it will be useful,         |
# | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
# | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
# | GNU General Public License for more details.                            |
# +-------------------------------------------------------------------------+
# | Cacti: The Complete RRDtool-based Graphing Solution                     |
# +-------------------------------------------------------------------------+
# | This code is designed, written, and maintained by the Cacti Group. See  |
# | about.php and/or the AUTHORS file for specific developer information.   |
# +-------------------------------------------------------------------------+
# | http://www.cacti.net/                                                   |
# +-------------------------------------------------------------------------+
set -euo pipefail

# Smoke test for Docker dev environment.
# Builds, starts, validates, tears down. Exit 0 = pass, 1 = fail.

COMPOSE_PROJECT="cacti_test_$$"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
WEB_PORT=18080
PASS=0
FAIL=0

cleanup() {
    cd "$REPO_DIR"
    docker compose -p "$COMPOSE_PROJECT" down -v --remove-orphans 2>/dev/null || true
    # Only remove the .env.test file this script created. We must NEVER touch
    # $REPO_DIR/include/config.php on the host; that is the developer's real
    # Cacti configuration and is never written by this smoke test.
    rm -f "$REPO_DIR/.env.test"
}
trap cleanup EXIT

log_pass() { echo "[PASS] $1"; PASS=$((PASS + 1)); }
log_fail() { echo "[FAIL] $1"; FAIL=$((FAIL + 1)); }

check() {
    local desc="$1"; shift
    if "$@" >/dev/null 2>&1; then
        log_pass "$desc"
    else
        log_fail "$desc"
    fi
}

cd "$REPO_DIR"

# Generate test env
cat > .env.test <<EOF
WEB_PORT=$WEB_PORT
DB_ROOT_PASSWORD=testroot
DB_NAME=cacti
DB_USER=cacti
DB_PASSWORD=testpass
DB_PORT=13306
TIMEZONE=UTC
PHP_MEMORY_LIMIT=256M
DB_MAX_CONNECTIONS=50
DB_BUFFER_POOL_SIZE=256M
EOF

echo "=== Docker smoke test ==="

# 1. Build
echo "Building image..."
if docker compose -p "$COMPOSE_PROJECT" --env-file .env.test build --quiet 2>&1; then
    log_pass "Image builds"
else
    log_fail "Image builds"
    echo "Build failed, aborting."
    exit 1
fi

# 2. Start
echo "Starting containers..."
docker compose -p "$COMPOSE_PROJECT" --env-file .env.test up -d 2>&1

# 3. Wait for healthy (up to 120s)
echo "Waiting for containers..."
TRIES=0
while [ $TRIES -lt 24 ]; do
    DB_HEALTH=$(docker compose -p "$COMPOSE_PROJECT" ps --format json 2>/dev/null | grep -o '"Health":"[^"]*"' | head -1 | cut -d'"' -f4)
    WEB_HEALTH=$(docker compose -p "$COMPOSE_PROJECT" ps --format json 2>/dev/null | grep -o '"Health":"[^"]*"' | tail -1 | cut -d'"' -f4)
    if [ "$DB_HEALTH" = "healthy" ] && [ "$WEB_HEALTH" = "healthy" ]; then
        break
    fi
    sleep 5
    TRIES=$((TRIES + 1))
done

WEB_CONTAINER=$(docker compose -p "$COMPOSE_PROJECT" ps -q web 2>/dev/null)

check "DB container healthy" [ "$DB_HEALTH" = "healthy" ]
check "Web container healthy" [ "$WEB_HEALTH" = "healthy" ]

# 4. PHP extensions
REQUIRED_EXTS="gd gmp intl ldap mbstring mysqli pdo_mysql snmp pcntl posix sockets xml dom sqlite3 pdo_sqlite"
LOADED=$(docker exec "$WEB_CONTAINER" php -m 2>/dev/null)
for ext in $REQUIRED_EXTS; do
    if echo "$LOADED" | grep -qi "^${ext}$"; then
        log_pass "PHP ext: $ext"
    else
        log_fail "PHP ext: $ext"
    fi
done

# 5. config.php generated
check "config.php exists" docker exec "$WEB_CONTAINER" test -f /var/www/html/cacti/include/config.php
check "config.php has DB host" docker exec "$WEB_CONTAINER" grep -q "database_hostname" /var/www/html/cacti/include/config.php

# 6. Cron
check "Cron job installed" docker exec "$WEB_CONTAINER" test -f /etc/cron.d/cacti-poller
check "Cron daemon running" docker exec "$WEB_CONTAINER" pgrep cron

# 7. HTTP response
HTTP_CODE=$(curl -so /dev/null -w "%{http_code}" "http://localhost:${WEB_PORT}/cacti/" 2>/dev/null || echo "000")
check "HTTP 200 on /cacti/" [ "$HTTP_CODE" = "200" ]

# 8. Security headers
HEADERS=$(curl -sI "http://localhost:${WEB_PORT}/cacti/" 2>/dev/null)
check "Header: X-Content-Type-Options" grep -qi "X-Content-Type-Options" <<<"$HEADERS"
check "Header: X-Frame-Options" grep -qi "X-Frame-Options" <<<"$HEADERS"
check "Header: Referrer-Policy" grep -qi "Referrer-Policy" <<<"$HEADERS"
check "Header: Permissions-Policy" grep -qi "Permissions-Policy" <<<"$HEADERS"
if grep -qi "X-Powered-By" <<<"$HEADERS"; then
    log_fail "No X-Powered-By leak"
else
    log_pass "No X-Powered-By leak"
fi

# 9. DB accessible from web
check "DB connection from web" docker exec "$WEB_CONTAINER" php -r "new PDO('mysql:host=db;dbname=cacti', 'cacti', 'testpass');"

echo ""
echo "=== Results ==="
echo "Passed: $PASS"
echo "Failed: $FAIL"

[ "$FAIL" -eq 0 ] && exit 0 || exit 1
