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

# Regenerate the message template, locales/po/cacti.pot, from the source.
# The language catalogs (*.po, *.mo) are not touched.
#
# usage: update-pot.sh [output-file]
#
# Without an argument the committed template is rewritten in place.  With an
# argument the template is written there instead, which lets a check compare
# the result against the committed file without changing the checkout.

# resolve the output file before changing directory
case "${1:-}" in
	'') POT_FILE= ;;
	/*) POT_FILE=$1 ;;
	*) POT_FILE="$PWD/$1" ;;
esac

# locate base directory of Cacti
REALPATH_BIN=$(command -v realpath)
if [ $? -gt 0 ]
then
	echo "ERROR: unable to locate realpath" >&2
	echo >&2
	echo "Linux: Confirm coreutils installed" >&2
	echo "Mac: Brew install coreutils" >&2
	echo >&2
	exit 1
fi
BASE_PATH=$(dirname "$(dirname "$("$REALPATH_BIN" "$0")")")

# locate xgettext for processing
XGETTEXT_BIN=$(command -v xgettext)
if [ $? -gt 0 ]
then
	echo "ERROR: Unable to locate xgettext" >&2
	echo >&2
	echo "Linux: Install GNU gettext" >&2
	echo "Mac: Brew install GNU gettext" >&2
	echo >&2
	exit 1
fi

# update the template
echo "Updating Cacti language gettext language file..."

cd "$BASE_PATH" || exit 1
[ -n "$POT_FILE" ] || POT_FILE="$BASE_PATH/locales/po/cacti.pot"

# xgettext cannot rewrite its header placeholders, and BSD sed and GNU sed
# disagree on -i, so extract to a scratch file and filter it into place.
POT_TMP=$(mktemp -d "$(dirname "$POT_FILE")/cacti.pot.XXXXXX") || exit 1
trap 'rm -rf "$POT_TMP"' 0
# dash runs the EXIT trap on exit but not when a signal kills the shell, so
# turn INT and TERM into an exit to keep an interrupted run from leaving files.
trap 'exit 1' INT TERM

# Core source only: plugins carry their own templates, and the vendored
# libraries and the test fixtures are not part of the translated interface.
# Dot files and directories are skipped at any depth, so caches and tool
# directories such as lib/.cache are not read.
# The file list is sorted so the result does not depend on directory order.
# Locations name the file without a line number so that moving code does not
# rewrite the template.
find . \( -name '.?*' -o -path ./plugins -o -path ./include/vendor \
	-o -path ./tests -o -path ./node_modules \) -prune -o -name '*.php' -print | LC_ALL=C sort | "$XGETTEXT_BIN" --add-location=file --no-wrap --copyright-holder="The Cacti Group" --package-name="Cacti" --package-version="$(cat include/cacti_version)" --msgid-bugs-address="developers@cacti.net" -F -k__gettext -k__ -k__n:1,2 -k__x:1c,2 -k__xn:1c,2,3 -k__esc -k__esc_n:1,2 -k__esc_x:1c,2 -k__esc_xn:1c,2,3 -k__date -o "$POT_TMP/extracted.pot" --files-from=- || exit 1

# xgettext stamps the current time into POT-Creation-Date on every run.  Keep
# the date of the template being replaced, so that regenerating an unchanged
# tree leaves the file byte for byte the same and two pull requests that both
# change strings do not conflict on that line.
POT_DATE=
if [ -f "$POT_FILE" ]; then
	POT_DATE=$(sed -n 's/^"POT-Creation-Date: \(.*\)\\n"$/\1/p' "$POT_FILE" | head -n 1)
	printf '%s\n' "$POT_DATE" | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}[+-][0-9]{4}$' || POT_DATE=
fi

if ! sed -e 's/FULL NAME <EMAIL@ADDRESS>/Cacti Developers <developers@cacti.net>/g' \
	-e 's/LANGUAGE <LL@li.org>/Cacti Developers <developers@cacti.net>/g' \
	-e 's/CHARSET/UTF-8/g' "$POT_TMP/extracted.pot" > "$POT_TMP/filtered.pot"; then
	exit 1
fi
if [ -n "$POT_DATE" ]; then
	sed -e "s|^\"POT-Creation-Date: .*\\\\n\"\$|\"POT-Creation-Date: $POT_DATE\\\\n\"|" "$POT_TMP/filtered.pot" > "$POT_TMP/cacti.pot" || exit 1
else
	mv "$POT_TMP/filtered.pot" "$POT_TMP/cacti.pot" || exit 1
fi
chmod 644 "$POT_TMP/cacti.pot" || exit 1
mv "$POT_TMP/cacti.pot" "$POT_FILE" || exit 1
