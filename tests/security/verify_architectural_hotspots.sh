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

# Compare on the line-agnostic signature (type + file + code): strip the
# trailing :LINE from field 2 (location) so pure line-number drift from an
# unrelated edit no longer trips the gate and no longer forces every PR that
# shifts a file to regenerate the baseline. Matches the develop gate.
strip_line_numbers() {
	awk 'BEGIN{FS="\t";OFS="\t"} {sub(/:[0-9]+$/,"",$2); print}'
}

tr -d '\r' < "$BASELINE" | strip_line_numbers | LC_ALL=C sort -u > "$TMP_BASELINE"
"${ROOT_DIR}/tests/security/build_architectural_helper_report.sh" --hotspots | tr -d '\r' | strip_line_numbers | LC_ALL=C sort -u > "$TMP_CUR"

# Detect only newly introduced hotspots (existing baseline debt is tolerated).
comm -13 "$TMP_BASELINE" "$TMP_CUR" > "$TMP_NEW" || true

if [ ! -s "$TMP_NEW" ]; then
	echo "OK: no new architectural hotspots."
	exit 0
fi

echo "ERROR: new architectural hotspots detected:"
cat "$TMP_NEW"
echo
echo "If this is intentional and reviewed, refresh baseline:"
echo "  tests/security/build_architectural_helper_report.sh --hotspots | LC_ALL=C sort > tests/security/baselines/architectural_hotspots.baseline.tsv"
exit 1
