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

# The image otherwise grants each user read access only to their own entry.
# Permit authenticated users to read this fixture group for the membership gate.
"${DC[@]}" exec -T openldap ldapmodify -Q -Y EXTERNAL -H ldapi:/// <<'LDIF'
dn: olcDatabase={1}mdb,cn=config
changetype: modify
add: olcAccess
olcAccess: {2}to dn.base="cn=cacti-operators,ou=groups,dc=example,dc=org" by users read by * none
olcAccess: {3}to dn.base="ou=users,dc=example,dc=org" by users read by * none
LDIF

echo "[ldap-e2e] first login provisions claims with a required DN group"
"${DC[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti -e \
	"UPDATE login_providers SET user_id=1, parameters=JSON_SET(parameters, '$.mode', 2, '$.specific_dn', 'cn=admin,dc=example,dc=org', '$.specific_password', 'adminpassword', '$.group_dn', 'cn=cacti-operators,ou=groups,dc=example,dc=org', '$.group_attrib', 'member', '$.group_member_type', 1, '$.claim_full_name', 'displayName', '$.claim_email', 'mail') WHERE id=1;"
login_post /tmp/c_ldap_claims.jar \
	--data-urlencode "login_username=claimuser" \
	--data-urlencode "login_password=ldap-e2e-pass" \
	--data-urlencode "realm=1001" >/dev/null
if ! body_has_app_layout || body_is_login_page; then
	echo "FAIL: first LDAP claim login did not reach the application" >&2
	"${DC[@]}" exec -T openldap ldapsearch -x -H ldap://127.0.0.1 -b 'cn=cacti-operators,ou=groups,dc=example,dc=org' -D 'cn=claimuser,ou=users,dc=example,dc=org' -w ldap-e2e-pass -LLL member >&2 || true
	"${DC[@]}" exec -T cacti tail -60 /var/www/html/log/cacti.log >&2 || true
	"${DC[@]}" exec -T cacti head -c 3000 /tmp/ldap_post >&2 || true
	exit 1
fi
CLAIMS=$("${DC[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti -Nse \
	"SELECT CONCAT(full_name, '|', email_address) FROM user_auth WHERE username='claimuser' AND realm=1001")
if [ "$CLAIMS" != 'Claim User|claimuser@example.org' ]; then
	echo "FAIL: LDAP claim attributes were not applied to the provisioned account" >&2
	exit 1
fi

echo "[ldap-e2e] a valid bind without group membership cannot provision an account"
login_post /tmp/c_ldap_outside.jar \
	--data-urlencode "login_username=outsideuser" \
	--data-urlencode "login_password=ldap-e2e-pass" \
	--data-urlencode "realm=1001" >/dev/null
OUTSIDE=$("${DC[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti -Nse \
	"SELECT COUNT(*) FROM user_auth WHERE username='outsideuser'")
if ! body_is_login_page || [ "$OUTSIDE" != 0 ]; then
	echo "FAIL: LDAP group restriction admitted or provisioned a nonmember" >&2
	exit 1
fi

echo "[ldap-e2e] resolved-DN membership also accepts the group member"
"${DC[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti -e \
	"UPDATE login_providers SET parameters=JSON_SET(parameters, '$.group_member_type', 2) WHERE id=1;"
login_post /tmp/c_ldap_dn.jar \
	--data-urlencode "login_username=claimuser" \
	--data-urlencode "login_password=ldap-e2e-pass" \
	--data-urlencode "realm=1001" >/dev/null
if ! body_has_app_layout || body_is_login_page; then
	echo "FAIL: resolved-DN group membership rejected the member" >&2
	exit 1
fi

echo "PASS: LDAP login bind, rejection, forged realm, provisioning claims and DN groups"
