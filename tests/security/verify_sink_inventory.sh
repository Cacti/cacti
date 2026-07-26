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

# Compare on the line-agnostic signature (type + file + code): strip the
# trailing :LINE from field 2 (location) so pure line-number drift from an
# unrelated edit or a develop merge no longer trips the gate. Sort without -u
# so identical signatures are kept as a multiset; adding or removing one of
# several same-signature sinks still changes the comparison and is caught.
strip_line_numbers() {
	awk 'BEGIN{FS="\t";OFS="\t"} {sub(/:[0-9]+$/,"",$2); print}'
}

tr -d '\r' < "$BASELINE" | strip_line_numbers | LC_ALL=C sort > "$TMP_BASELINE"
"${ROOT_DIR}/tests/security/build_sink_inventory.sh" | tr -d '\r' | strip_line_numbers | LC_ALL=C sort > "$TMP_CUR"

if diff -u "$TMP_BASELINE" "$TMP_CUR" > "$TMP_DIFF"; then
	rm -f "$TMP_DIFF"
	echo "OK: sink inventory matches baseline"
	exit 0
fi

# $TMP_DIFF is deliberately left behind on failure so the drift can be inspected.
echo "ERROR: sink inventory drift detected."
echo "See: $TMP_DIFF"
echo "Line-number-only drift no longer fails here; refresh only for a genuine"
echo "sink add, remove, or code change:"
printf '%s\n' "  tests/security/build_sink_inventory.sh | tr -d '\\r' | LC_ALL=C sort > tests/security/baselines/sink_inventory.baseline.tsv"
exit 1
