#!/usr/bin/env bash
# Prove both Apache roles start and serve requests without a root process.
set -euo pipefail
image=$1
prefix="cacti-apache-user-test-$$"
containers=()
cleanup() {
    for container in "${containers[@]}"; do
        docker rm -f "$container" >/dev/null
    done
}
trap cleanup EXIT

for role in master poller; do
    container="$prefix-$role"
    containers+=("$container")
    docker run -d --name "$container" -e "ROLE=$role" "$image" >/dev/null
    test "$(docker inspect --format '{{.Config.User}}' "$container")" = www-data
    ready=0
    for ((attempt=0; attempt<30; attempt++)); do
        if [ "$role" = poller ]; then
            if docker exec "$container" curl --fail --silent --show-error --max-time 4 \
                --cacert /var/cacti-state/tls/poller.crt https://127.0.0.1/index.php -o /dev/null 2>/dev/null; then ready=1; break; fi
        elif docker exec "$container" curl --fail --silent --show-error --max-time 4 \
            http://127.0.0.1/index.php -o /dev/null 2>/dev/null; then ready=1; break;
        fi
        sleep 1
    done
    test "$ready" = 1
    test "$(docker exec "$container" id -u)" = 33
    # Check actual server process credentials, including its parent process.
    # shellcheck disable=SC2016
    docker exec "$container" php -r '
        $found = 0;
        foreach (glob("/proc/[0-9]*/status") as $file) {
            $status = @file_get_contents($file);
            if ($status !== false && preg_match("/^Name:\\s+apache2$/m", $status)) {
                if (!preg_match("/^Uid:\\s+33\\s+33\\s+33\\s+33$/m", $status)) { exit(1); }
                $found++;
            }
        }
        exit($found > 0 ? 0 : 1);
    '
    if [ "$role" = poller ]; then
        # shellcheck disable=SC2016
        docker exec "$container" php -r '
            $key = "/var/cacti-state/tls/poller.key";
            exit(fileowner($key) === 33 && (fileperms($key) & 0777) === 0600 ? 0 : 1);
        '
    fi
    printf 'PASS: %s serves requests with all Apache processes as www-data\n' "$role"
done
