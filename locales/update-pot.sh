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

# update translation files
echo "Updating Cacti language gettext language files"

cd "$BASE_PATH" || exit 1
find . -maxdepth 2 -name '*.php' -print | "$XGETTEXT_BIN" -F -k__gettext -k__ -k__n:1,2 -k__x:1c,2 -k__xn:1c,2,3 -k__esc -k__esc_n:1,2 -k__esc_x:1c,2 -k__esc_xn:1c,2,3 -k__date -o locales/po/cacti.pot --files-from=-
