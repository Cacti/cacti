#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
cd "$ROOT_DIR"

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

hotspots() {
	printf 'class\tlocation\tmatch\n'

	# Raw command execution boundaries.
	rg -n --pcre2 "${RG_COMMON[@]}" '(?<!->)(?<!::)\b(exec|system|shell_exec|passthru|popen|proc_open)\s*\(' "${EXCLUDE[@]}" --glob '*.php' . | while IFS= read -r line; do
		file="${line%%:*}"
		rest="${line#*:}"
		lineno="${rest%%:*}"
		case "$file" in
			./*) ;;
			*) file="./$file" ;;
		esac
		match="${line#*:*:}"
		printf 'cmd_exec\t%s:%s\t%s\n' "$file" "$lineno" "$match"
	done

	# Dynamic include/require with variables.
	rg -n --pcre2 "${RG_COMMON[@]}" '\b(include|include_once|require|require_once)\s*\(?\s*\$' "${EXCLUDE[@]}" --glob '*.php' . | while IFS= read -r line; do
		file="${line%%:*}"
		rest="${line#*:}"
		lineno="${rest%%:*}"
		case "$file" in
			./*) ;;
			*) file="./$file" ;;
		esac
		match="${line#*:*:}"
		printf 'dynamic_include\t%s:%s\t%s\n' "$file" "$lineno" "$match"
	done

	# Host header trust boundaries.
	rg -n --pcre2 "${RG_COMMON[@]}" '\$_SERVER\s*\[\s*["'"'"']HTTP_HOST["'"'"']\s*\]' "${EXCLUDE[@]}" --glob '*.php' . | while IFS= read -r line; do
		file="${line%%:*}"
		rest="${line#*:}"
		lineno="${rest%%:*}"
		case "$file" in
			./*) ;;
			*) file="./$file" ;;
		esac
		match="${line#*:*:}"
		printf 'host_header\t%s:%s\t%s\n' "$file" "$lineno" "$match"
	done

	# ORDER BY user-input boundary.
	rg -n --pcre2 "${RG_COMMON[@]}" 'ORDER\s+BY[^\n]*(get_request_var|get_nfilter_request_var|\$_(GET|POST|REQUEST))' "${EXCLUDE[@]}" --glob '*.php' . | while IFS= read -r line; do
		file="${line%%:*}"
		rest="${line#*:}"
		lineno="${rest%%:*}"
		case "$file" in
			./*) ;;
			*) file="./$file" ;;
		esac
		match="${line#*:*:}"
		printf 'sort_order_input\t%s:%s\t%s\n' "$file" "$lineno" "$match"
	done
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
