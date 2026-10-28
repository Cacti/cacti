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
LDAP_CONTAINER="${RUN_ID}_ldap"
IMAGE="${RUN_ID}:test"
APP_VOLUME="${RUN_ID}_app"
DB_VOLUME="${RUN_ID}_db"
CACHE_VOLUME="${RUN_ID}_cache"
RRA_VOLUME="${RUN_ID}_rra"
LOG_VOLUME="${RUN_ID}_log"
SOURCE_VERSION="${1:-${INSTALLER_SOURCE_VERSION:-1.2.22}}"
DB_IMAGE="${2:-${INSTALLER_DB_IMAGE:-mariadb:11.8}}"
OLD_SCHEMA="$(mktemp "${TMPDIR:-/tmp}/cacti-upgrade.XXXXXX")"

cleanup() {
	local status=$?
	if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 && -n "${CACTI_PR_COVERAGE_OUTPUT:-}" ]]; then
		mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
		docker cp "$WEB_CONTAINER:/tmp/cacti-pr-coverage/." "$CACTI_PR_COVERAGE_OUTPUT/" || status=1
	fi
	if [ "$status" -ne 0 ] && [ "${INSTALLER_KEEP_FAILURE:-0}" = 1 ]; then
		printf 'Retained disposable failed fixture: %s (%s)\n' "$RUN_ID" "$IMAGE" >&2
		return
	fi
	docker rm -f "$WEB_CONTAINER" "$DB_CONTAINER" "$LDAP_CONTAINER" >/dev/null 2>&1 || true
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
git show "release/$SOURCE_VERSION:cacti.sql" > "$OLD_SCHEMA"
printf 'Upgrade fixture source: release/%s (%s)\n' "$SOURCE_VERSION" "$(git rev-parse "release/$SOURCE_VERSION")"

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
	composer:2 install '--ignore-platform-req=ext-*' --no-interaction --no-progress >/dev/null 2>&1

docker run -d \
	--name "$DB_CONTAINER" \
	--network "$NETWORK" \
	--network-alias db \
	-e MARIADB_ROOT_PASSWORD=testroot \
	-v "$DB_VOLUME:/var/lib/mysql" \
	"$DB_IMAGE" \
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
	--publish 127.0.0.1::80 \
	--name "$WEB_CONTAINER" \
	--network "$NETWORK" \
	-e DB_HOST=db \
	-e DB_PORT=3306 \
	-e DB_USER=root \
	-e DB_PASS=testroot \
	-e DB_NAME=cacti \
	-e TIMEZONE=UTC \
	-e CACTI_PR_RAW_COVERAGE="${CACTI_PR_RAW_COVERAGE:-}" \
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
docker exec "$WEB_CONTAINER" service cron stop >/dev/null
docker exec "$WEB_CONTAINER" apt-get update -qq
docker exec "$WEB_CONTAINER" apt-get install -y -qq fping >/dev/null
docker exec "$WEB_CONTAINER" sh -c "printf '%s\n' 'display_errors=Off' 'error_reporting=24575' > /usr/local/etc/php/conf.d/zz-cacti-e2e.ini"
if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then
	docker exec "$WEB_CONTAINER" pecl install xdebug-3.5.3 >/dev/null
	docker exec "$WEB_CONTAINER" docker-php-ext-enable xdebug
	docker exec "$WEB_CONTAINER" mkdir -p /tmp/cacti-pr-coverage
	docker exec "$WEB_CONTAINER" chmod 0777 /tmp/cacti-pr-coverage
	docker exec "$WEB_CONTAINER" sh -c "printf '%s\n' 'xdebug.mode=coverage' 'auto_prepend_file=/var/www/html/cacti/tests/Helpers/RequestCoverage.php' > /usr/local/etc/php/conf.d/zz-request-coverage.ini"
	docker exec "$WEB_CONTAINER" sh -c "printf '%s\n' '[www]' 'env[CACTI_PR_RAW_COVERAGE] = /tmp/cacti-pr-coverage' >> /usr/local/etc/php-fpm.d/zz-docker.conf"
	docker exec "$WEB_CONTAINER" pkill -USR2 php-fpm
fi
docker exec "$WEB_CONTAINER" mkdir -p \
	/var/www/html/cacti/cache/boost \
	/var/www/html/cacti/cache/mibcache \
	/var/www/html/cacti/cache/realtime \
	/var/www/html/cacti/cache/spikekill
docker exec "$WEB_CONTAINER" touch /var/www/html/cacti/log/cacti.log
docker exec "$WEB_CONTAINER" chown -R www-data:www-data /var/www/html/cacti/cache /var/www/html/cacti/log

docker exec "$WEB_CONTAINER" php -r 'echo "Installer test PHP ", PHP_VERSION, PHP_EOL;'
db_root -e 'SELECT VERSION();'

CACTI_VERSION="$(docker exec "$WEB_CONTAINER" awk -F. '{print $1 "." $2 "." $3}' /var/www/html/cacti/include/cacti_version)"
EXPECTED_TABLES="$(grep -c '^CREATE TABLE' cacti.sql)"

printf 'Scenario: fresh CLI installation\n'
reset_database "$REPO_DIR/cacti.sql"
run_installer --debug=json:3
assert_complete_install "$EXPECTED_TABLES"
[ "$(db_cacti -e "SELECT value FROM settings WHERE name = 'log_install_json';")" = '3' ] || fail 'installer reset the requested debug level'

printf 'Scenario: table conversion rejects missing conversion options\n'
if docker exec "$WEB_CONTAINER" php /var/www/html/cacti/cli/convert_tables.php; then
	fail 'table converter returned success without a conversion option'
fi

printf 'Scenario: table conversion rejects disabled file-per-table\n'
db_root -e 'SET GLOBAL innodb_file_per_table = OFF;'
if docker exec "$WEB_CONTAINER" php /var/www/html/cacti/cli/convert_tables.php --innodb --table=host; then
	fail 'table converter returned success with file-per-table disabled'
fi
db_root -e 'SET GLOBAL innodb_file_per_table = ON;'

printf 'Scenario: installer logging mode loads the conversion helpers\n'
docker exec "$WEB_CONTAINER" php /var/www/html/cacti/cli/convert_tables.php --installer --utf8 --table=host

printf 'Scenario: row-format-only conversion does not require a charset change\n'
db_cacti -e 'CREATE TABLE installer_conversion_fixture (id int unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB ROW_FORMAT=Compact DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
docker exec "$WEB_CONTAINER" php /var/www/html/cacti/cli/convert_tables.php --innodb --dynamic --table=installer_conversion_fixture
[ "$(db_cacti -e "SELECT ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installer_conversion_fixture';")" = 'Dynamic' ] || fail 'row-format-only conversion did not convert the fixture table'

printf 'Scenario: Aria conversion reaches the requested InnoDB engine\n'
db_cacti -e 'ALTER TABLE installer_conversion_fixture ENGINE=Aria;'
docker exec "$WEB_CONTAINER" php /var/www/html/cacti/cli/convert_tables.php --innodb --utf8 --table=installer_conversion_fixture
[ "$(db_cacti -e "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'installer_conversion_fixture';")" = 'InnoDB' ] || fail 'table converter left the fixture table using Aria'

printf 'Scenario: %s to %s CLI upgrade\n' "$SOURCE_VERSION" "$CACTI_VERSION"
reset_database "$OLD_SCHEMA"
db_cacti -e "UPDATE version SET cacti = '$SOURCE_VERSION';"
run_installer
assert_complete_install "$EXPECTED_TABLES"
printf 'Scenario: database-only CLI verifies target schema and authentication\n'
reset_database "$OLD_SCHEMA"
db_cacti -e "UPDATE version SET cacti = '$SOURCE_VERSION';"
docker exec "$WEB_CONTAINER" php /var/www/html/cacti/cli/upgrade_database.php --forcever="$SOURCE_VERSION"
[ "$(db_cacti -e "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_template' AND COLUMN_NAME = 'templates';")" = 'templates' ] || fail 'database-only CLI did not install the template column'
[ "$(db_cacti -e "SELECT JSON_UNQUOTE(JSON_EXTRACT(value, '$.state')) FROM settings WHERE name = 'maintenance_lastrun_installer_report';")" = 'succeeded' ] || fail 'database-only CLI did not preserve successful diagnostics'

printf 'Scenario: database-only CLI rejects incomplete target schema\n'
reset_database "$OLD_SCHEMA"
db_cacti -e "UPDATE version SET cacti = '$SOURCE_VERSION';"
docker exec -e CACTI_INSTALLER_FAILURE_FIXTURE=1 "$WEB_CONTAINER" php /var/www/html/cacti/tests/e2e/installer_failure_paths.php schema-postcondition

printf 'Scenario: populated %s installation preserves authentication, templates, plugins and collector state\n' "$SOURCE_VERSION"
reset_database "$OLD_SCHEMA"
for collector_db in collector_two collector_three; do
	db_root -e "DROP DATABASE IF EXISTS $collector_db; CREATE DATABASE $collector_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
	docker exec -i "$DB_CONTAINER" mariadb -uroot -ptestroot "$collector_db" < "$REPO_DIR/cacti.sql"
done

docker run -d --name "$LDAP_CONTAINER" --network "$NETWORK" --network-alias ldap \
	-e LDAP_ORGANISATION='Cacti installer fixture' -e LDAP_DOMAIN=example.org \
	-e LDAP_ADMIN_PASSWORD=adminpassword -e LDAP_TLS=false \
	-v "$REPO_DIR/tests/e2e/ldap/50-e2e.ldif:/container/service/slapd/assets/config/bootstrap/ldif/custom/50-e2e.ldif:ro" \
	osixia/openldap:1.5.0 --copy-service >/dev/null
for _ in $(seq 1 30); do
	if docker exec "$LDAP_CONTAINER" ldapsearch -x -H ldap://127.0.0.1:389 -b dc=example,dc=org -D cn=admin,dc=example,dc=org -w adminpassword -LLL cn=ldapuser >/dev/null 2>&1; then
		break
	fi
	sleep 2
done

docker exec -i "$DB_CONTAINER" mariadb -uroot -ptestroot cacti < "$REPO_DIR/tests/e2e/installer_populated_fixture.sql"
docker exec "$WEB_CONTAINER" mkdir -p /var/www/html/cacti/plugins/installer_fixture
docker cp "$REPO_DIR/tests/fixtures/InstallerPlugin/." "$WEB_CONTAINER:/var/www/html/cacti/plugins/installer_fixture/"
docker exec "$WEB_CONTAINER" rrdtool create /var/www/html/cacti/rra/installer_fixture.rrd --step 300 DS:fixture:GAUGE:600:0:U RRA:AVERAGE:0.5:1:48
RRD_BEFORE="$(docker exec "$WEB_CONTAINER" sha256sum /var/www/html/cacti/rra/installer_fixture.rrd)"
ADMIN_HASH="$(docker exec "$WEB_CONTAINER" php -r 'echo password_hash("installer-e2e-pass", PASSWORD_DEFAULT);')"
db_cacti -e "UPDATE version SET cacti = '$SOURCE_VERSION'; UPDATE settings SET value = '3600' WHERE name = 'poller_interval'; UPDATE user_auth SET password = '$ADMIN_HASH', must_change_password = '', lastchange = UNIX_TIMESTAMP() WHERE id = 1;"
run_installer
assert_complete_install "$((EXPECTED_TABLES))"
[ "$(db_cacti -e "SELECT name FROM host_template WHERE id = 700;")" = 'Installer custom template' ] || fail 'custom template changed during upgrade'
[ "$(db_cacti -e "SELECT value FROM settings WHERE name = 'installer_fixture_custom_setting';")" = 'preserve-me' ] || fail 'custom setting changed during upgrade'
[ "$(db_cacti -e "SELECT COUNT(*) FROM poller_output_boost WHERE local_data_id = 700;")" -eq 100000 ] || fail 'populated large-table data changed during upgrade'
[ "$(db_cacti -e "SELECT realm FROM user_auth WHERE id = 700;")" -eq 1007 ] || fail 'LDAP user lost its provider realm'
[ "$(db_cacti -e "SELECT value FROM settings WHERE name = 'auth_method';")" -eq 4 ] || fail 'legacy domain authentication was not preserved as provider authentication'
[ "$(db_cacti -e "SELECT JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.server')) FROM login_providers WHERE id = 7;")" = 'ldap' ] || fail 'LDAP server configuration was not preserved'
[ "$(db_cacti -e "SELECT JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.group_dn')) FROM login_providers WHERE id = 7;")" = '' ] || fail 'disabled LDAP group enforcement became enabled'
[ "$(db_cacti -e "SELECT status FROM plugin_config WHERE directory = 'installer_missing';")" -ne 1 ] || fail 'missing plugin stayed enabled'
[ "$(db_cacti -e "SELECT status FROM plugin_config WHERE directory = 'installer_fixture';")" -eq 1 ] || fail 'compatible plugin was disabled'
[ "$(db_cacti -e "SELECT data_source_name FROM data_template_rrd WHERE id = 700;")" = 'fixture' ] || fail 'custom data source changed'
[ "$(docker exec "$WEB_CONTAINER" sha256sum /var/www/html/cacti/rra/installer_fixture.rrd)" = "$RRD_BEFORE" ] || fail 'existing RRD file changed during upgrade'
[ "$(db_cacti -e "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_local' AND INDEX_NAME = 'host_id_snmp_query_id';")" = 'host_id,snmp_query_id' ] || fail 'required data-local index changed'
[ "$(db_cacti -e "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_auth' AND INDEX_NAME = 'username';")" = 'username' ] || fail 'required authentication index changed'
[ "$(db_cacti -e "SELECT COUNT(*) FROM poller WHERE id IN (2, 3) AND last_sync > '2000-01-01';")" -eq 2 ] || fail 'not all remote collectors were synchronized'
for collector_db in collector_two collector_three; do
	[ "$(db_root "$collector_db" -e "SELECT COUNT(*) FROM user_auth WHERE id = 700;")" -eq 1 ] || fail 'remote collector did not receive upgraded users'
done
docker exec "$WEB_CONTAINER" bash /var/www/html/cacti/tests/e2e/installer_application_check.sh
[ "$(db_cacti -e 'SELECT description FROM host WHERE id = 700;')" = 'Installer custom device' ] || fail 'invalid CSRF request changed the existing device'
BROWSER_PORT="$(docker port "$WEB_CONTAINER" 80/tcp)"
CACTI_BASE_URL="http://$BROWSER_PORT/cacti/" CACTI_INSTALLER_UPGRADE_TEST=1 \
	npm --prefix tests/e2e test -- tests/installer-upgrade.spec.ts --project=chromium --workers=1


printf 'Scenario: repeated completed installation preserves custom objects\n'
run_installer
[ "$(db_cacti -e "SELECT COUNT(*) FROM poller_output_boost WHERE local_data_id = 700;")" -eq 100000 ] || fail 'repeated installation changed buffered data'
[ "$(db_cacti -e "SELECT name FROM host_template WHERE id = 700;")" = 'Installer custom template' ] || fail 'repeated installation changed the custom template'
[ "$(docker exec "$WEB_CONTAINER" sha256sum /var/www/html/cacti/rra/installer_fixture.rrd)" = "$RRD_BEFORE" ] || fail 'repeated installation changed the RRD file'

printf 'Scenario: forced replay on the populated, already upgraded schema\n'
docker exec -e CACTI_INSTALLER_FAILURE_FIXTURE=1 "$WEB_CONTAINER" \
	php /var/www/html/cacti/tests/e2e/installer_failure_paths.php forced-upgrade-replay
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
[ "$(db_cacti -e "SELECT COUNT(*) FROM processes WHERE tasktype = 'install' AND taskname = 'master';")" -eq 0 ] || fail 'failed installer retained its process registration'
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
[ "$(db_cacti -e "SELECT COUNT(*) FROM processes WHERE tasktype = 'install' AND taskname = 'master';")" -eq 0 ] || fail 'schema validation failure retained its process registration'
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

for scenario in domain-read ldap-read count-read upgrade-warning upgrade-report cli-failure forced-cli-failure dependency-preflight collector-exception installer-lock; do
	printf 'Scenario: %s\n' "$scenario"
	reset_database "$REPO_DIR/cacti.sql"
	docker exec -e CACTI_INSTALLER_FAILURE_FIXTURE=1 "$WEB_CONTAINER" \
		php /var/www/html/cacti/tests/e2e/installer_failure_paths.php "$scenario"
done

printf 'PASS installer unit/integration Docker matrix\n'
