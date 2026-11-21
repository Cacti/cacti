#!/bin/sh
#   +-------------------------------------------------------------------------+
#   | Copyright (C) 2004-2026 The Cacti Group                                 |
#   |                                                                         |
#   | This program is free software; you can redistribute it and/or           |
#   | modify it under the terms of the GNU General Public License             |
#   | as published by the Free Software Foundation; either version 2          |
#   | of the License, or (at your option) any later version.                  |
#   |                                                                         |
#   | This program is distributed in the hope that it will be useful,         |
#   | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
#   | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
#   | GNU General Public License for more details.                            |
#   +-------------------------------------------------------------------------+
#   | Cacti: The Complete RRDTool-based Graphing Solution                     |
#   +-------------------------------------------------------------------------+
#   | This code is designed, written, and maintained by the Cacti Group. See  |
#   | about.php and/or the AUTHORS file for specific developer information.   |
#   +-------------------------------------------------------------------------+
#   | http://www.cacti.net/                                                   |
#   +-------------------------------------------------------------------------+

# Check the message template and the language catalogs.
#
# 1. locales/po/cacti.pot must hold the messages that locales/update-pot.sh
#    extracts from the source.  When it does not, run
#
#        sh locales/update-pot.sh
#
#    and commit locales/po/cacti.pot.
# 2. The template and every locales/po/*.po must pass msgfmt --check-format.
#    Catalogs that already fail are listed with their error count in
#    message_catalog_baseline.txt; a catalog fails the check only when it has
#    more errors than its baseline allows.
#
# The checkout is not modified.

ROOT=$(cd "$(dirname "$0")/../.." && pwd) || exit 1
BASELINE="$ROOT/tests/tools/message_catalog_baseline.txt"

for tool in xgettext msgcat msgfmt; do
	if ! command -v "$tool" > /dev/null; then
		echo "ERROR: unable to locate $tool; install GNU gettext" >&2
		exit 2
	fi
done

WORK=$(mktemp -d "${TMPDIR:-/tmp}/cacti-template.XXXXXX") || exit 2
trap 'rm -rf "$WORK"' 0
trap 'exit 2' INT TERM

cd "$ROOT" || exit 2
STATUS=0

# Reduce a template to its sorted messages: context, id and plural.  The
# header carries the creation date, locations move with the code, and the
# format flag is a guess that differs between gettext releases, so none of
# them takes part in the comparison.
messages() {
	LC_ALL=C msgcat --no-location --no-wrap --sort-output "$1" > "$WORK/sorted.pot" || return 1
	grep -v -e '^#' -e '^msgstr' "$WORK/sorted.pot" | sed '1,/^$/d'
}

if ! sh locales/update-pot.sh "$WORK/cacti.pot" > "$WORK/update.log" 2>&1; then
	cat "$WORK/update.log" >&2
	echo "ERROR: locales/update-pot.sh failed" >&2
	exit 2
fi

if ! messages locales/po/cacti.pot > "$WORK/committed.txt" || ! messages "$WORK/cacti.pot" > "$WORK/extracted.txt"; then
	echo "ERROR: unable to read the message template" >&2
	exit 2
fi

if diff -u "$WORK/committed.txt" "$WORK/extracted.txt" > "$WORK/template.diff"; then
	echo "OK: locales/po/cacti.pot matches the source"
else
	# Skip the two file name lines, which only name scratch files.
	sed '1,2d' "$WORK/template.diff"
	echo
	echo "FAIL: locales/po/cacti.pot does not match the source."
	echo "Lines starting with '-' are in the template but no longer in the source;"
	echo "lines starting with '+' are in the source but missing from the template."
	echo "Run 'sh locales/update-pot.sh' and commit locales/po/cacti.pot."
	STATUS=1
fi

# Print the number of msgfmt errors a catalog is allowed to have.
allowed() {
	[ -f "$BASELINE" ] || { echo 0; return; }
	awk -v file="$1" '$1 == file { count = $2 } END { print count + 0 }' "$BASELINE"
}

for file in locales/po/cacti.pot locales/po/*.po; do
	[ -f "$file" ] || continue
	if LC_ALL=C msgfmt --check-format -o /dev/null "$file" > "$WORK/msgfmt.log" 2>&1; then
		found=0
	else
		found=$(sed -n 's/^msgfmt: found \([0-9][0-9]*\) fatal errors\{0,1\}$/\1/p' "$WORK/msgfmt.log")
	fi
	limit=$(allowed "$file")

	# A failure without a count is a catalog msgfmt could not read at all.
	if [ -z "$found" ] || [ "$found" -gt "$limit" ]; then
		cat "$WORK/msgfmt.log"
		echo "FAIL: $file does not pass msgfmt --check-format (${found:-unreadable}, baseline allows $limit errors)"
		STATUS=1
	elif [ "$found" -gt 0 ]; then
		echo "KNOWN: $file has $found msgfmt --check-format errors recorded in the baseline"
	elif [ "$limit" -gt 0 ]; then
		echo "NOTE: $file now passes; remove it from tests/tools/message_catalog_baseline.txt"
	fi
done

[ "$STATUS" -eq 0 ] && echo "OK: the template and the language catalogs pass msgfmt --check-format"
exit "$STATUS"
