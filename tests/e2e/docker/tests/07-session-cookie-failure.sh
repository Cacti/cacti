#!/usr/bin/env bash
# Verify that a rejected session cookie produces an actionable error instead
# of redirecting the login POST back to an indistinguishable empty GET.
set -euo pipefail
IFS=$'\n\t'

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

"${DC[@]}" exec -T cacti-master sh -c \
	'printf '\''%s\n'\'' "\$cacti_cookie_domain = '\''example.com'\'';" >> /var/www/html/include/config.php'
# php.ini-production caches file timestamps for two seconds.
sleep 3
"${DC[@]}" exec -T cacti-master rm -f /tmp/c07.jar /tmp/c07_form /tmp/c07_headers /tmp/c07_response

"${DC[@]}" exec -T cacti-master curl -sS \
	-c /tmp/c07.jar \
	-o /tmp/c07_form \
	http://127.0.0.1/index.php

if "${DC[@]}" exec -T cacti-master sh -c "grep -q $'\\tCacti\\t' /tmp/c07.jar"; then
	echo 'FAIL: client retained a session cookie with a mismatched Domain attribute' >&2
	exit 1
fi

CSRF=$("${DC[@]}" exec -T cacti-master php \
	/var/www/html/tests/e2e/docker/probes/extract_csrf.php /tmp/c07_form)
if [ -z "$CSRF" ]; then
	echo 'FAIL: could not extract the login CSRF token' >&2
	exit 1
fi

	STATUS=$("${DC[@]}" exec -T cacti-master curl -sS \
	-b /tmp/c07.jar \
	-c /tmp/c07.jar \
	-D /tmp/c07_headers \
	-o /tmp/c07_response \
	-w '%{http_code}' \
	--data-urlencode 'action=login' \
	--data-urlencode 'login_username=admin' \
	--data-urlencode 'login_password=cacti-e2e-admin' \
	--data-urlencode "__csrf_magic=$CSRF" \
	--data-urlencode 'realm=local' \
	http://127.0.0.1/index.php)

if [ "$STATUS" != '403' ]; then
	echo "FAIL: rejected session cookie returned HTTP $STATUS instead of 403" >&2
	"${DC[@]}" exec -T cacti-master grep -iE '^(HTTP/|Location:|Set-Cookie:)' /tmp/c07_headers >&2 || true
	exit 1
fi

if ! "${DC[@]}" exec -T cacti-master grep -q \
	'The browser did not return the Cacti session cookie' /tmp/c07_response; then
	echo 'FAIL: response did not explain the missing session cookie' >&2
	exit 1
fi

if ! "${DC[@]}" exec -T cacti-master grep -q \
	'Browser did not return the Cacti session cookie during CSRF validation' \
	/var/www/html/log/cacti.log; then
	echo 'FAIL: missing session cookie was not logged' >&2
	exit 1
fi

echo 'PASS: rejected session cookie returns an actionable 403 without redirecting'
