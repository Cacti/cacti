#!/usr/bin/env bash
# Run the production logger against the installed fixture's real database/log.
set -euo pipefail
cd "$(dirname "$0")"

runner=(docker compose exec -T)
if [ "$(docker compose exec -T php id -u)" = 0 ]; then
    runner+=(--user www-data)
fi

"${runner[@]}" php php -d disable_functions=posix_getpwuid,posix_geteuid \
    tests/fixtures/Core/UserActionLogProbe.php

# The router accepts only cli-server with an explicit test flag and loopback
# peer, so it cannot become an application endpoint through FPM or Apache.
# shellcheck disable=SC2016
"${runner[@]}" -e CACTI_USER_AUDIT_PROBE=1 php sh -c '
    server_log=$(mktemp /tmp/cacti-user-audit.XXXXXX)
    php -S 127.0.0.1:18705 tests/fixtures/Core/UserActionLogProbe.php >"$server_log" 2>&1 &
    server_pid=$!
    cleanup() {
        kill "$server_pid" 2>/dev/null || true
        rm -f "$server_log"
    }
    trap cleanup EXIT
    ready=0
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        if php -r '\''$body = @file_get_contents("http://127.0.0.1:18705/"); exit($body === "PASS: anonymous web audit actor\n" ? 0 : 1);'\''; then
            ready=1
            break
        fi
        sleep 1
    done
    test "$ready" = 1
    printf "%s\n" "PASS: native web audit actor"
'
