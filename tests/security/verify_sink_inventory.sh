#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
BASELINE="${1:-${ROOT_DIR}/tests/security/baselines/sink_inventory.baseline.tsv}"
TMP_CUR="$(mktemp)"
TMP_BASELINE="$(mktemp)"
trap 'rm -f "$TMP_CUR" "$TMP_BASELINE"' EXIT

if [ ! -f "$BASELINE" ]; then
	echo "ERROR: baseline not found: $BASELINE" >&2
	exit 1
fi

tr -d '\r' < "$BASELINE" | LC_ALL=C sort -u > "$TMP_BASELINE"
"${ROOT_DIR}/tests/security/build_sink_inventory.sh" | tr -d '\r' | LC_ALL=C sort -u > "$TMP_CUR"

if diff -u "$TMP_BASELINE" "$TMP_CUR" > /tmp/sink_inventory.diff; then
	echo "OK: sink inventory matches baseline"
	exit 0
fi

echo "ERROR: sink inventory drift detected."
echo "See: /tmp/sink_inventory.diff"
echo "If intentional, review and refresh baseline:"
echo "  tests/security/build_sink_inventory.sh | LC_ALL=C sort > tests/security/baselines/sink_inventory.baseline.tsv"
exit 1
