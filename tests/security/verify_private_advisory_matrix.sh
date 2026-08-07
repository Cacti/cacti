#!/usr/bin/env bash
set -euo pipefail

MATRIX_FILE="${1:-/tmp/private_advisory_proof_matrix.tsv}"
ALLOW_PARTIAL="${ALLOW_PARTIAL:-0}"

if [ ! -f "$MATRIX_FILE" ]; then
	echo "ERROR: matrix file not found: $MATRIX_FILE" >&2
	exit 1
fi

total="$(awk -F'\t' 'NR>1 {n++} END {print n+0}' "$MATRIX_FILE")"
no_evidence="$(awk -F'\t' 'NR>1 && $NF=="NO_EVIDENCE" {n++} END {print n+0}' "$MATRIX_FILE")"
partial="$(awk -F'\t' 'NR>1 && $NF=="PARTIAL_REFERENCE" {n++} END {print n+0}' "$MATRIX_FILE")"
changelog_column="$(awk -F'\t' 'NR==1 {for (i=1; i<=NF; i++) if ($i=="changelog_hits") print i}' "$MATRIX_FILE")"

if [ -z "$changelog_column" ]; then
	echo "ERROR: matrix header is missing changelog_hits." >&2
	exit 1
fi

missing_changelog="$(awk -F'\t' -v column="$changelog_column" 'NR>1 && ($column+0)==0 {n++} END {print n+0}' "$MATRIX_FILE")"

echo "matrix_total=${total}"
echo "matrix_no_evidence=${no_evidence}"
echo "matrix_partial=${partial}"
echo "matrix_missing_changelog=${missing_changelog}"

if [ "$no_evidence" -gt 0 ]; then
	echo "ERROR: unresolved advisories with NO_EVIDENCE." >&2
	exit 1
fi

if [ "$ALLOW_PARTIAL" != "1" ] && [ "$partial" -gt 0 ]; then
	echo "ERROR: unresolved advisories with PARTIAL_REFERENCE." >&2
	exit 1
fi

if [ "$missing_changelog" -gt 0 ]; then
	echo "ERROR: private advisories without a GHSA reference in CHANGELOG." >&2
	exit 1
fi

echo "OK: matrix closure criteria satisfied."
