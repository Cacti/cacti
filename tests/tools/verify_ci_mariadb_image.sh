#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail
image=${1:?Pass the verified local image ID}
[[ "$image" =~ ^sha256:[0-9a-f]{64}$ ]]
container=''
# Invoked by the EXIT trap, including failed startup/query paths.
# shellcheck disable=SC2329
cleanup() {
    if [[ -n "$container" ]]; then
        docker rm --force --volumes "$container" >/dev/null
    fi
}
trap cleanup EXIT
container=$(docker run --detach --pull=never \
    --env MYSQL_DATABASE=cacti --env MYSQL_USER=cactiuser \
    --env MYSQL_PASSWORD=cactiuser --env MYSQL_ROOT_PASSWORD=cactiroot "$image")
for _ in $(seq 1 60); do
    if result=$(docker exec "$container" mariadb --protocol=TCP --host=127.0.0.1 -ucactiuser -pcactiuser cacti \
        --batch --skip-column-names -e 'SELECT VERSION(); SELECT 1;' 2>/dev/null); then
        [[ "$result" == 10.6.*$'\n1' ]]
        printf 'PASS: pinned MariaDB accepts the configured database credentials\n'
        exit 0
    fi
    sleep 2
done
docker logs "$container"
exit 1
