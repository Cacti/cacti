#!/usr/bin/env bash
# Asserts lib/html_form.php does NOT html_escape() $field_array['description'].
# Description strings in include/global_settings.php legitimately contain <strong>,
# <br>, <i> markup. Escaping turns them into '&lt;strong&gt;' which breaks the UI.
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

run_curl() {
    "${DC[@]}" exec -T cacti-master curl -sS -b /tmp/c01.jar -c /tmp/c01.jar "$@"
}

# Reset session state inside the container.
"${DC[@]}" exec -T cacti-master rm -f /tmp/c01.jar

# GET /index.php to prime the PHP session and collect the CSRF token.
run_curl -L -o /tmp/c01_form 'http://127.0.0.1/index.php'
CSRF=$("${DC[@]}" exec -T cacti-master sh -c "grep -oE 'name=.__csrf_magic. value=\"[^\"]+\"' /tmp/c01_form | head -1 | sed 's|.*value=\"\\([^\"]*\\)\"|\\1|'")
if [ -z "$CSRF" ]; then
    echo "FAIL: could not extract __csrf_magic token from index.php" >&2
    exit 1
fi

# Authenticate via /index.php (auth_login.php does not load global.php on its own).
run_curl -o /dev/null \
    --data-urlencode "action=login" \
    --data-urlencode "login_username=admin" \
    --data-urlencode "login_password=cacti-e2e-admin" \
    --data-urlencode "__csrf_magic=$CSRF" \
    --data-urlencode "realm=local" \
    'http://127.0.0.1/index.php'

# Fetch settings.php?tab=path. Several Path-tab descriptions contain literal <strong>
# (see include/global_settings.php). Also check tab=general for a <br> marker.
run_curl -L -o /tmp/c01_body "http://127.0.0.1/settings.php?tab=path"
BODY=$("${DC[@]}" exec -T cacti-master cat /tmp/c01_body)

if echo "$BODY" | grep -q '&lt;strong&gt;'; then
    echo "FAIL: '&lt;strong&gt;' present in settings.php?tab=path body — html_escape() regression detected" >&2
    echo "$BODY" | grep -n -m 3 '&lt;strong&gt;' | sed 's/^/    /' >&2
    exit 1
fi

if ! echo "$BODY" | grep -q '<strong>'; then
    echo "FAIL: no literal <strong> markup in settings.php?tab=path — page may not have rendered" >&2
    echo "$BODY" | head -c 400 >&2
    exit 1
fi

# tab=general carries the directory-pattern description with <br> + <ul>.
run_curl -L -o /tmp/c01_body "http://127.0.0.1/settings.php?tab=general"
BODY=$("${DC[@]}" exec -T cacti-master cat /tmp/c01_body)

if echo "$BODY" | grep -q '&lt;br&gt;'; then
    echo "FAIL: '&lt;br&gt;' present in settings.php?tab=general body — html_escape() regression detected" >&2
    echo "$BODY" | grep -n -m 3 '&lt;br&gt;' | sed 's/^/    /' >&2
    exit 1
fi

if ! echo "$BODY" | grep -qE '<br[^a-z]|<br>'; then
    # Not every general-tab description renders <br>; fall back to <strong>/<i>.
    if ! echo "$BODY" | grep -qE '<strong>|<i>'; then
        echo "FAIL: no literal HTML markup in settings.php?tab=general descriptions" >&2
        exit 1
    fi
fi

echo "PASS: form descriptions render raw HTML markup"
