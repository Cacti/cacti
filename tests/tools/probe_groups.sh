# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
# Sourced by the coverage runners; not executed on its own.

# A runner keeps each group of probes in its own file so that two changes add
# two files instead of editing one list. Groups are sourced in byte order of
# their names, which keeps the numbered groups first and in sequence. Every
# group is syntax-checked before the first one runs, so a broken group stops
# the run instead of leaving it half done.
source_probe_groups() {
	local probe_directory=$1 probe_group
	local probe_groups=()
	if [[ ! -d "$probe_directory" ]]; then
		printf 'Probe group directory %s is missing\n' "$probe_directory" >&2
		return 1
	fi
	while IFS= read -r probe_group; do
		if [[ ! -e "$probe_group" ]]; then
			printf 'Probe group directory %s is empty\n' "$probe_directory" >&2
			return 1
		fi
		if [[ ! -f "$probe_group" || ! "${probe_group##*/}" =~ ^[A-Za-z0-9][A-Za-z0-9_-]*\.sh$ ]]; then
			printf 'Probe group %s is not a regular file named [A-Za-z0-9_-]+.sh\n' "$probe_group" >&2
			return 1
		fi
		bash -n "$probe_group"
		probe_groups+=("$probe_group")
	done < <(LC_ALL=C; printf '%s\n' "$probe_directory"/*)
	for probe_group in "${probe_groups[@]}"; do
		# shellcheck source=/dev/null
		source "$probe_group"
	done
}
