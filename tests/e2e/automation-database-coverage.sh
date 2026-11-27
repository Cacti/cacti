#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
#
# Run the database-backed automation and template propagation contracts against a
# disposable MariaDB created from cacti.sql, and keep the request coverage that
# every isolated probe process records for the changed-line gate.
set -euo pipefail
cd "$(dirname "$0")/../.."
: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage output directory is required}"
mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
name="cacti-automation-coverage-$$"
port="${HOST_PORT:-18278}"
cleanup() {
    docker rm -f "$name" > /dev/null 2>&1 || true
}
trap cleanup EXIT
docker run -d --name "$name" -p "127.0.0.1:$port:3306" \
    -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 -e MARIADB_DATABASE=cacti_automation_test \
    mariadb:10.11 --max-allowed-packet=500M > /dev/null
ready=0
for _ in $(seq 1 60); do
    if docker exec "$name" mariadb-admin ping -h127.0.0.1 --silent > /dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 2
done
if [[ "$ready" != 1 ]]; then
    docker logs --tail=50 "$name" >&2 || true
    echo "The disposable database did not become ready." >&2
    exit 1
fi
docker exec -i "$name" mariadb -uroot cacti_automation_test < cacti.sql
php_args=(-d memory_limit=2G)
if [[ -n "${CACTI_PR_XDEBUG_EXTENSION:-}" ]]; then
    php_args+=(-d "zend_extension=$CACTI_PR_XDEBUG_EXTENSION")
fi
CACTI_PR_RAW_COVERAGE="$CACTI_PR_COVERAGE_OUTPUT" XDEBUG_MODE=coverage \
    CACTI_AUTOMATION_TEST_DSN="mysql:host=127.0.0.1;port=$port;dbname=cacti_automation_test" \
    php "${php_args[@]}" include/vendor/bin/pest --configuration=tests/phpunit.xml \
    tests/integration/AutomationCreationIntegrationTest.php \
    tests/integration/TemplatePropagationIntegrationTest.php
