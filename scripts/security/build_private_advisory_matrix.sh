#!/usr/bin/env bash
set -euo pipefail

if ! command -v jq >/dev/null 2>&1; then
	echo "ERROR: jq is required." >&2
	exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
	echo "ERROR: gh CLI is required." >&2
	exit 1
fi

if ! command -v git >/dev/null 2>&1; then
	echo "ERROR: git is required." >&2
	exit 1
fi

REPO="${1:-Cacti/cacti}"
BRANCHES="${2:-1.2.x develop}"
OUT_DIR="${3:-/tmp}"

mkdir -p "$OUT_DIR"

api_json="${OUT_DIR}/private_advisory_source.json"
matrix_tsv="${OUT_DIR}/private_advisory_proof_matrix.tsv"

gh api "repos/${REPO}/security-advisories" --paginate > "$api_json"

# private-like states
jq -r '.[] | select(.state=="draft" or .state=="triage") | .ghsa_id' "$api_json" | sort -u > "${OUT_DIR}/private_advisory_keys.txt"

printf 'branch\tadvisory_key_hash\tstate\tseverity\tsummary\tcommit_count\ttest_hits\tchangelog_hits\tsecurity_hits\tcode_hits\tproof_status\n' > "$matrix_tsv"

hash_key() {
	if command -v sha256sum >/dev/null 2>&1; then
		printf '%s' "$1" | sha256sum | awk '{print substr($1,1,12)}'
	else
		printf '%s' "$1" | shasum -a 256 | awk '{print substr($1,1,12)}'
	fi
}

for b in $BRANCHES; do
	branch_ref="$b"
	if ! git rev-parse --verify --quiet "$branch_ref" >/dev/null; then
		if git rev-parse --verify --quiet "origin/$b" >/dev/null; then
			branch_ref="origin/$b"
		else
			echo "WARN: branch not found locally or in origin: $b" >&2
			continue
		fi
	fi

	while IFS= read -r advisory_key; do
		[ -n "$advisory_key" ] || continue
		key_hash="$(hash_key "$advisory_key")"

		state="$(jq -r --arg k "$advisory_key" '.[]|select(.ghsa_id==$k)|.state' "$api_json" | head -1)"
		severity="$(jq -r --arg k "$advisory_key" '.[]|select(.ghsa_id==$k)|(.severity // "unknown")' "$api_json" | head -1)"
		summary="$(jq -r --arg k "$advisory_key" '.[]|select(.ghsa_id==$k)|(.summary // "")' "$api_json" | head -1 | tr '\t' ' ' | tr '\n' ' ')"

		commit_count="$(git log "$branch_ref" --oneline --grep "$advisory_key" | wc -l | tr -d ' ')"
		test_hits="$( (git grep -n "$advisory_key" "$branch_ref" -- tests 2>/dev/null || true) | wc -l | tr -d ' ' )"
		changelog_hits="$( (git grep -n "$advisory_key" "$branch_ref" -- CHANGELOG 2>/dev/null || true) | wc -l | tr -d ' ' )"
		security_hits="$( (git grep -n "$advisory_key" "$branch_ref" -- SECURITY.md 2>/dev/null || true) | wc -l | tr -d ' ' )"
		code_hits="$( (git grep -n "$advisory_key" "$branch_ref" -- lib include cli api 2>/dev/null || true) | wc -l | tr -d ' ' )"

		proof_status="NO_EVIDENCE"
		if [ "$test_hits" -gt 0 ]; then
			proof_status="PROVEN_TEST_BACKED"
		elif [ "$commit_count" -gt 0 ] && { [ "$code_hits" -gt 0 ] || [ "$changelog_hits" -gt 0 ] || [ "$security_hits" -gt 0 ]; }; then
			proof_status="PROVEN_COMMIT_LINKED"
		elif [ "$commit_count" -gt 0 ] || [ "$changelog_hits" -gt 0 ] || [ "$security_hits" -gt 0 ] || [ "$code_hits" -gt 0 ]; then
			proof_status="PARTIAL_REFERENCE"
		fi

		printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
			"$branch_ref" "$key_hash" "$state" "$severity" "$summary" \
			"$commit_count" "$test_hits" "$changelog_hits" "$security_hits" "$code_hits" "$proof_status" >> "$matrix_tsv"
	done < "${OUT_DIR}/private_advisory_keys.txt"
done

echo "WROTE: $matrix_tsv"
