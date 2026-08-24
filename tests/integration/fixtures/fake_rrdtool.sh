#!/bin/sh
# Test double for rrdtool used by the argv-contract tests. It records the argv
# it was invoked with and the command it received on stdin, then echoes a fixed
# marker. It never interprets the payload, so if the caller used a shell the
# injected command would run before this script ever sees it.
#
# FAKE_RRD_STDOUT_BYTES=N   emit N bytes on stdout before the marker
# FAKE_RRD_STDERR_BYTES=N   emit N bytes on stderr (large stderr for drain tests)
# FAKE_RRD_STDERR=text      emit one line on stderr
if [ -n "$FAKE_RRD_ARGV_FILE" ]; then
	: > "$FAKE_RRD_ARGV_FILE"
	for a in "$@"; do printf '%s\n' "$a" >> "$FAKE_RRD_ARGV_FILE"; done
fi
if [ -n "$FAKE_RRD_STDIN_FILE" ]; then
	cat > "$FAKE_RRD_STDIN_FILE"
else
	cat > /dev/null
fi
if [ -n "$FAKE_RRD_STDERR_BYTES" ]; then
	head -c "$FAKE_RRD_STDERR_BYTES" /dev/zero | tr '\0' 'E' >&2
fi
if [ -n "$FAKE_RRD_STDOUT_BYTES" ]; then
	head -c "$FAKE_RRD_STDOUT_BYTES" /dev/zero | tr '\0' 'A'
fi
printf 'FAKERRD-OK\n'
[ -n "$FAKE_RRD_STDERR" ] && printf '%s\n' "$FAKE_RRD_STDERR" >&2
exit 0
