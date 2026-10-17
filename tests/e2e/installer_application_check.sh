#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
# Runs inside the disposable installer application container.
set -euo pipefail
cd /var/www/html/cacti

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }

login() {
  local user="$1" password="$2" realm="$3" jar="$4"
  curl --fail --silent --show-error -L -c "$jar" http://127.0.0.1/cacti/ -o /tmp/installer-login-form
  local token
  token="$(php tests/e2e/extract_csrf.php /tmp/installer-login-form)" || fail "missing CSRF token on the $user login form"
  curl --fail --silent --show-error -L -b "$jar" -c "$jar" \
    --data-urlencode 'action=login' --data-urlencode "__csrf_magic=$token" \
    --data-urlencode "login_username=$user" --data-urlencode "login_password=$password" \
    --data-urlencode "realm=$realm" http://127.0.0.1/cacti/ -o /tmp/installer-login-result
  if grep -Eq '<form[^>]+id=.auth.|FATAL:|Fatal error|Uncaught' /tmp/installer-login-result; then
    fail "post-upgrade $user login did not reach the application"
  fi
}

# The fixtures contain synthetic, disposable credentials only.
login admin installer-e2e-pass 0 /tmp/installer-admin-cookie
for page in index.php host.php graphs.php; do
  curl --fail --silent --show-error -L -b /tmp/installer-admin-cookie "http://127.0.0.1/cacti/$page" -o /tmp/installer-page
  grep -q 'cactiPageHead\|main_logo' /tmp/installer-page || fail "missing application layout on $page"
  if grep -q 'Fatal error\|Uncaught\|Login to Cacti' /tmp/installer-page; then
    fail "post-upgrade page failed: $page"
  fi
done
curl --fail --silent --show-error -b /tmp/installer-admin-cookie http://127.0.0.1/cacti/install/report.php -o /tmp/installer-report
php -r '$r=json_decode(file_get_contents("/tmp/installer-report"),true,512,JSON_THROW_ON_ERROR); if(!isset($r["run_id"],$r["operations"]) || $r["state"] !== "succeeded") exit(1);' || fail 'downloaded installer report did not record successful completion'
login ldapuser ldap-e2e-pass 1007 /tmp/installer-ldap-cookie
grep -q 'cactiPageHead\|main_logo' /tmp/installer-login-result || fail 'LDAP login did not reach the application'
printf 'PASS: upgraded local/LDAP login, authenticated pages, session cookies and installer report\n'

CSRF_STATUS="$(curl --silent --show-error -b /tmp/installer-admin-cookie -c /tmp/installer-admin-cookie -D /tmp/installer-csrf-headers -o /tmp/installer-csrf-rejection -w '%{http_code}' --data 'action=save&id=700&description=CSRF%20rejected%20mutation&__csrf_magic=invalid' http://127.0.0.1/cacti/host.php)"
[ "$CSRF_STATUS" -eq 302 ] || fail 'invalid CSRF token did not trigger the application recovery redirect'
grep -Eq '^Location: /cacti/host.php[[:space:]]*$' /tmp/installer-csrf-headers || fail 'CSRF recovery redirected away from the application'
