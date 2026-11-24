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
# usage: check_message_template.sh [--list-catalog-failures] [base-revision]
#
# 1. locales/po/cacti.pot must hold the messages that locales/update-pot.sh
#    extracts from the source.  Only what the change under test introduces is
#    held against it: the same comparison is made at a base revision and the
#    messages that were already out of sync there are reported as a notice, not
#    a failure.  A change fails when it adds or removes a message in the source
#    without updating the template, or edits the template without a matching
#    source change.  To fix it run
#
#        sh locales/update-pot.sh
#
#    and commit locales/po/cacti.pot.
#
#    The base revision is, in order: the argument, $CACTI_I18N_BASE, or the
#    merge base of HEAD with upstream/develop (then origin/develop).  The
#    workflow passes the base of the pull request, or the previous head of
#    develop for a push.  When no base can be resolved the whole tree is
#    compared and the output says so.
# 2. The template and every locales/po/*.po must pass msgfmt --check-format.
#    Entries that already fail are listed in message_catalog_baseline.txt.  An
#    entry that fails and is not in the baseline fails the check.  A baseline
#    entry that no longer fails only produces a notice, so that an unrelated
#    change is not blocked by a catalog fix that Weblate delivered; remove the
#    line when you see the notice.  --list-catalog-failures prints the current
#    failing entries in the baseline format and exits.
#
# The checkout is not modified; scratch files live in a temporary directory.

# comm and sort must agree on the collation order.
LC_ALL=C
export LC_ALL

ROOT=$(cd "$(dirname "$0")/../.." && pwd) || exit 1
BASELINE="$ROOT/tests/tools/message_catalog_baseline.txt"
BASE_ARG=
LIST_ONLY=0

while [ $# -gt 0 ]; do
	case $1 in
		--list-catalog-failures) LIST_ONLY=1 ;;
		-h|--help) sed -n '/^# usage:/,/^# The checkout is not modified/p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		-*) echo "ERROR: unknown option $1" >&2; exit 2 ;;
		*) BASE_ARG=$1 ;;
	esac
	shift
done

for tool in xgettext msgcat msgfmt awk; do
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
TAB=$(printf '\t')

# Print a template as one line per message, sorted: context, id and plural.
# The header carries the creation date, locations move with the code, and the
# format flag is a guess that differs between gettext releases, so none of
# them takes part in the comparison.  A missing file has no messages.
messages() {
	[ -f "$1" ] || return 0
	msgcat --no-location --no-wrap "$1" > "$WORK/normalized.pot" || return 1
	awk '
	function strip(text) { sub(/^"/, "", text); sub(/"$/, "", text); return text }
	function emit() {
		if (inentry && (hasctxt || id != "")) {
			line = (hasctxt ? "msgctxt \"" ctxt "\" " : "") "msgid \"" id "\""
			if (hasplural) line = line " msgid_plural \"" plural "\""
			print line
		}
		inentry = hasctxt = hasplural = 0
		ctxt = id = plural = field = ""
	}
	/^#/ { next }
	/^$/ { emit(); next }
	/^msgctxt / { if (field == "str") emit(); inentry = 1; hasctxt = 1; field = "ctxt"; ctxt = strip(substr($0, 9)); next }
	/^msgid / { if (field == "str") emit(); inentry = 1; field = "id"; id = strip(substr($0, 7)); next }
	/^msgid_plural / { hasplural = 1; field = "plural"; plural = strip(substr($0, 14)); next }
	/^msgstr/ { field = "str"; next }
	/^"/ {
		if (field == "ctxt") ctxt = ctxt strip($0)
		else if (field == "id") id = id strip($0)
		else if (field == "plural") plural = plural strip($0)
		next
	}
	END { emit() }
	' "$WORK/normalized.pot" | sort -u
}

# Compare the template committed under $1 with what $1/locales/update-pot.sh
# extracts from the source there.  Write the messages the template lacks to
# $2.add and the messages it holds that the source no longer has to $2.del.
drift() {
	sh "$1/locales/update-pot.sh" "$2.pot" > "$2.log" 2>&1 || return 1
	messages "$1/locales/po/cacti.pot" > "$2.template" || return 1
	messages "$2.pot" > "$2.source" || return 1
	comm -13 "$2.template" "$2.source" > "$2.add"
	comm -23 "$2.template" "$2.source" > "$2.del"
}

# Set BASE_REV to the commit the change under test starts from, or leave it
# empty and set BASE_WHY to the reason none was found.
resolve_base() {
	BASE_REV=
	candidate=${BASE_ARG:-${CACTI_I18N_BASE:-}}
	if [ -n "$candidate" ]; then
		BASE_WHY="base '$candidate' is not available in this checkout"
	else
		BASE_WHY='no base given and neither upstream/develop nor origin/develop exists'
	fi
	if ! command -v git > /dev/null || ! git rev-parse --git-dir > /dev/null 2>&1; then
		BASE_WHY='this is not a git checkout'
		return 0
	fi
	case $candidate in -*) return 0 ;; esac
	if [ -n "$candidate" ]; then
		commit=$(git rev-parse --verify --quiet "$candidate^{commit}") || return 0
	else
		for ref in upstream/develop origin/develop; do
			commit=$(git rev-parse --verify --quiet "$ref^{commit}") && break
			commit=
		done
		[ -n "$commit" ] || return 0
	fi
	# The base of a change is where it left the target branch, not the tip the
	# branch has moved on to.  A shallow clone cannot tell, so use the commit.
	BASE_REV=$(git merge-base HEAD "$commit" 2> /dev/null) || BASE_REV=$commit
}

# Print the failing entries of the catalogs as "catalog<TAB>entry", sorted.
# An error is named by the message it is in, cut to 100 characters, so that
# lines moving in a catalog do not change the entry.  An error that cannot be
# placed in a message is named by its text without the position.
catalog_failures() {
	for file in locales/po/cacti.pot locales/po/*.po; do
		[ -f "$file" ] || continue
		if msgfmt --check-format -o /dev/null "$file" > "$WORK/msgfmt.log" 2>&1; then
			continue
		fi
		awk -v file="$file" -v tab="$TAB" '
		function strip(text) { sub(/^"/, "", text); sub(/"$/, "", text); return text }
		function close_entry() {
			if (open) { n++; first[n] = start; last[n] = end; key[n] = (hasctxt ? "msgctxt " ctxt " " : "") "msgid " id }
			open = hasctxt = 0; ctxt = id = field = ""
		}
		FILENAME == ARGV[1] {
			prefix = file ":"
			if (index($0, prefix) == 1) {
				rest = substr($0, length(prefix) + 1)
				if (match(rest, /^[0-9]+/)) {
					line = substr(rest, 1, RLENGTH) + 0
					text = substr(rest, RLENGTH + 1)
					sub(/^:[0-9]+/, "", text)
					sub(/^: */, "", text)
					wanted[++w] = line
					reason[w] = text
				}
			}
			next
		}
		/^#/ { next }
		/^$/ { next }
		/^msgctxt / { if (field == "str") close_entry(); if (!open) { open = 1; start = FNR } hasctxt = 1; field = "ctxt"; ctxt = strip(substr($0, 9)); end = FNR; next }
		/^msgid / { if (field == "str") close_entry(); if (!open) { open = 1; start = FNR } field = "id"; id = strip(substr($0, 7)); end = FNR; next }
		/^msgid_plural / { field = "plural"; end = FNR; next }
		/^msgstr/ { field = "str"; end = FNR; next }
		/^"/ {
			if (field == "ctxt") ctxt = ctxt strip($0)
			else if (field == "id") id = id strip($0)
			end = FNR
			next
		}
		END {
			close_entry()
			for (i = 1; i <= w; i++) {
				name = ""
				for (j = 1; j <= n; j++) if (wanted[i] >= first[j] && wanted[i] <= last[j]) name = key[j]
				if (name == "") name = reason[i]
				if (length(name) > 100) name = substr(name, 1, 100) "..."
				print file tab name
			}
		}
		' "$WORK/msgfmt.log" "$file"
		# A failure that named no position still has to count.
		if ! grep -q "^$file:[0-9]" "$WORK/msgfmt.log"; then
			printf '%s\t%s\n' "$file" "msgfmt failed: $(grep -v '^msgfmt: found' "$WORK/msgfmt.log" | head -n 1)"
		fi
	done | sort -u
}

if [ "$LIST_ONLY" -eq 1 ]; then
	catalog_failures
	exit 0
fi

# 1. The template against the source.
if ! drift "$ROOT" "$WORK/head"; then
	cat "$WORK/head.log" >&2
	echo "ERROR: locales/update-pot.sh failed" >&2
	exit 2
fi

resolve_base
: > "$WORK/base.add"
: > "$WORK/base.del"
if [ -n "$BASE_REV" ]; then
	# The base is read from git objects, not checked out, so the checkout
	# and its index stay as they are.  The working tree's update-pot.sh is
	# copied in so that both sides are extracted by the same rules.
	mkdir -p "$WORK/base-tree/locales/po"
	if git archive "$BASE_REV" -- '*.php' include/cacti_version 2> /dev/null | tar -x -C "$WORK/base-tree" 2> /dev/null \
		&& cp locales/update-pot.sh "$WORK/base-tree/locales/update-pot.sh" \
		&& { git show "$BASE_REV:locales/po/cacti.pot" > "$WORK/base-tree/locales/po/cacti.pot" 2> /dev/null || rm -f "$WORK/base-tree/locales/po/cacti.pot"; } \
		&& drift "$WORK/base-tree" "$WORK/base"; then
		echo "Comparing with the base $(git rev-parse --short "$BASE_REV")"
	else
		BASE_WHY="the source at $BASE_REV could not be read"
		BASE_REV=
		: > "$WORK/base.add"
		: > "$WORK/base.del"
	fi
fi
[ -n "$BASE_REV" ] || echo "NOTE: $BASE_WHY; comparing the whole tree, so messages that were already out of sync count against this change."

comm -23 "$WORK/head.add" "$WORK/base.add" > "$WORK/new.add"
comm -23 "$WORK/head.del" "$WORK/base.del" > "$WORK/new.del"
comm -12 "$WORK/head.add" "$WORK/base.add" > "$WORK/old.add"
comm -12 "$WORK/head.del" "$WORK/base.del" > "$WORK/old.del"
carried=$(($(wc -l < "$WORK/old.add") + $(wc -l < "$WORK/old.del")))

if [ "$carried" -gt 0 ]; then
	echo "NOTE: $carried message(s) were already out of sync with locales/po/cacti.pot at the base and still are; they are not held against this change. 'sh locales/update-pot.sh' brings the template up to date."
fi

if [ -s "$WORK/new.add" ] || [ -s "$WORK/new.del" ]; then
	sed 's/^/+/' "$WORK/new.add"
	sed 's/^/-/' "$WORK/new.del"
	echo
	echo "FAIL: locales/po/cacti.pot does not match the source for the messages above."
	echo "Lines starting with '+' are in the source but missing from the template;"
	echo "lines starting with '-' are in the template but no longer in the source."
	echo "Run 'sh locales/update-pot.sh' and commit locales/po/cacti.pot."
	STATUS=1
else
	echo "OK: locales/po/cacti.pot matches the source"
fi

# 2. The template and the catalogs against msgfmt --check-format.
catalog_failures > "$WORK/failures.txt"
grep -v -e '^#' -e '^$' "$BASELINE" 2> /dev/null | sort -u > "$WORK/baseline.txt"
comm -23 "$WORK/failures.txt" "$WORK/baseline.txt" > "$WORK/failures.new"
comm -13 "$WORK/failures.txt" "$WORK/baseline.txt" > "$WORK/failures.fixed"
known=$(($(comm -12 "$WORK/failures.txt" "$WORK/baseline.txt" | wc -l)))

if [ -s "$WORK/failures.new" ]; then
	while IFS="$TAB" read -r catalog entry; do
		echo "FAIL: $catalog does not pass msgfmt --check-format: $entry"
	done < "$WORK/failures.new"
	echo "Fix the translation, or for a catalog that Weblate owns, report it there."
	STATUS=1
fi
[ "$known" -eq 0 ] || echo "KNOWN: $known msgfmt --check-format failure(s) are recorded in tests/tools/message_catalog_baseline.txt"
if [ -s "$WORK/failures.fixed" ]; then
	while IFS="$TAB" read -r catalog entry; do
		echo "NOTE: $catalog no longer fails for: $entry"
	done < "$WORK/failures.fixed"
	echo "Remove those lines from tests/tools/message_catalog_baseline.txt."
fi

[ "$STATUS" -eq 0 ] && echo "OK: no new msgfmt --check-format failures in the template or the language catalogs"
exit "$STATUS"
