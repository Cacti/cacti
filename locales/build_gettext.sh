#!/bin/sh
#+-------------------------------------------------------------------------+
#| Copyright (C) 2004-2026 The Cacti Group                                 |
#|                                                                         |
#| This program is free software; you can redistribute it and/or           |
#| modify it under the terms of the GNU General Public License             |
#| as published by the Free Software Foundation; either version 2          |
#| of the License, or (at your option) any later version.                  |
#|                                                                         |
#| This program is distributed in the hope that it will be useful,         |
#| but WITHOUT ANY WARRANTY; without even the implied warranty of          |
#| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
#| GNU General Public License for more details.                            |
#+-------------------------------------------------------------------------+
#| Cacti: The Complete RRDtool-based Graphing Solution                     |
#+-------------------------------------------------------------------------+
#| This code is designed, written, and maintained by the Cacti Group. See  |
#| about.php and/or the AUTHORS file for specific developer information.   |
#+-------------------------------------------------------------------------+
#| http://www.cacti.net/                                                   |
#+-------------------------------------------------------------------------+

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

# Update main gettext POT file with application strings.  update-pot.sh owns
# the extraction so that both scripts always produce the same template.
/bin/sh "$BASE_PATH/locales/update-pot.sh" || exit 1
cd "$BASE_PATH" || exit 1

# Merge any changes to POT file into language files
echo "Merging updates to language files..."

for file in locales/po/*.po; do
	[ -f "$file" ] || continue
	echo "Updating $file from cacti.pot"
	msgmerge --backup off --no-wrap --update -F "$file" locales/po/cacti.pot
done

for file in locales/po/*.po; do
	[ -f "$file" ] || continue
  ofile=$(basename "$file" .po)
  echo "Converting $file to LC_MESSAGES/${ofile}.mo"
  msgfmt "$file" -o "locales/LC_MESSAGES/$ofile.mo"
done

exit 0
