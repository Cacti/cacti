#!/usr/bin/env bash
# Boot the develop e2e compose stack (Cacti + MariaDB + OpenLDAP) and drive
# LDAP domain login through index.php.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_PROJECT="${COMPOSE_PROJECT:-cacti_ldap_e2e_$$}"
export HOST_PORT="${HOST_PORT:-18090}"

cleanup() {
	cd "$SCRIPT_DIR"
	docker compose -p "$COMPOSE_PROJECT" down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

cd "$SCRIPT_DIR"

echo "[ldap-e2e] building stack on HOST_PORT=${HOST_PORT}"
docker compose -p "$COMPOSE_PROJECT" up -d --build

echo "[ldap-e2e] waiting for Cacti"
ready=0
for _ in $(seq 1 60); do
	if docker compose -p "$COMPOSE_PROJECT" exec -T cacti curl -fsS http://127.0.0.1/ >/dev/null 2>&1; then
		ready=1
		break
	fi
	sleep 3
done
if [ "$ready" -ne 1 ]; then
	echo "[ldap-e2e] Cacti never became ready" >&2
	docker compose -p "$COMPOSE_PROJECT" logs --no-color >&2 || true
	exit 1
fi

DC=(docker compose -p "$COMPOSE_PROJECT")

run_curl() {
	"${DC[@]}" exec -T cacti curl -sS -b "$1" -c "$1" "${@:2}"
}

login_post() {
	local jar="$1"
	shift

	"${DC[@]}" exec -T cacti rm -f "$jar"
	run_curl "$jar" -L -o /tmp/ldap_form 'http://127.0.0.1/'
	local csrf
	csrf=$("${DC[@]}" exec -T cacti php /var/www/html/tests/e2e/extract_csrf.php /tmp/ldap_form || true)
	if [ -z "$csrf" ]; then
		echo "FAIL: could not extract __csrf_magic token" >&2
		"${DC[@]}" exec -T cacti head -c 800 /tmp/ldap_form >&2 || true
		exit 1
	fi

	run_curl "$jar" -L \
		-o /tmp/ldap_post \
		-w '%{url_effective}' \
		--data-urlencode "action=login" \
		--data-urlencode "__csrf_magic=$csrf" \
		"$@" \
		'http://127.0.0.1/'
}

body_is_login_page() {
	"${DC[@]}" exec -T cacti grep -q '<title>Login to Cacti</title>' /tmp/ldap_post
}

body_has_app_layout() {
	"${DC[@]}" exec -T cacti grep -qE "id='main_logo'|id=\"main_logo\"|class='cactiPageHead'|class=\"cactiPageHead\"" /tmp/ldap_post
}

echo "[ldap-e2e] valid LDAP credentials with realm 1001 log in"
FINAL_URL=$(login_post /tmp/c_ldap_ok.jar \
	--data-urlencode "login_username=ldapuser" \
	--data-urlencode "login_password=ldap-e2e-pass" \
	--data-urlencode "realm=1001")
if echo "$FINAL_URL" | grep -q 'auth_login\.php'; then
	echo "FAIL: valid LDAP login redirected to auth_login.php ($FINAL_URL)" >&2
	exit 1
fi
if body_is_login_page; then
	echo "FAIL: valid LDAP login still rendered the login page" >&2
	"${DC[@]}" exec -T cacti head -c 800 /tmp/ldap_post >&2 || true
	exit 1
fi
if ! body_has_app_layout; then
	echo "FAIL: valid LDAP login missing application layout marker" >&2
	"${DC[@]}" exec -T cacti head -c 800 /tmp/ldap_post >&2 || true
	exit 1
fi

echo "[ldap-e2e] wrong LDAP password stays on the login page"
login_post /tmp/c_ldap_bad.jar \
	--data-urlencode "login_username=ldapuser" \
	--data-urlencode "login_password=wrong-password" \
	--data-urlencode "realm=1001" >/dev/null
if ! body_is_login_page; then
	echo "FAIL: bad LDAP password did not stay on the login page" >&2
	exit 1
fi

echo "[ldap-e2e] empty LDAP password stays on the login page"
login_post /tmp/c_ldap_empty.jar \
	--data-urlencode "login_username=ldapuser" \
	--data-urlencode "login_password=" \
	--data-urlencode "realm=1001" >/dev/null
if ! body_is_login_page; then
	echo "FAIL: empty LDAP password did not stay on the login page" >&2
	exit 1
fi

echo "[ldap-e2e] forged realm 2 does not skip the bind"
BEFORE=$("${DC[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti -Nse \
	"SELECT COUNT(*) FROM user_auth WHERE username='attacker'")
login_post /tmp/c_ldap_forge.jar \
	--data-urlencode "login_username=attacker" \
	--data-urlencode "login_password=anything" \
	--data-urlencode "realm=2" >/dev/null
if ! body_is_login_page; then
	echo "FAIL: forged realm 2 left the login page — bind skip is back" >&2
	exit 1
fi
AFTER=$("${DC[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti -Nse \
	"SELECT COUNT(*) FROM user_auth WHERE username='attacker'")
if [ "$AFTER" != "$BEFORE" ]; then
	echo "FAIL: forged realm 2 created a user_auth row (before=$BEFORE after=$AFTER)" >&2
	exit 1
fi

echo "PASS: LDAP login bind, rejection, and forged-realm paths"
