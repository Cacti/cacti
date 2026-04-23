#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
BASELINE="${1:-${ROOT_DIR}/security/baselines/sink_inventory.baseline.tsv}"
TMP_CUR="$(mktemp)"
trap 'rm -f "$TMP_CUR"' EXIT

if [ ! -f "$BASELINE" ]; then
	echo "ERROR: baseline not found: $BASELINE" >&2
	exit 1
fi

"${ROOT_DIR}/scripts/security/build_sink_inventory.sh" | LC_ALL=C sort > "$TMP_CUR"

if diff -u "$BASELINE" "$TMP_CUR" > /tmp/sink_inventory.diff; then
	echo "OK: sink inventory matches baseline"
	exit 0
fi

echo "ERROR: sink inventory drift detected."
echo "See: /tmp/sink_inventory.diff"
echo "If intentional, review and refresh baseline:"
echo "  scripts/security/build_sink_inventory.sh | LC_ALL=C sort > security/baselines/sink_inventory.baseline.tsv"
exit 1
