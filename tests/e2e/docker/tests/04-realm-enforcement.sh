#!/usr/bin/env bash
# Asserts is_realm_allowed() is the only realm-check entry point AND that a
# low-privilege user is denied on admin-realm pages.
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

# ---- (a) Static guardrail: no parallel realm-helper definitions. ----
REPO_ROOT="$(cd ../../.. && pwd)"

# grep -E with alternation; -RnE traverses lib/ and include/. Exit 0 means matches
# found (regression). The single allowed definition is is_realm_allowed in lib/auth.php.
PARALLEL=$(grep -RnE 'function (cacti_realm_check|realm_allowed|dispatch_realm|check_user_realm)\b' \
    --include='*.php' "$REPO_ROOT/lib" "$REPO_ROOT/include" 2>/dev/null || true)

if [ -n "$PARALLEL" ]; then
    echo "FAIL: parallel realm-check helper(s) defined:" >&2
    echo "$PARALLEL" >&2
    exit 1
fi

if ! grep -nE '^function is_realm_allowed\b' "$REPO_ROOT/lib/auth.php" >/dev/null; then
    echo "FAIL: is_realm_allowed() not defined in lib/auth.php" >&2
    exit 1
fi

echo "[04] static check passed"

# ---- (b) Runtime: low-priv user must be denied on admin pages. ----
"${DC[@]}" exec -T cacti-master rm -f /tmp/c04.jar

run_curl() {
    "${DC[@]}" exec -T cacti-master curl -sS -b /tmp/c04.jar -c /tmp/c04.jar "$@"
}

# GET /index.php to prime the PHP session and collect the CSRF token.
run_curl -L -o /tmp/c04_form 'http://127.0.0.1/index.php'
CSRF=$("${DC[@]}" exec -T cacti-master sh -c "grep -oE 'name=.__csrf_magic. value=\"[^\"]+\"' /tmp/c04_form | head -1 | sed 's|.*value=\"\\([^\"]*\\)\"|\\1|'")
if [ -z "$CSRF" ]; then
    echo "FAIL: could not extract __csrf_magic token from index.php" >&2
    exit 1
fi

# Authenticate via /index.php (auth_login.php does not load global.php on its own).
run_curl -o /dev/null \
    --data-urlencode "action=login" \
    --data-urlencode "login_username=lowpriv" \
    --data-urlencode "login_password=cacti-e2e-lowpriv" \
    --data-urlencode "__csrf_magic=$CSRF" \
    --data-urlencode "realm=local" \
    'http://127.0.0.1/index.php'

assert_denied() {
    local path="$1"
    # -w writes the final HTTP status; -o keeps the body for grep below; -L follows
    # redirects so a 302 -> permission_denied.php still surfaces a useful body.
    local body status
    body=$(mktemp)
    if ! "${DC[@]}" exec -T cacti-master sh -c "curl -sSL -b /tmp/c04.jar -c /tmp/c04.jar -o /tmp/c04_body -w '%{http_code}' http://127.0.0.1${path}" > /tmp/c04_status 2>/tmp/c04_err; then
        echo "FAIL: curl could not reach $path" >&2
        cat /tmp/c04_err >&2
        return 1
    fi
    status=$(cat /tmp/c04_status)
    "${DC[@]}" exec -T cacti-master cat /tmp/c04_body > "$body" 2>/dev/null || true

    # Acceptable outcomes: 403, OR final URL was a permission-denied / login page
    # (status 200 OK on those is fine because the protected resource itself was not served).
    if [ "$status" = "403" ]; then
        echo "[04] $path -> 403 OK"
        rm -f "$body"
        return 0
    fi

    if grep -qiE 'permission[ _]?denied|access.*denied|you are not allowed|please log in|auth_login\.php' "$body"; then
        echo "[04] $path -> denied via redirect/markup OK (status=$status)"
        rm -f "$body"
        return 0
    fi

    # If the page rendered the protected admin UI, that's a fail.
    if grep -qE 'id="user_admin"|name="action" value="user_edit"|<title>[^<]*Settings' "$body"; then
        echo "FAIL: low-priv user reached protected resource $path (status=$status)" >&2
        head -c 400 "$body" >&2
        rm -f "$body"
        return 1
    fi

    echo "[04] $path -> status=$status, no admin UI in body OK"
    rm -f "$body"
}

rc=0
for p in /user_admin.php /settings.php /host.php; do
    assert_denied "$p" || rc=1
done

if [ "$rc" -ne 0 ]; then
    exit 1
fi

echo "PASS: low-priv user denied on admin-realm pages; only is_realm_allowed defined"
