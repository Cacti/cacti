#!/usr/bin/env bash
# Verify the actual FPM master and workers use the configured checkout UID.
set -euo pipefail
image=$1
expected_uid=$2
container="cacti-fpm-user-test-$$"
cleanup() { docker rm -f "$container" >/dev/null; }
trap cleanup EXIT

test "$expected_uid" -gt 0
test "$(docker image inspect --format '{{.Config.User}}' "$image")" = cacti
docker run -d --name "$container" --entrypoint php-fpm "$image" -F >/dev/null
test "$(docker exec "$container" id -u)" = "$expected_uid"
docker exec "$container" php-fpm --test
# shellcheck disable=SC2016
docker exec "$container" php -r '
    $expected = (int) $argv[1];
    $found = 0;
    foreach (glob("/proc/[0-9]*/status") as $file) {
        $status = @file_get_contents($file);
        if ($status !== false && preg_match("/^Name:\\s+php-fpm$/m", $status)) {
            if (!preg_match("/^Uid:\\s+(\\d+)\\s+(\\d+)\\s+(\\d+)\\s+(\\d+)$/m", $status, $uids)) { exit(1); }
            foreach (array_slice($uids, 1) as $uid) { if ((int) $uid !== $expected) { exit(1); } }
            $found++;
        }
    }
    exit($found >= 2 ? 0 : 1);
' "$expected_uid"
printf 'PASS: FPM master and workers run as checkout UID %s\n' "$expected_uid"
