#!/usr/bin/env bash
# LDAP domain login through index.php against the OpenLDAP service.
#
# Asserts a real bind succeeds, a bad password stays on the login page, an
# empty password stays on the login page, and a posted realm of 2 (the old
# skip-the-bind hole) does not create a session or a user_auth row.
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

run_curl() {
	"${DC[@]}" exec -T cacti-master curl -sS -b "$1" -c "$1" "${@:2}"
}

login_post() {
	local jar="$1"
	shift

	"${DC[@]}" exec -T cacti-master rm -f "$jar"

	run_curl "$jar" -L -o /tmp/ldap_form 'http://127.0.0.1/index.php'
	local csrf
	csrf=$("${DC[@]}" exec -T cacti-master php /var/www/html/tests/e2e/docker/probes/extract_csrf.php /tmp/ldap_form || true)
	if [ -z "$csrf" ]; then
		echo "FAIL: could not extract __csrf_magic token from index.php" >&2
		exit 1
	fi

	run_curl "$jar" -L \
		-o /tmp/ldap_post \
		-w '%{url_effective}' \
		--data-urlencode "action=login" \
		--data-urlencode "__csrf_magic=$csrf" \
		"$@" \
		'http://127.0.0.1/index.php'
}

body_is_login_page() {
	"${DC[@]}" exec -T cacti-master grep -q '<title>Login to Cacti</title>' /tmp/ldap_post
}

body_has_app_layout() {
	"${DC[@]}" exec -T cacti-master grep -qE "id='main_logo'|id=\"main_logo\"|class='cactiPageHead'|class=\"cactiPageHead\"" /tmp/ldap_post
}

echo "[08] valid LDAP credentials with realm 1001 log in"
FINAL_URL=$(login_post /tmp/c08_ok.jar \
	--data-urlencode "login_username=ldapuser" \
	--data-urlencode "login_password=ldap-e2e-pass" \
	--data-urlencode "realm=1001")
if echo "$FINAL_URL" | grep -q 'auth_login\.php'; then
	echo "FAIL: valid LDAP login redirected to auth_login.php ($FINAL_URL)" >&2
	exit 1
fi
if body_is_login_page; then
	echo "FAIL: valid LDAP login still rendered the login page" >&2
	"${DC[@]}" exec -T cacti-master head -c 800 /tmp/ldap_post >&2 || true
	exit 1
fi
if ! body_has_app_layout; then
	echo "FAIL: valid LDAP login missing application layout marker" >&2
	"${DC[@]}" exec -T cacti-master head -c 800 /tmp/ldap_post >&2 || true
	exit 1
fi

echo "[08] wrong LDAP password stays on the login page"
login_post /tmp/c08_bad.jar \
	--data-urlencode "login_username=ldapuser" \
	--data-urlencode "login_password=wrong-password" \
	--data-urlencode "realm=1001" >/dev/null
if ! body_is_login_page; then
	echo "FAIL: bad LDAP password did not stay on the login page" >&2
	exit 1
fi

echo "[08] empty LDAP password stays on the login page"
login_post /tmp/c08_empty.jar \
	--data-urlencode "login_username=ldapuser" \
	--data-urlencode "login_password=" \
	--data-urlencode "realm=1001" >/dev/null
if ! body_is_login_page; then
	echo "FAIL: empty LDAP password did not stay on the login page" >&2
	exit 1
fi

echo "[08] forged realm 2 does not skip the bind"
BEFORE=$("${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser cacti -Nse \
	"SELECT COUNT(*) FROM user_auth WHERE username='attacker'")
login_post /tmp/c08_forge.jar \
	--data-urlencode "login_username=attacker" \
	--data-urlencode "login_password=anything" \
	--data-urlencode "realm=2" >/dev/null
if ! body_is_login_page; then
	echo "FAIL: forged realm 2 left the login page — bind skip is back" >&2
	exit 1
fi
AFTER=$("${DC[@]}" exec -T cacti-db mariadb -ucactiuser -pcactiuser cacti -Nse \
	"SELECT COUNT(*) FROM user_auth WHERE username='attacker'")
if [ "$AFTER" != "$BEFORE" ]; then
	echo "FAIL: forged realm 2 created a user_auth row (before=$BEFORE after=$AFTER)" >&2
	exit 1
fi

echo "PASS: LDAP login bind, rejection, and forged-realm paths"
