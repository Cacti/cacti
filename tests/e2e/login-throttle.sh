#!/usr/bin/env bash
# Check throttled and successful submissions through the disposable login page.
set -euo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
export HOST_PORT="${HOST_PORT:-18421}"
compose=(docker compose -f "$root/tests/e2e/docker-compose.yml" -p "cacti_throttle_${BASHPID:-$$}")
if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then export CACTI_PR_RAW_COVERAGE=/tmp/cacti-pr-coverage; fi
trap '"${compose[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true' EXIT
"${compose[@]}" up -d --build --wait
password=$(openssl rand -hex 20)
hash=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$password")
"${compose[@]}" exec -T mariadb mariadb -ucactiuser -pcactiuser cacti <<SQL
UPDATE user_auth SET password='$hash', must_change_password='', password_change='', locked='', failed_attempts=0 WHERE id=1;
REPLACE INTO settings(name,value) VALUES ('auth_method','1'),('secpass_lockfailed','6'),('auth_throttle_ip','6'),('auth_throttle_pair','2'),('auth_throttle_window','3');
SQL
cd "$root"
CACTI_THROTTLE_TEST_PASSWORD="$password" CACTI_THROTTLE_TEST_URL="http://127.0.0.1:$HOST_PORT" \
	php include/vendor/bin/pest --configuration tests/phpunit.xml --do-not-cache-result tests/integration/LoginThrottleTest.php --filter 'password login form'
if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then
	mkdir -p "${CACTI_PR_COVERAGE_OUTPUT:?Coverage directory is required}"
	"${compose[@]}" cp cacti:/tmp/cacti-pr-coverage/. "$CACTI_PR_COVERAGE_OUTPUT"
fi
