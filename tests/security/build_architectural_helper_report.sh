#!/usr/bin/env bash
# ripgrep returns exit 1 when a hotspot category has zero matches. With errexit
# that would abort the whole report on the first empty category (some categories
# are legitimately empty on develop), so errexit is intentionally omitted here;
# pipefail is kept and each pipeline's rg status is checked explicitly instead
# (exit 1 = no matches is tolerated, exit > 1 = real rg error fails the report).
set -uo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
cd "$ROOT_DIR" || exit 1

if ! command -v rg >/dev/null 2>&1; then
	echo "ERROR: ripgrep (rg) is required." >&2
	exit 1
fi

MODE="${1:---summary}"

EXCLUDE=(
	--glob '!include/vendor/**'
	--glob '!locales/**'
	--glob '!tests/**'
	--glob '!**/*.min.js'
)
RG_COMMON=(--no-config --no-ignore --no-ignore-vcs --no-ignore-parent)

emit_hotspot_matches() {
	local class="$1"
	local pattern="$2"
	local rg_status

	rg -n --pcre2 "${RG_COMMON[@]}" "$pattern" "${EXCLUDE[@]}" --glob '*.php' . | while IFS= read -r line; do
		file="${line%%:*}"
		case "$file" in
			./*) ;;
			*) file="./$file" ;;
		esac
		match="${line#*:*:}"
		printf '%s\t%s\t%s\n' "$class" "$file" "$match"
	done
	rg_status="${PIPESTATUS[0]}"

	if [ "$rg_status" -gt 1 ]; then
		echo "ERROR: ripgrep failed while scanning $class hotspots." >&2
		return "$rg_status"
	fi

	return 0
}

hotspots() {
	printf 'class\tlocation\tmatch\n'

	# Raw command execution boundaries.
	emit_hotspot_matches 'cmd_exec' '(?<!->)(?<!::)\b(exec|system|shell_exec|passthru|popen|proc_open)\s*\(' || return $?

	# Dynamic include/require with variables.
	emit_hotspot_matches 'dynamic_include' '\b(include|include_once|require|require_once)\s*\(?\s*\$' || return $?

	# Host header trust boundaries.
	# shellcheck disable=SC2016 # literal PCRE pattern, $ is not a shell expansion
	emit_hotspot_matches 'host_header' '\$_SERVER\s*\[\s*["'"'"']HTTP_HOST["'"'"']\s*\]' || return $?

	# ORDER BY user-input boundary.
	# shellcheck disable=SC2016 # literal PCRE pattern, $ is not a shell expansion
	emit_hotspot_matches 'sort_order_input' 'ORDER\s+BY[^\n]*(get_request_var|get_nfilter_request_var|\$_(GET|POST|REQUEST))' || return $?
}

summary() {
	printf 'class\thelper\thelper_calls\thotspot_calls\n'

	helper_calls_cmd="$( (rg -n --pcre2 "${RG_COMMON[@]}" '\bcacti_exec\s*\(' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	hotspot_calls_cmd="$( (rg -n --pcre2 "${RG_COMMON[@]}" '(?<!->)(?<!::)\b(exec|system|shell_exec|passthru|popen|proc_open)\s*\(' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	printf 'cmd_exec\tcacti_exec\t%s\t%s\n' "$helper_calls_cmd" "$hotspot_calls_cmd"

	helper_calls_path="$( (rg -n --pcre2 "${RG_COMMON[@]}" '\b(cacti_plugin_path|validate_relative_path_within)\s*\(' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	hotspot_calls_path="$( (rg -n --pcre2 "${RG_COMMON[@]}" '\b(include|include_once|require|require_once)\s*\(?\s*\$' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	printf 'dynamic_include\tcacti_plugin_path|validate_relative_path_within\t%s\t%s\n' "$helper_calls_path" "$hotspot_calls_path"

	helper_calls_sort="$( (rg -n --pcre2 "${RG_COMMON[@]}" '\bcacti_validate_sort_column\s*\(' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	hotspot_calls_sort="$( (rg -n --pcre2 "${RG_COMMON[@]}" 'ORDER\s+BY[^\n]*(get_request_var|get_nfilter_request_var|\$_(GET|POST|REQUEST))' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	printf 'sort_order_input\tcacti_validate_sort_column\t%s\t%s\n' "$helper_calls_sort" "$hotspot_calls_sort"

	helper_calls_remote="$( (rg -n --pcre2 "${RG_COMMON[@]}" '\bcacti_build_remote_url\s*\(' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	hotspot_calls_remote="$( (rg -n --pcre2 "${RG_COMMON[@]}" '\$_SERVER\s*\[\s*["'"'"']HTTP_HOST["'"'"']\s*\]' "${EXCLUDE[@]}" --glob '*.php' . || true) | wc -l | tr -d ' ' )"
	printf 'host_header\tcacti_build_remote_url\t%s\t%s\n' "$helper_calls_remote" "$hotspot_calls_remote"
}

case "$MODE" in
--summary)
	summary
	;;
--hotspots)
	hotspots
	;;
*)
	echo "Usage: $0 [--summary|--hotspots]" >&2
	exit 1
	;;
esac

# Propagate rg errors surfaced by emit_hotspot_matches; a forced `exit 0`
# here would let the CI gate pass on a broken scan.
exit $?
