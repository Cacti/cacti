#!/usr/bin/env bash
# Bring the stack up, seed it, run every test in tests/ in lexical order, tear down.
# Final exit code = worst test exit code. KEEP_UP=1 to skip teardown for debugging.
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"
export CACTI_E2E_PORT

cd "$(dirname "$0")"

DC=(docker compose -f docker-compose.yml)
KEEP_UP="${KEEP_UP:-0}"

teardown() {
    if [ "$KEEP_UP" = "1" ]; then
        echo "[run] KEEP_UP=1, leaving stack up"
        return
    fi
    echo "[run] tearing down"
    "${DC[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
}
trap teardown EXIT

echo "[run] bringing stack up"
"${DC[@]}" up -d --build

echo "[run] waiting for cacti-master to serve /index.php"
ready=0
for _ in $(seq 1 60); do
    if "${DC[@]}" exec -T cacti-master sh -c 'curl -fsS http://127.0.0.1/index.php >/dev/null'; then
        ready=1
        break
    fi
    sleep 2
done
if [ "$ready" -ne 1 ]; then
    echo "[run] cacti-master never became ready" >&2
    "${DC[@]}" logs --tail 100 cacti-master >&2 || true
    exit 1
fi

./setup.sh

worst=0
shopt -s nullglob || true
test_files=(tests/[0-9][0-9]-*)
# Use a portable lexical sort (no associative arrays).
IFS=$'\n' sorted=($(printf '%s\n' "${test_files[@]}" | LC_ALL=C sort))
unset IFS

for t in "${sorted[@]}"; do
    name="$(basename "$t")"
    echo
    echo "============================================================"
    echo "[run] $name"
    echo "============================================================"
    rc=0
    case "$t" in
        *.sh)
            bash "$t" || rc=$?
            ;;
        *)
            echo "[run] skipping unknown test type: $t"
            ;;
    esac
    if [ "$rc" -ne 0 ] && [ "$rc" -gt "$worst" ]; then
        worst=$rc
    fi
    echo "[run] $name exit=$rc"
done

echo
echo "[run] worst exit code: $worst"
exit "$worst"
