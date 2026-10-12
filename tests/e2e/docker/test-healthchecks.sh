#!/usr/bin/env bash
# Exercise the image-defined checks against live services and stopped servers.
# Usage: bash test-healthchecks.sh <FPM image> <Apache image>
set -euo pipefail

fpm_image=$1
apache_image=$2
prefix="cacti-health-test-$$"
containers=()
cleanup() {
    for container in "${containers[@]}"; do
        docker rm -f "$container" >/dev/null
    done
}
trap cleanup EXIT

for role in fpm master poller; do
    container="$prefix-$role"
    containers+=("$container")
    if [ "$role" = fpm ]; then
        image=$fpm_image
        docker run -d --name "$container" --entrypoint php-fpm "$image" -F >/dev/null
    else
        image=$apache_image
        docker run -d --name "$container" -e "ROLE=$role" "$image" >/dev/null
    fi
    # Use the actual health command from the built image, not a test duplicate.
    check=$(docker image inspect --format '{{index .Config.Healthcheck.Test 1}}' "$image")
    ready=0
    for ((attempt=0; attempt<30; attempt++)); do
        if docker exec "$container" sh -c "$check" >/dev/null 2>&1; then
            ready=1
            break
        fi
        sleep 1
    done
    if [ "$ready" -ne 1 ]; then
        printf 'FAIL: %s live health check\n' "$role" >&2
        exit 1
    fi
    # With no FPM/Apache process, the same check must return failure. A fresh
    # poller image also has no generated certificate and must fail closed.
    if docker run --rm --entrypoint sh -e "ROLE=$role" "$image" -c "$check" >/dev/null 2>&1; then
        printf 'FAIL: %s accepted an absent server\n' "$role" >&2
        exit 1
    fi
    printf 'PASS: %s health check accepts live service and rejects absent service\n' "$role"
done
