#!/usr/bin/env bash
# Verify the installer-to-database-to-browser CSRF secret handoff.
set -euo pipefail
IFS=$'\n\t'

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

SECRET=$("${DC[@]}" exec -T cacti-db mariadb -N -B \
	-ucactiuser -pcactiuser cacti \
	-e "SELECT value FROM settings WHERE name='csrf_secret' LIMIT 1")

if ! [[ "$SECRET" =~ ^[a-f0-9]{64}$ ]]; then
	echo "FAIL: installer did not store a 32-byte hexadecimal CSRF secret" >&2
	exit 1
fi

if "${DC[@]}" exec -T cacti-master test -e /var/www/html/include/vendor/csrf/csrf-secret.php; then
	echo "FAIL: installer created the legacy CSRF secret below the web root" >&2
	exit 1
fi

"${DC[@]}" exec -T cacti-master php /var/www/html/cli/refresh_csrf.php >/dev/null
ROTATED_SECRET=$("${DC[@]}" exec -T cacti-db mariadb -N -B \
	-ucactiuser -pcactiuser cacti \
	-e "SELECT value FROM settings WHERE name='csrf_secret' LIMIT 1")
if ! [[ "$ROTATED_SECRET" =~ ^[a-f0-9]{64}$ ]] || [ "$ROTATED_SECRET" = "$SECRET" ]; then
	echo "FAIL: refresh_csrf.php did not persist a new 32-byte secret" >&2
	exit 1
fi

"${DC[@]}" exec -T cacti-master sh -c \
	'curl -fsS -c /tmp/c06.jar http://127.0.0.1/index.php > /tmp/c06_form'
TOKEN=$("${DC[@]}" exec -T cacti-master php \
	/var/www/html/tests/e2e/docker/probes/extract_csrf.php /tmp/c06_form)

if ! [[ "$TOKEN" =~ ^(sid|cookie|key|user|ip):[a-f0-9]+,[0-9]+ ]]; then
	echo "FAIL: browser form did not receive a valid token shape" >&2
	exit 1
fi

"${DC[@]}" exec -T cacti-master sh -c \
	'printf '\''%s\n'\'' "\$path_csrf_secret = '\''/var/cacti-state/csrf-secret'\'';" >> /var/www/html/include/config.php'
MISSING_STATUS=''
for _ in $(seq 1 10); do
	MISSING_STATUS=$("${DC[@]}" exec -T cacti-master curl -sS -o /dev/null -w '%{http_code}' \
		http://127.0.0.1/index.php)
	[ "$MISSING_STATUS" = '500' ] && break
	sleep 1
done
if [ "$MISSING_STATUS" != '500' ]; then
	echo "FAIL: a completed install accepted a missing configured external secret" >&2
	exit 1
fi

"${DC[@]}" exec -T cacti-master php /var/www/html/cli/refresh_csrf.php >/dev/null
if ! "${DC[@]}" exec -T cacti-master test -f /var/cacti-state/csrf-secret; then
	echo "FAIL: refresh_csrf.php did not create the configured external secret" >&2
	exit 1
fi

"${DC[@]}" exec -T cacti-master sh -c \
	'curl -fsS -c /tmp/c06-external.jar http://127.0.0.1/index.php > /tmp/c06_external_form'
EXTERNAL_TOKEN=$("${DC[@]}" exec -T cacti-master php \
	/var/www/html/tests/e2e/docker/probes/extract_csrf.php /tmp/c06_external_form)
if ! [[ "$EXTERNAL_TOKEN" =~ ^(sid|cookie|key|user|ip):[a-f0-9]+,[0-9]+ ]]; then
	echo "FAIL: the UI did not issue a token from the configured external secret" >&2
	exit 1
fi

echo "PASS: database and external secret handoffs fail closed, rotate, and issue browser tokens"
