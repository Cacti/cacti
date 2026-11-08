#!/usr/bin/env bash

# The canonical Cacti schema contains directives for Cacti's installer SQL
# parser. Convert those two directives before feeding it to MySQL's client.
case "${MYSQL_DATABASE:-}" in
	''|*[!a-zA-Z0-9_]*)
		echo 'MYSQL_DATABASE must contain only letters, numbers, and underscores.' >&2
		return 1
		;;
esac

sed \
	-e '/^DELIMITER \/\/$/d' \
	-e "s/^ALTER DATABASE default /ALTER DATABASE \`${MYSQL_DATABASE}\` /" \
	/docker-entrypoint-initdb.d/cacti.sql.source |
	docker_process_sql --database="${MYSQL_DATABASE}"
