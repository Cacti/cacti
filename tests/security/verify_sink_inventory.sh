#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
BASELINE="${1:-${ROOT_DIR}/tests/security/baselines/sink_inventory.baseline.tsv}"
TMP_CUR="$(mktemp)"
TMP_BASELINE="$(mktemp)"
TMP_DIFF="$(mktemp)"
trap 'rm -f "$TMP_CUR" "$TMP_BASELINE"' EXIT

if [ ! -f "$BASELINE" ]; then
	echo "ERROR: baseline not found: $BASELINE" >&2
	exit 1
fi

tr -d '\r' < "$BASELINE" | LC_ALL=C sort -u > "$TMP_BASELINE"
"${ROOT_DIR}/tests/security/build_sink_inventory.sh" | tr -d '\r' | LC_ALL=C sort -u > "$TMP_CUR"

if diff -u "$TMP_BASELINE" "$TMP_CUR" > "$TMP_DIFF"; then
	rm -f "$TMP_DIFF"
	echo "OK: sink inventory matches baseline"
	exit 0
fi

# $TMP_DIFF is deliberately left behind on failure so the drift can be inspected.
echo "ERROR: sink inventory drift detected."
echo "See: $TMP_DIFF"
echo "If intentional, review and refresh baseline:"
printf '%s\n' "  tests/security/build_sink_inventory.sh | tr -d '\\r' | LC_ALL=C sort -u > tests/security/baselines/sink_inventory.baseline.tsv"
exit 1
