#!/usr/bin/env bash
# Run the production logger against the installed fixture's real database/log.
set -euo pipefail
cd "$(dirname "$0")"

docker compose exec -T php php -d disable_functions=posix_getpwuid,posix_geteuid \
    tests/fixtures/Core/UserActionLogProbe.php

# The router accepts only cli-server with an explicit test flag and loopback
# peer, so it cannot become an application endpoint through FPM or Apache.
# shellcheck disable=SC2016
docker compose exec -T -e CACTI_USER_AUDIT_PROBE=1 php sh -c '
    php -S 127.0.0.1:18705 tests/fixtures/Core/UserActionLogProbe.php >/tmp/cacti-user-audit-server.log 2>&1 &
    server_pid=$!
    trap "kill $server_pid" EXIT
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
