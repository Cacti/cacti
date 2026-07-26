#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
BASELINE="${1:-${ROOT_DIR}/tests/security/baselines/architectural_hotspots.baseline.tsv}"
TMP_CUR="$(mktemp)"
TMP_BASELINE="$(mktemp)"
TMP_NEW="$(mktemp)"
trap 'rm -f "$TMP_CUR" "$TMP_BASELINE" "$TMP_NEW"' EXIT

if [ ! -f "$BASELINE" ]; then
	echo "ERROR: baseline not found: $BASELINE" >&2
	exit 1
fi

# Compare on the line-agnostic signature (class + file + code): strip the
# trailing :LINE from field 2 (location) so pure line-number drift from an
# unrelated edit or a develop merge no longer trips the gate. Sort without -u
# so identical signatures are kept as a multiset; an added same-signature
# hotspot still changes the comparison and is caught.
strip_line_numbers() {
	awk 'BEGIN{FS="\t";OFS="\t"} {sub(/:[0-9]+$/,"",$2); print}'
}

tr -d '\r' < "$BASELINE" | strip_line_numbers | LC_ALL=C sort > "$TMP_BASELINE"
"${ROOT_DIR}/tests/security/build_architectural_helper_report.sh" --hotspots | tr -d '\r' | strip_line_numbers | LC_ALL=C sort > "$TMP_CUR"

# Detect only newly introduced hotspots (existing baseline debt is tolerated).
comm -13 "$TMP_BASELINE" "$TMP_CUR" > "$TMP_NEW" || true

if [ ! -s "$TMP_NEW" ]; then
	echo "OK: no new architectural hotspots."
	exit 0
fi

echo "ERROR: new architectural hotspots detected:"
cat "$TMP_NEW"
echo
echo "Line-number-only drift no longer fails here; refresh only when a genuine"
echo "new hotspot is intentional and reviewed:"
printf '%s\n' "  tests/security/build_architectural_helper_report.sh --hotspots | tr -d '\\r' | LC_ALL=C sort > tests/security/baselines/architectural_hotspots.baseline.tsv"
exit 1
