#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
#
# Runs the installer against a real MariaDB: a failed 1.2.30 upgrade and its retry, the browser
# requests that follow a failed run, a stale wizard step and an abandoned install. PHP runs on the
# host with Xdebug; every process, including the CLI scripts the installer starts, publishes request
# coverage to CACTI_PR_COVERAGE_OUTPUT.
#
# PHP        the php command to run (default: php)
# PHP_CHILD  the php command for the processes the installer starts (default: PHP)
# DB_HOST    host the database is reached on from PHP (default: 127.0.0.1)
# DB_PORT    port the disposable MariaDB is published on (default: 13307)
set -euo pipefail
cd "$(dirname "$0")/../.."
root="$PWD"

: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage output directory is required}"
mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
CACTI_PR_COVERAGE_OUTPUT="$(cd "$CACTI_PR_COVERAGE_OUTPUT" && pwd)"

php_command="${PHP:-php}"
child_command="${PHP_CHILD:-$php_command}"
db_host="${DB_HOST:-127.0.0.1}"
db_port="${DB_PORT:-13307}"
# a throw-away password for a database that only lives for this run
db_secret="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
container="cacti_installer_coverage_$$"
work="$(mktemp -d)"
config_backup=""

cleanup() {
	result=$?
	docker rm -f "$container" > /dev/null 2>&1 || true
	if [[ -n "$config_backup" ]]; then
		mv -f "$config_backup" include/config.php
	else
		rm -f include/config.php
	fi
	rm -rf "$work"
	exit "$result"
}
trap cleanup EXIT

fail() {
	printf 'FAIL: %s\n' "$*" >&2
	exit 1
}

# Every PHP process, including the ones the installer starts, publishes request coverage.
export CACTI_PR_RAW_COVERAGE="$CACTI_PR_COVERAGE_OUTPUT"
export XDEBUG_MODE=coverage
for kind in php:"$php_command" php-child:"$child_command"; do
	cat > "$work/${kind%%:*}" << WRAPPER
#!/bin/sh
exec ${kind#*:} -d memory_limit=2G -d display_errors=stderr -d auto_prepend_file=$root/tests/Helpers/RequestCoverage.php "\$@"
WRAPPER
	chmod +x "$work/${kind%%:*}"
done
php_cacti() { "$work/php" "$@"; }

db() { docker exec -i -e MYSQL_PWD="$db_secret" "$container" mariadb --batch --skip-column-names -uroot "$@"; }
sql() { db cacti -e "$1"; }

# The version the running code installs
release="$(tr -d '[:space:]' < include/cacti_version)"

# A command line install that selects no templates and replaces the default tool locations.
install_cacti() {
	local args=() template tool
	for template in install/templates/*.xml.gz; do
		template=$(basename "$template")
		if grep -q "'$template'" install/functions.php; then
			args+=("--template=$template:0")
		fi
	done
	for tool in rrdtool snmpwalk snmpget snmpbulkwalk snmpgetnext snmptrap fping; do
		if grep -q "path_$tool" install/functions.php; then
			args+=("--path=$tool:/bin/true")
		fi
	done
	php_cacti cli/install_cacti.php --accept-eula --install --force --path=php_binary:"$work/php-child" "${args[@]}" "$@"
}

reset_database() {
	local ref="$1"
	db -e 'DROP DATABASE IF EXISTS cacti; CREATE DATABASE cacti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
	git show "$ref:cacti.sql" | db cacti
	sql "UPDATE version SET cacti = '${ref##*/}'"
}

docker run -d --name "$container" -p "127.0.0.1:$db_port:3306" -e MARIADB_ROOT_PASSWORD="$db_secret" \
	mariadb:11.8 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci > /dev/null
for _ in $(seq 1 60); do
	docker exec "$container" healthcheck.sh --connect --innodb_initialized > /dev/null 2>&1 && break
	sleep 2
done
docker exec "$container" healthcheck.sh --connect --innodb_initialized > /dev/null 2>&1 || fail 'MariaDB did not become ready'

if [[ -f include/config.php ]]; then
	config_backup="$work/config.php.orig"
	cp include/config.php "$config_backup"
fi
sed -e "s|\$database_hostname *= *'localhost'|\$database_hostname = '$db_host'|" \
	-e "s|\$database_username *= *'cactiuser'|\$database_username = 'root'|" \
	-e "s|\$database_password *= *'cactiuser'|\$database_password = '$db_secret'|" \
	-e "s|\$database_port *= *'*3306'*|\$database_port = $db_port|" \
	include/config.php.dist > include/config.php
mkdir -p log cache

printf 'Scenario: a 1.2.30 upgrade that fails the schema check\n'
reset_database release/1.2.30
sql 'DROP TABLE user_auth_row_cache'

[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php jump)" == 'step=1' ]] || fail 'a request at the first step reached the install step'
[[ "$(sql 'SELECT COUNT(*) FROM settings WHERE name = "install_started"')" == 0 ]] || fail 'a refused step started an install'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php reach | grep -vc '=ok$')" == 0 ]] || fail 'the wizard followed a step that does not come next'
# A stored location that does not exist, so the --path given to the installer has an error to resolve
sql 'REPLACE INTO settings (name, value) VALUES ("path_rrdtool", "/nonexistent/rrdtool")'

install_cacti > /dev/null && fail 'the upgrade passed with a table missing'
[[ "$(sql 'SELECT cacti FROM version')" == 1.2.31 ]] || fail 'a failed upgrade stamped the running release'
[[ "$(sql 'SELECT value FROM settings WHERE name = "install_error"')" == *user_auth_row_cache* ]] || fail 'the failure was not recorded'

printf 'Scenario: the install page and a page load after the failure\n'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php poll)" == 'step=99' ]] || fail 'the failure report was reset while polled'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php load | tr '\n' ' ')" == 'step=1 links=ok ' ]] || fail 'a fresh page load did not restart the wizard'

printf 'Scenario: retry, then repair\n'
install_cacti > /dev/null && fail 'the retry passed with a table missing'
sql "CREATE TABLE user_auth_row_cache (user_id mediumint(8) NOT NULL default '0', class varchar(20) NOT NULL default '', hash varchar(32) NOT NULL default '0', total_rows int(10) unsigned NOT NULL default '0', time timestamp NOT NULL default CURRENT_TIMESTAMP, PRIMARY KEY (user_id,class,hash)) ENGINE=InnoDB"
# Tables the schema check requires and an upgrade from 1.2.30 does not create on its own
for table in poller_output_boost_local_data_ids poller_output_boost_failures; do
	if [[ "$(sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'cacti' AND TABLE_NAME = '$table'")" == 0 ]]; then
		awk -v table="$table" '/^CREATE TABLE /{ show = (index($0, " " table " ") > 0 || index($0, "`" table "`") > 0) } show { print } /;$/{ show = 0 }' cacti.sql | db cacti
	fi
done
install_cacti > /dev/null || fail 'the retry did not complete once the tables existed'
[[ "$(sql 'SELECT cacti FROM version')" == "$release" ]] || fail 'the completed upgrade did not record the release'

printf 'Scenario: running the installer again, a browser request that names the CLI runtime, and a stale wizard step\n'
install_cacti > /dev/null || fail 'a rerun after a completed install failed'
sql 'UPDATE user_auth SET must_change_password = "" WHERE id = 1'
sql 'REPLACE INTO settings (name, value) VALUES ("install_step", "5")'
printf '%s\n' -d memory_limit=2G -d display_errors=0 -d "auto_prepend_file=$root/tests/Helpers/RequestCoverage.php" > "$work/server-flags"
[[ "$(php_cacti tests/fixtures/Installer/StepJsonProbe.php "$work/server-flags")" == 'step=98 html=yes' ]] || fail 'a browser request chose the command line runtime'
sql 'REPLACE INTO settings (name, value) VALUES ("install_step", "5")'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php stale)" == 'step=98' ]] || fail 'a stale wizard step reopened the wizard'

printf 'Scenario: a database that has no version yet\n'
sql 'DELETE FROM version'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php empty)" == 'guidance=ok' ]] || fail 'the setup guidance changed'
sql 'INSERT INTO version (cacti) VALUES ("new_install")'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php legacy)" == 'legacy=false' ]] || fail 'a new install was mistaken for a legacy database'

printf 'Scenario: a second installer while one holds the lock\n'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php lock)" == 'exit=1 refused=yes' ]] || fail 'a second installer ran while the first held the lock'

printf 'Scenario: a table selected for conversion\n'
[[ "$(php_cacti tests/fixtures/Installer/StateProbe.php convert)" == 'collation=utf8mb4_unicode_ci selected=0' ]] || fail 'a selected table was not converted, or stayed selected'

printf 'Scenario: an abandoned install\n'
sql 'UPDATE version SET cacti = "1.2.31"; DELETE FROM settings WHERE name LIKE "install%"; INSERT INTO settings (name, value) VALUES ("install_step", "97"), ("install_started", "12345"), ("install_updated", "12345")'
install_cacti > /dev/null || fail 'an abandoned install stopped the upgrade'
[[ "$(sql 'SELECT cacti FROM version')" == "$release" ]] || fail 'the upgrade after the abandoned step did not record the release'

printf 'PASS installer scenarios\n'
