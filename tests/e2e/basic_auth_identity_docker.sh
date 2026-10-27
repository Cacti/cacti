#!/usr/bin/env bash
# Exercise the real Apache -> PHP -> Cacti session boundary in a disposable stack.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
COMPOSE_PROJECT="${COMPOSE_PROJECT:-cacti_basic_identity_$$}"
export HOST_PORT="${HOST_PORT:-18392}"
cd "$SCRIPT_DIR"
if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then export CACTI_PR_RAW_COVERAGE=/tmp/cacti-pr-coverage; fi
DC=(docker compose -p "$COMPOSE_PROJECT")
cleanup() { "${DC[@]}" down -v --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT
"${DC[@]}" up -d --build
ready=0
for _ in $(seq 1 60); do
	if "${DC[@]}" exec -T cacti curl -fsS http://127.0.0.1/ >/dev/null 2>&1; then ready=1; break; fi
	sleep 3
done
[ "$ready" = 1 ] || { echo 'FAIL: Cacti never became ready' >&2; exit 1; }
sql() { "${DC[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti -e "$1"; }
request() {
	local route="$1"; shift
	"${DC[@]}" exec -T cacti curl -sS -b /tmp/basic.jar -c /tmp/basic.jar \
		-o /tmp/basic-response -w '%{http_code}' "$@" "http://127.0.0.1/$route"
}
assert_user() {
	"${DC[@]}" exec -T cacti php -r '
		$data = json_decode(file_get_contents("/tmp/basic-response"), true);
		if (($data["user_id"] ?? 0) !== (int) $argv[1]) {
			fwrite(STDERR, "FAIL: unexpected authenticated identity\n" . substr(strip_tags(preg_replace("~<script.*?</script>~s", "", file_get_contents("/tmp/basic-response"))), -1200)); exit(1);
		}' "$1"
}
assert_denied() {
	"${DC[@]}" exec -T cacti grep -qF "$1" /tmp/basic-response || {
		echo 'FAIL: request was not denied as expected' >&2
		"${DC[@]}" exec -T cacti head -c 1800 /tmp/basic-response >&2 || true
		"${DC[@]}" exec -T cacti tail -15 /var/www/html/log/cacti.log >&2 || true
		exit 1;
	}
}
cookie_id() { "${DC[@]}" exec -T cacti awk '$6 == "Cacti" {print $7}' /tmp/basic.jar; }

sql "REPLACE INTO settings(name,value) VALUES('auth_method','2'),('guest_user','0');
	UPDATE user_auth SET username='basic_one',realm=2,enabled='on',locked='' WHERE id=1001;
	INSERT INTO user_auth(id,username,password,realm,full_name,enabled) VALUES(1002,'basic_two','!',2,'Basic Two','on');
	INSERT INTO user_auth_realm(user_id,realm_id) SELECT 1002,realm_id FROM user_auth_realm WHERE user_id=1;"
"${DC[@]}" exec -T cacti bash -s <<'SH'
set -euo pipefail
php -r 'file_put_contents("/tmp/cacti-basic-passwords", "basic_one:" . password_hash("fixture-pass", PASSWORD_BCRYPT) . "\nbasic_two:" . password_hash("fixture-pass", PASSWORD_BCRYPT) . "\n");'
chmod 644 /tmp/cacti-basic-passwords
cp /var/www/html/include/config.php /tmp/basic-original-config.php
cat > /var/www/html/basic-auth-endpoint.php <<'PHP'
<?php
// Disposable test endpoint; the production authentication entry point runs unchanged.
// Config changes in this test must not wait for OPcache's timestamp interval.
if (function_exists('opcache_invalidate')) { opcache_invalidate(__DIR__ . '/include/config.php', true); }
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/include/auth.php';
header('Content-Type: application/json');
print json_encode(['user_id' => (int) $_SESSION[SESS_USER_ID]]);
PHP
cat > /etc/apache2/conf-available/cacti-basic-test.conf <<'APACHE'
Alias /basic-verified.php /var/www/html/basic-auth-endpoint.php
Alias /basic-unverified.php /var/www/html/basic-auth-endpoint.php
<Location /basic-verified.php>
    AuthType Basic
    AuthName "Cacti test"
    AuthUserFile /tmp/cacti-basic-passwords
    Require valid-user
</Location>
APACHE
a2enconf cacti-basic-test >/dev/null
apachectl -k graceful
SH

echo '[basic-e2e] decoded client credentials do not authenticate by default'
request basic-unverified.php -u basic_one:anything >/dev/null
assert_denied 'no username was passed'
code=$(request basic-verified.php -u basic_one:wrong)
[ "$code" = 401 ] || { echo 'FAIL: Apache accepted a wrong password' >&2; exit 1; }

echo '[basic-e2e] verified identity creates a session and a repeated request retains it'
request basic-verified.php -u basic_one:fixture-pass >/dev/null
assert_user 1001
first=$(cookie_id)
request basic-verified.php -u basic_one:fixture-pass >/dev/null
assert_user 1001
[ "$first" = "$(cookie_id)" ] || { echo 'FAIL: unchanged identity rotated its session repeatedly' >&2; exit 1; }

echo '[basic-e2e] changing verified identity replaces and rotates the session'
request basic-verified.php -u basic_two:fixture-pass >/dev/null
assert_user 1002
[ "$first" != "$(cookie_id)" ] || { echo 'FAIL: identity change reused the old session ID' >&2; exit 1; }

echo '[basic-e2e] disabled, locked and access-less accounts cannot restore sessions'
sql "UPDATE user_auth SET enabled='' WHERE id=1002"
request basic-verified.php -u basic_two:fixture-pass >/dev/null
assert_denied 'User account disabled'
sql "UPDATE user_auth SET enabled='on' WHERE id=1002"
request basic-verified.php -u basic_two:fixture-pass >/dev/null
assert_user 1002
sql "UPDATE user_auth SET locked='on' WHERE id=1002"
request basic-verified.php -u basic_two:fixture-pass >/dev/null
assert_denied 'User account locked'
sql "UPDATE user_auth SET locked='' WHERE id=1002"
request basic-verified.php -u basic_two:fixture-pass >/dev/null
assert_user 1002
sql 'DELETE FROM user_auth_realm WHERE user_id=1002'
request basic-verified.php -u basic_two:fixture-pass >/dev/null
assert_denied 'You do not have access to any area'
sql 'INSERT INTO user_auth_realm(user_id,realm_id) SELECT 1002,realm_id FROM user_auth_realm WHERE user_id=1'
request basic-verified.php -u basic_two:fixture-pass >/dev/null
assert_user 1002

echo '[basic-e2e] a saved session does not replace a missing upstream identity'
request basic-unverified.php >/dev/null
assert_denied 'no username was passed'
request basic-unverified.php -H 'X-Forwarded-User: basic_one' >/dev/null
assert_denied 'no username was passed'

"${DC[@]}" exec -T cacti bash -s <<'SH'
cp /tmp/basic-original-config.php /var/www/html/include/config.php
printf '\n$trusted_proxies = ["127.0.0.1"];\n' >> /var/www/html/include/config.php
SH
echo '[basic-e2e] explicitly trusted forwarded identity authenticates'
request basic-unverified.php -H 'X-Forwarded-User: basic_one' >/dev/null
assert_user 1001

"${DC[@]}" exec -T cacti bash -s <<'SH'
cp /tmp/basic-original-config.php /var/www/html/include/config.php
printf '\n$auth_basic_allow_php_auth_user = true;\n' >> /var/www/html/include/config.php
SH
echo '[basic-e2e] legacy PHP_AUTH_USER requires the explicit upstream-verification opt-in'
request basic-unverified.php -u basic_two:fixture-pass >/dev/null
assert_user 1002
if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then
	: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage output directory is required}"
	mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
	"${DC[@]}" cp cacti:/tmp/cacti-pr-coverage/. "$CACTI_PR_COVERAGE_OUTPUT"
fi
echo 'PASS: Basic identity, upstream verification, eligibility and session transitions'
