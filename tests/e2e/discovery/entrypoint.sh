#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
[[ "${CACTI_DISCOVERY_FIXTURE:-}" == 1 && "${CACTI_DB_HOST:-}" == mariadb ]] || exit 2
cd /var/www/html
# This PHP program owns its $variables; do not expand them in the shell.
# shellcheck disable=SC2016
php -r '
$config = "<?php\n";
foreach (["database_type"=>"mysql", "database_hostname"=>"mariadb", "database_default"=>"cacti", "database_username"=>getenv("CACTI_DB_USER"), "database_password"=>getenv("CACTI_DB_PASS"), "database_port"=>"3306", "poller_id"=>1, "url_path"=>"/"] as $name=>$value) {
 $config .= "$".$name." = ".var_export($value,true).";\n";
}
if (file_put_contents("include/config.php",$config) === false) exit(1);
'
if ! mysql --skip-ssl -hmariadb -u"$CACTI_DB_USER" -p"$CACTI_DB_PASS" cacti -e 'SELECT 1 FROM settings LIMIT 1' >/dev/null 2>&1; then
  mysql --skip-ssl -hmariadb -u"$CACTI_DB_USER" -p"$CACTI_DB_PASS" cacti < cacti.sql
  version="$(cat include/cacti_version)"
  [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 2
  mysql --skip-ssl -hmariadb -u"$CACTI_DB_USER" -p"$CACTI_DB_PASS" cacti -e "UPDATE version SET cacti='$version'; REPLACE INTO settings(name,value) VALUES ('install_complete','1')"
fi
mkdir -p log rra cache
chown -R www-data:www-data log rra cache
chown root:www-data include/config.php
chmod 640 include/config.php
exec "$@"
