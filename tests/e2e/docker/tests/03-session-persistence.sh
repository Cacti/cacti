#!/usr/bin/env bash
# 03-session-persistence: log in once via index.php, then navigate to five
# realm-protected pages with the same cookie jar. Fail on any auth bounce,
# 4xx response, or missing admin layout marker.
#
# Guards against cacti_auth_transition() destroying the session via
# session_regenerate_id(true) + aggressive cookie rotation — if that bug is
# present, the second request in the loop will redirect to auth_login.php.
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

run_curl() {
    "${DC[@]}" exec -T cacti-master curl -sS -b /tmp/c03.jar -c /tmp/c03.jar "$@"
}

# Reset session state inside the container.
"${DC[@]}" exec -T cacti-master rm -f /tmp/c03.jar

# GET /index.php to prime the PHP session and collect the CSRF token.
run_curl -L -o /tmp/c03_form 'http://127.0.0.1/index.php'
CSRF=$("${DC[@]}" exec -T cacti-master sh -c "grep -oE 'name=.__csrf_magic. value=\"[^\"]+\"' /tmp/c03_form | head -1 | sed 's|.*value=\"\\([^\"]*\\)\"|\\1|'")
if [ -z "$CSRF" ]; then
    echo "FAIL: could not extract __csrf_magic token from index.php" >&2
    exit 1
fi

# POST credentials to /index.php. -w writes the final URL to stdout, which we
# capture via $() so the value lives in a shell variable rather than racing a
# host-vs-container path conflict.
FINAL_URL=$(run_curl -L \
    -o /tmp/c03_post \
    -w '%{url_effective}' \
    --data-urlencode "action=login" \
    --data-urlencode "login_username=admin" \
    --data-urlencode "login_password=cacti-e2e-admin" \
    --data-urlencode "__csrf_magic=$CSRF" \
    --data-urlencode "realm=local" \
    'http://127.0.0.1/index.php')

LOGIN_BODY=$("${DC[@]}" exec -T cacti-master cat /tmp/c03_post)

# Fail fast if login itself bounced back to the login page.
if echo "$FINAL_URL" | grep -q 'auth_login\.php'; then
    echo "FAIL: login redirected to auth_login.php (URL: $FINAL_URL)" >&2
    exit 1
fi
if echo "$LOGIN_BODY" | grep -q '<title>Login to Cacti</title>'; then
    echo "FAIL: login response body contains login-page title — authentication did not succeed" >&2
    exit 1
fi
if ! echo "$LOGIN_BODY" | grep -qE "id='main_logo'|id=\"main_logo\"|class='cactiPageHead'|class=\"cactiPageHead\""; then
    echo "FAIL: login response body is missing admin layout marker (main_logo / cactiPageHead)" >&2
    echo "$LOGIN_BODY" | head -c 500 >&2
    exit 1
fi

# Navigate to each realm-protected page in order, carrying the same cookie jar.
# If cacti_auth_transition() destroys the session at login, the second fetch
# will receive a redirect to auth_login.php and the assertions below catch it.
PAGES=(
    '/index.php'
    '/host.php'
    '/data_sources.php'
    '/graphs.php'
    '/user_admin.php'
)

i=0
for path in "${PAGES[@]}"; do
    i=$((i + 1))

    HTTP_CODE=$(run_curl -L \
        -o /tmp/c03_body \
        -w '%{http_code}' \
        "http://127.0.0.1${path}")

    BODY=$("${DC[@]}" exec -T cacti-master cat /tmp/c03_body)
    EFFECTIVE_URL=$(run_curl -L \
        -o /dev/null \
        -w '%{url_effective}' \
        "http://127.0.0.1${path}")

    if [ "$HTTP_CODE" -ge 400 ]; then
        echo "FAIL [step $i, $path]: HTTP $HTTP_CODE" >&2
        exit 1
    fi

    if echo "$EFFECTIVE_URL" | grep -q 'auth_login\.php'; then
        echo "FAIL [step $i, $path]: redirected to auth_login.php — session was destroyed" >&2
        exit 1
    fi

    if echo "$BODY" | grep -q '<title>Login to Cacti</title>'; then
        echo "FAIL [step $i, $path]: response body contains login-page title — session was destroyed" >&2
        exit 1
    fi

    if ! echo "$BODY" | grep -qE "id='main_logo'|id=\"main_logo\"|class='cactiPageHead'|class=\"cactiPageHead\""; then
        echo "FAIL [step $i, $path]: admin layout marker (main_logo / cactiPageHead) absent" >&2
        echo "$BODY" | head -c 500 >&2
        exit 1
    fi
done

echo "PASS: admin session survives 5 navigations after login"
