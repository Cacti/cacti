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

# Compare on the line-agnostic signature (type + file + code): strip the
# trailing :LINE from field 2 (location) so pure line-number drift from an
# unrelated edit no longer trips the gate and no longer forces every PR that
# shifts a file to regenerate the baseline. Matches the develop gate.
strip_line_numbers() {
	awk 'BEGIN{FS="\t";OFS="\t"} {sub(/:[0-9]+$/,"",$2); print}'
}

tr -d '\r' < "$BASELINE" | strip_line_numbers | LC_ALL=C sort -u > "$TMP_BASELINE"
"${ROOT_DIR}/tests/security/build_sink_inventory.sh" | tr -d '\r' | strip_line_numbers | LC_ALL=C sort -u > "$TMP_CUR"

if diff -u "$TMP_BASELINE" "$TMP_CUR" > /tmp/sink_inventory.diff; then
	echo "OK: sink inventory matches baseline"
	exit 0
fi

echo "ERROR: sink inventory drift detected."
echo "See: /tmp/sink_inventory.diff"
echo "If intentional, review and refresh baseline:"
echo "  tests/security/build_sink_inventory.sh | LC_ALL=C sort > tests/security/baselines/sink_inventory.baseline.tsv"
exit 1
