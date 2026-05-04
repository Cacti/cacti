#!/usr/bin/env bash
# Asserts the master can fetch from a poller that serves HTTPS with a self-signed
# cert. Drives the same code path the UI uses (call_remote_data_collector).
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

# Wait briefly for the poller's HTTPS endpoint (the entrypoint generates the cert
# on first start, which can race with the master coming up).
for _ in $(seq 1 30); do
    if "${DC[@]}" exec -T cacti-master sh -c 'curl -fsSk https://cacti-poller/index.php >/dev/null 2>&1'; then
        break
    fi
    sleep 1
done

# Truncate the cacti log so we can grep cleanly afterwards.
"${DC[@]}" exec -T cacti-master sh -c ': > /var/www/html/log/cacti.log 2>/dev/null || true'

set +e
PROBE_OUT=$("${DC[@]}" exec -T cacti-master php /var/www/html/tests/e2e/docker/probes/probe_remote_fetch.php 2>&1)
PROBE_RC=$?
set -e

echo "$PROBE_OUT"

if [ "$PROBE_RC" -ne 0 ]; then
    echo "FAIL: probe_remote_fetch.php exit=$PROBE_RC" >&2
    "${DC[@]}" exec -T cacti-master sh -c 'tail -n 80 /var/www/html/log/cacti.log 2>/dev/null || true' >&2
    exit 1
fi

if ! echo "$PROBE_OUT" | grep -qE '^OK len=[1-9][0-9]*'; then
    echo "FAIL: probe returned suspicious payload size" >&2
    exit 1
fi

# Look for SSL/TLS verification noise in the cacti log. file_get_contents emits
# 'SSL operation failed' on cert verification errors.
if "${DC[@]}" exec -T cacti-master sh -c 'grep -iE "SSL operation failed|certificate verify|self.signed|verification failed" /var/www/html/log/cacti.log 2>/dev/null'; then
    echo "FAIL: TLS verification errors leaked into cacti.log" >&2
    exit 1
fi

echo "PASS: master fetched poller body over HTTPS with self-signed cert"
