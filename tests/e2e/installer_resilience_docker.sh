#!/usr/bin/env bash
# +-------------------------------------------------------------------------+
# | Copyright (C) 2004-2026 The Cacti Group                                 |
# |                                                                         |
# | This program is free software; you can redistribute it and/or           |
# | modify it under the terms of the GNU General Public License             |
# +-------------------------------------------------------------------------+
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
RUN_ID="cacti_installer_e2e_$$"
NETWORK="${RUN_ID}_net"
DB_CONTAINER="${RUN_ID}_db"
WEB_CONTAINER="${RUN_ID}_web"
IMAGE="${RUN_ID}:test"
APP_VOLUME="${RUN_ID}_app"
DB_VOLUME="${RUN_ID}_db"
CACHE_VOLUME="${RUN_ID}_cache"
RRA_VOLUME="${RUN_ID}_rra"
LOG_VOLUME="${RUN_ID}_log"
OLD_SCHEMA="$(mktemp "${TMPDIR:-/tmp}/cacti-1.2.22.XXXXXX.sql")"

cleanup() {
	docker rm -f "$WEB_CONTAINER" "$DB_CONTAINER" >/dev/null 2>&1 || true
	docker volume rm "$APP_VOLUME" "$DB_VOLUME" "$CACHE_VOLUME" "$RRA_VOLUME" "$LOG_VOLUME" >/dev/null 2>&1 || true
	docker network rm "$NETWORK" >/dev/null 2>&1 || true
	docker image rm "$IMAGE" >/dev/null 2>&1 || true
	rm -f "$OLD_SCHEMA"
}

trap cleanup EXIT

fail() {
	printf 'FAIL: %s\n' "$*" >&2
	exit 1
}

db_root() {
	docker exec "$DB_CONTAINER" mariadb --batch --skip-column-names -uroot -ptestroot "$@"
}

db_cacti() {
	db_root cacti "$@"
}

reset_database() {
	local schema="$1"

	db_root -e 'DROP DATABASE IF EXISTS cacti; CREATE DATABASE cacti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
	docker exec -i "$DB_CONTAINER" mariadb -uroot -ptestroot cacti < "$schema"
}

run_installer() {
	docker exec "$WEB_CONTAINER" php /var/www/html/cacti/cli/install_cacti.php \
		--accept-eula \
		--install \
		--force \
		--path=php_binary:/usr/local/bin/php \
		--path=rrdtool:/usr/bin/rrdtool \
		--path=snmpwalk:/usr/bin/snmpwalk \
		--path=snmpget:/usr/bin/snmpget \
		--path=snmpbulkwalk:/usr/bin/snmpbulkwalk \
		--path=snmpgetnext:/usr/bin/snmpgetnext \
		--path=fping:/usr/bin/fping \
		"$@"
}

assert_complete_install() {
	local expected_tables="$1"
	local actual_tables
	local installed_version
	local version_rows
	local host_templates

	actual_tables="$(db_cacti -e 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();')"
	installed_version="$(db_cacti -e 'SELECT cacti FROM version;')"
	version_rows="$(db_cacti -e 'SELECT COUNT(*) FROM version;')"
	host_templates="$(db_cacti -e 'SELECT COUNT(*) FROM host_template;')"

	[ "$actual_tables" -eq "$expected_tables" ] || fail "expected $expected_tables tables, found $actual_tables"
	[ "$installed_version" = "$CACTI_VERSION" ] || fail "expected version $CACTI_VERSION, found $installed_version"
	[ "$version_rows" -eq 1 ] || fail "expected one version row, found $version_rows"
	[ "$host_templates" -gt 0 ] || fail 'installer completed without host templates'
}

cd "$REPO_DIR"
git show release/1.2.22:cacti.sql > "$OLD_SCHEMA"

docker build --quiet -t "$IMAGE" -f docker/Dockerfile docker
docker network create "$NETWORK" >/dev/null
docker volume create "$APP_VOLUME" >/dev/null
docker volume create "$DB_VOLUME" >/dev/null
docker volume create "$CACHE_VOLUME" >/dev/null
docker volume create "$RRA_VOLUME" >/dev/null
docker volume create "$LOG_VOLUME" >/dev/null

# Exercise imports against a disposable application copy. Template packages
# legitimately update resource and script files, which must never dirty the
# developer's checkout or the Actions workspace.
docker run --rm \
	-v "$REPO_DIR:/source:ro" \
	-v "$APP_VOLUME:/app" \
	alpine:3.22 sh -c 'cp -a /source/. /app/'

docker run --rm \
	-v "$APP_VOLUME:/app" \
	-w /app \
	composer:2 install --ignore-platform-reqs --no-interaction --no-progress >/dev/null 2>&1

docker run -d \
	--name "$DB_CONTAINER" \
	--network "$NETWORK" \
	--network-alias db \
	-e MARIADB_ROOT_PASSWORD=testroot \
	-v "$DB_VOLUME:/var/lib/mysql" \
	mariadb:11.8 \
	--character-set-server=utf8mb4 \
	--collation-server=utf8mb4_unicode_ci >/dev/null

for _ in $(seq 1 60); do
	if docker exec "$DB_CONTAINER" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
		break
	fi

	sleep 2
done

docker exec "$DB_CONTAINER" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 || fail 'MariaDB did not become ready'

docker run -d \
	--name "$WEB_CONTAINER" \
	--network "$NETWORK" \
	-e DB_HOST=db \
	-e DB_PORT=3306 \
	-e DB_USER=root \
	-e DB_PASS=testroot \
	-e DB_NAME=cacti \
	-e TIMEZONE=UTC \
	-v "$APP_VOLUME:/var/www/html/cacti" \
	-v "$CACHE_VOLUME:/var/www/html/cacti/cache" \
	-v "$RRA_VOLUME:/var/www/html/cacti/rra" \
	-v "$LOG_VOLUME:/var/www/html/cacti/log" \
	"$IMAGE" >/dev/null

for _ in $(seq 1 30); do
	if docker exec "$WEB_CONTAINER" test -f /var/www/html/cacti/include/config.php >/dev/null 2>&1; then
		break
	fi

	sleep 1
done

docker exec "$WEB_CONTAINER" test -f /var/www/html/cacti/include/config.php || fail 'Cacti configuration was not generated'
docker exec "$WEB_CONTAINER" apt-get update -qq
docker exec "$WEB_CONTAINER" apt-get install -y -qq fping >/dev/null
docker exec "$WEB_CONTAINER" sh -c "printf '%s\n' 'display_errors=Off' 'error_reporting=24575' > /usr/local/etc/php/conf.d/zz-cacti-e2e.ini"
docker exec "$WEB_CONTAINER" mkdir -p \
	/var/www/html/cacti/cache/boost \
	/var/www/html/cacti/cache/mibcache \
	/var/www/html/cacti/cache/realtime \
	/var/www/html/cacti/cache/spikekill
docker exec "$WEB_CONTAINER" chown -R www-data:www-data /var/www/html/cacti/cache

CACTI_VERSION="$(docker exec "$WEB_CONTAINER" awk -F. '{print $1 "." $2 "." $3}' /var/www/html/cacti/include/cacti_version)"
EXPECTED_TABLES="$(grep -c '^CREATE TABLE' cacti.sql)"

printf 'Scenario: fresh CLI installation\n'
reset_database "$REPO_DIR/cacti.sql"
run_installer
assert_complete_install "$EXPECTED_TABLES"

printf 'Scenario: 1.2.22 to %s CLI upgrade\n' "$CACTI_VERSION"
reset_database "$OLD_SCHEMA"
db_cacti -e "UPDATE version SET cacti = '1.2.22';"
run_installer
assert_complete_install "$EXPECTED_TABLES"

printf 'Scenario: table conversion handoff reaches its postcondition\n'
reset_database "$REPO_DIR/cacti.sql"
db_cacti -e 'ALTER TABLE host ROW_FORMAT=Compact, CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci;'
run_installer --table=host:1
[ "$(db_cacti -e "SELECT ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'host';")" = 'Dynamic' ] || fail 'installer did not convert the host table row format'
case "$(db_cacti -e "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'host';")" in
	utf8mb4_*) ;;
	*) fail 'installer did not convert the host table character set' ;;
esac

printf 'Scenario: version persistence failure rolls back and fails closed\n'
reset_database "$REPO_DIR/cacti.sql"
db_cacti -e "CREATE TRIGGER reject_version BEFORE INSERT ON version FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'version write rejected';"
set +e
VERSION_OUTPUT="$(run_installer 2>&1)"
VERSION_STATUS=$?
set -e
printf '%s\n' "$VERSION_OUTPUT"
[ "$VERSION_STATUS" -ne 0 ] || fail 'installer ignored a rejected version write'
[ "$(db_cacti -e 'SELECT cacti FROM version;')" = 'new_install' ] || fail 'failed version transaction did not roll back'

printf 'Scenario: incomplete schema fails closed in CLI mode\n'
reset_database "$REPO_DIR/cacti.sql"
db_cacti -e 'DROP TABLE user_auth_row_cache;'
set +e
CLI_OUTPUT="$(run_installer 2>&1)"
CLI_STATUS=$?
set -e
printf '%s\n' "$CLI_OUTPUT"
[ "$CLI_STATUS" -ne 0 ] || fail 'CLI installer accepted an incomplete schema'
grep -q 'user_auth_row_cache' <<< "$CLI_OUTPUT" || fail 'CLI failure did not identify the missing table'
[ "$(db_cacti -e 'SELECT cacti FROM version;')" = 'new_install' ] || fail 'failed install advanced the version row'

printf 'Scenario: incomplete schema fails closed in background mode\n'
db_cacti -e "REPLACE INTO settings (name, value) VALUES
	('install_started', '12345'),
	('install_updated', '12345'),
	('install_step', '97'),
	('install_eula', '1'),
	('install_version', '$CACTI_VERSION');"
set +e
BACKGROUND_OUTPUT="$(docker exec "$WEB_CONTAINER" php /var/www/html/cacti/install/background.php 12345 2>&1)"
BACKGROUND_STATUS=$?
set -e
printf '%s\n' "$BACKGROUND_OUTPUT"
[ "$BACKGROUND_STATUS" -ne 0 ] || fail 'background installer accepted an incomplete schema'
[ "$(db_cacti -e "SELECT value FROM settings WHERE name = 'install_step';")" = '99' ] || fail 'background failure did not preserve STEP_ERROR'

printf 'PASS installer unit/integration Docker matrix\n'
