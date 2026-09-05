#!/usr/bin/env bash
# Verify a rejected plugin dependency check returns native POST navigation to
# the full Plugin Management page instead of an unskinned header=false fragment.
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)
FIXTURE='dependency_fixture'
COOKIE_JAR='/tmp/c08.jar'

run_curl() {
	"${DC[@]}" exec -T cacti-master curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$@"
}

cleanup() {
	"${DC[@]}" exec -T cacti-master rm -rf \
		"/var/www/html/plugins/$FIXTURE" >/dev/null 2>&1 || true
	"${DC[@]}" exec -T cacti-master rm -f \
		"$COOKIE_JAR" /tmp/c08_login_form /tmp/c08_login_result \
		/tmp/c08_plugins /tmp/c08_result >/dev/null 2>&1 || true
}

trap cleanup EXIT

"${DC[@]}" exec -T cacti-master mkdir -p "/var/www/html/plugins/$FIXTURE"
MASTER_CONTAINER=$("${DC[@]}" ps -q cacti-master)
if [ -z "$MASTER_CONTAINER" ]; then
	echo 'FAIL: cacti-master container is not running' >&2
	exit 1
fi
docker cp "../../fixtures/plugins/$FIXTURE/." "$MASTER_CONTAINER:/var/www/html/plugins/$FIXTURE"
"${DC[@]}" exec -T cacti-master chown -R www-data:www-data "/var/www/html/plugins/$FIXTURE"
"${DC[@]}" exec -T cacti-master rm -f "$COOKIE_JAR"

run_curl -L -o /tmp/c08_login_form 'http://127.0.0.1/index.php'
CSRF=$("${DC[@]}" exec -T cacti-master php \
	/var/www/html/tests/e2e/docker/probes/extract_csrf.php /tmp/c08_login_form || true)
if [ -z "$CSRF" ]; then
	echo 'FAIL: could not extract the login CSRF token' >&2
	exit 1
fi

run_curl -L -o /tmp/c08_login_result \
	--data-urlencode 'action=login' \
	--data-urlencode 'login_username=admin' \
	--data-urlencode 'login_password=cacti-e2e-admin' \
	--data-urlencode "__csrf_magic=$CSRF" \
	--data-urlencode 'realm=local' \
	'http://127.0.0.1/index.php'

LOGIN_BODY=$("${DC[@]}" exec -T cacti-master cat /tmp/c08_login_result)
if echo "$LOGIN_BODY" | grep -q '<title>Login to Cacti</title>'; then
	echo 'FAIL: authentication returned the login page' >&2
	exit 1
fi
if ! echo "$LOGIN_BODY" | grep -qE "class=['\"]cactiPageHead['\"]"; then
	echo 'FAIL: authenticated response is missing the Cacti page layout' >&2
	exit 1
fi

run_curl -o /tmp/c08_plugins 'http://127.0.0.1/plugins.php'
if ! "${DC[@]}" exec -T cacti-master grep -q \
	'Dependency Redirect Fixture' /tmp/c08_plugins; then
	echo 'FAIL: dependency fixture is absent from Plugin Management' >&2
	exit 1
fi
if ! "${DC[@]}" exec -T cacti-master grep -qi \
	'missing_fixture_dependency' /tmp/c08_plugins; then
	echo 'FAIL: Plugin Management does not show the fixture dependency' >&2
	exit 1
fi

CSRF=$("${DC[@]}" exec -T cacti-master php \
	/var/www/html/tests/e2e/docker/probes/extract_csrf.php /tmp/c08_plugins || true)
if [ -z "$CSRF" ]; then
	echo 'FAIL: could not extract the Plugin Management CSRF token' >&2
	exit 1
fi

FINAL_URL=$(run_curl -L -o /tmp/c08_result -w '%{url_effective}' \
	--data-urlencode 'mode=install' \
	--data-urlencode "id=$FIXTURE" \
	--data-urlencode "__csrf_magic=$CSRF" \
	'http://127.0.0.1/plugins.php')

if [ "$FINAL_URL" != 'http://127.0.0.1/plugins.php' ]; then
	echo "FAIL: dependency error ended at $FINAL_URL instead of the full plugins.php page" >&2
	exit 1
fi

BODY=$("${DC[@]}" exec -T cacti-master cat /tmp/c08_result)
if ! echo "$BODY" | grep -qE "class=['\"]cactiPageHead['\"]"; then
	echo 'FAIL: dependency error response is missing the full Cacti page layout' >&2
	exit 1
fi
if ! echo "$BODY" | grep -q 'Plugin cannot be installed'; then
	echo 'FAIL: dependency error response is missing the installation error' >&2
	exit 1
fi
if ! echo "$BODY" | grep -qi 'missing_fixture_dependency'; then
	echo 'FAIL: dependency error response does not name the missing dependency' >&2
	exit 1
fi

PLUGIN_ROWS=$("${DC[@]}" exec -T cacti-db mariadb -N -B \
	-ucactiuser -pcactiuser cacti \
	-e "SELECT COUNT(*) FROM plugin_config WHERE directory='$FIXTURE'")
if [ "$PLUGIN_ROWS" != '0' ]; then
	echo 'FAIL: rejected plugin was unexpectedly installed' >&2
	exit 1
fi

echo 'PASS: plugin dependency errors retain the full Plugin Management layout'
