#!/bin/sh
# RRDtool test double for the direct argv and concurrent pipe contracts.

if [ -n "$FAKE_RRD_ARGV_FILE" ]; then
	: > "$FAKE_RRD_ARGV_FILE"
	for argument in "$@"; do
		printf '%s\n' "$argument" >> "$FAKE_RRD_ARGV_FILE"
	done
fi

while IFS= read -r command; do
	if [ -n "$FAKE_RRD_STDIN_FILE" ]; then
		printf '%s\n' "$command" >> "$FAKE_RRD_STDIN_FILE"
	fi

	if [ -n "$FAKE_RRD_STDERR_BYTES" ]; then
		head -c "$FAKE_RRD_STDERR_BYTES" /dev/zero | tr '\0' 'E' >&2
	fi

	if [ -n "$FAKE_RRD_STDOUT_BYTES" ]; then
		head -c "$FAKE_RRD_STDOUT_BYTES" /dev/zero | tr '\0' 'A'
	fi

	printf 'OK u:0.00 0.00\n'
done
