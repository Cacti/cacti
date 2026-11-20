#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail

root=$(git rev-parse --show-toplevel)
app=$(mktemp -d)
schema="cacti_rrd_test_$(php -r 'echo bin2hex(random_bytes(6));')"
export CACTI_RRD_FIXTURE_DB="$schema" CACTI_RRD_FIXTURE_PORT="${CACTI_RRD_FIXTURE_PORT:-3306}"
client=$(command -v mariadb || command -v mysql)
sql() {
	"$client" --protocol=tcp --host=127.0.0.1 --port="$CACTI_RRD_FIXTURE_PORT" --user=root --batch --skip-column-names "$@"
}

# All CLI writes are confined to this checkout and newly created database.
trap 'rm -rf "$app"' EXIT
sql -e "CREATE DATABASE $schema"
trap 'sql -e "DROP DATABASE $schema"; rm -rf "$app"' EXIT
git -C "$root" ls-files -z | rsync -a --from0 --files-from=- "$root/" "$app/"
cd "$app"
sql "$schema" < cacti.sql
version=$(cat include/cacti_version)
[[ $version =~ ^[0-9a-zA-Z._-]+$ ]]
sql "$schema" -e "UPDATE version SET cacti = '$version' WHERE cacti = 'new_install'"
cp include/config.php.dist include/config.php
cat >> include/config.php <<'PHP'

$database_default = getenv('CACTI_RRD_FIXTURE_DB');
$database_hostname = '127.0.0.1';
$database_port = getenv('CACTI_RRD_FIXTURE_PORT');
$database_username = 'root';
$database_password = getenv('MYSQL_PWD');
PHP

for view in aggregate graph; do
	php tests/fixtures/Core/RrdDisplayContracts.php "$view" | grep -Fx 'RRD display passed'
done
php tests/fixtures/Core/RrdDisplayContracts.php properties > "$app/properties.html"
grep -Fq 'RRDtool Command:' "$app/properties.html"
grep -Fq 'RRDtool Says:' "$app/properties.html"

sql "$schema" <<'SQL'
INSERT INTO settings (name, value) VALUES ('boost_rrd_update_enable', 'on'), ('extended_paths_type', 'device')
	ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO host (id, hostname, description) VALUES (17, 'test.invalid', 'Router');
INSERT INTO data_local (id, host_id) VALUES (31, 17), (32, 17), (33, 17);
INSERT INTO data_template_data (local_data_id, data_source_path) VALUES
	(31, '<path_rra>/notes.txt'), (32, '<path_rra>/missing.rrd'), (33, '<path_rra>/old.rrd');
SQL
printf 'operator notes\n' > rra/notes.txt
printf 'sample data\n' > rra/router_32.rrd
php -d auto_prepend_file="$app/tests/Helpers/RequestCoverage.php" cli/structure_rra_paths.php --host-id=17 --proceed > "$app/migration.log"
grep -F 'notes.txt' "$app/migration.log"
test "$(cat rra/notes.txt)" = 'operator notes'
test "$(cat rra/17/32.rrd)" = 'sample data'
test ! -e rra/17/31.rrd
paths=$(sql "$schema" -e 'SELECT data_source_path FROM data_template_data WHERE local_data_id IN (31,32,33) ORDER BY local_data_id')
test "$paths" = "$(printf '%s\n' '<path_rra>/notes.txt' '<path_rra>/17/32.rrd' '<path_rra>/17/33.rrd')"
