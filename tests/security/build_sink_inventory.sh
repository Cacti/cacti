#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
cd "$ROOT_DIR"

if ! command -v rg >/dev/null 2>&1; then
	echo "ERROR: ripgrep (rg) is required." >&2
	exit 1
fi

EXCLUDE=(
	--glob '!include/vendor/**'
	--glob '!locales/**'
	--glob '!tests/**'
	--glob '!**/*.min.js'
)

printf 'category\tlocation\tmatch\n'

scan() {
	local category="$1"
	local pattern="$2"
	rg -n --pcre2 --no-config --no-ignore --no-ignore-vcs --no-ignore-parent "$pattern" "${EXCLUDE[@]}" --glob '*.php' . 2>/dev/null | grep -v '\/plugins\/' | while IFS= read -r line; do
		file="${line%%:*}"
		rest="${line#*:}"
		lineno="${rest%%:*}"
		case "$file" in
			./*) ;;
			*) file="./$file" ;;
		esac
		loc="${file}:${lineno}"
		match="${line#*:*:}"
		printf '%s\t%s\t%s\n' "$category" "$loc" "$match"
	done || true
}

# Command execution sinks
scan "cmd_exec" '(?<!->)(?<!::)\b(exec|system|shell_exec|passthru|popen|proc_open)\s*\('

# Dynamic include/require
scan "dynamic_include" '\b(include|include_once|require|require_once)\s*\(?\s*\$'

# Unsafe deserialization sink
scan "deserialize" '\bunserialize\s*\('

# XML parser entrypoints
scan "xml_parse" '\b(simplexml_load_file|simplexml_load_string|DOMDocument::loadXML|DOMDocument::load)\s*\('

# Redirect/header sinks
scan "header_redirect" '\bheader\s*\(\s*[\"\x27]Location:'

# Filesystem write sinks
scan "fs_write" '\b(file_put_contents|fopen)\s*\('
