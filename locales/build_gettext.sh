#!/bin/sh
#+-------------------------------------------------------------------------+
#| Copyright (C) 2004-2022 The Cacti Group                                 |
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

# get script name
SCRIPT_NAME=`basename ${0}`

# locate base directory of Cacti
REALPATH_BIN=`which realpath 2>/dev/null`
if [ $? -gt 0 ]
then
	echo "ERROR: unable to locate realpath"
	echo
	echo "Linux: Confirm coreutils installed"
	echo "Mac: Brew install coreutils"
	echo
	exit 1
fi
BASE_PATH=`${REALPATH_BIN} ${0} | sed s#/locales/${SCRIPT_NAME}##`

# locate xgettext for processing
XGETTEXT_BIN=`which xgettext 2>/dev/null`
if [ $? -gt 0 ]
then
	echo "ERROR: Unable to locate xgettext"
	echo
	echo "Linux: Install GNU gettext"
	echo "Mac: Brew install GNU gettext"
	echo
	exit 1
fi

# Update main gettext POT file with application strings
echo "Updating Cacti language gettext language file..."
cd ${BASE_PATH}

${XGETTEXT_BIN} --no-wrap --copyright-holder="The Cacti Group" --package-name="Cacti" --package-version=`cat include/cacti_version` --msgid-bugs-address="developers@cacti.net" -F -k__gettext -k__ -k__n:1,2 -k__x:1c,2 -k__xn:1c,2,3 -k__esc -k__esc_n:1,2 -k__esc_x:1c,2 -k__esc_xn:1c,2,3 -k__date -o locales/po/cacti.pot `find . -maxdepth 2 -name \*.php`

# Merge any changes to POT file into language files
echo "Merging updates to language files..."

for file in `ls -1 locales/po/*.po`;do
	echo "Updating $file from cacti.pot"
	msgmerge --backup off --no-wrap --update -F $file locales/po/cacti.pot
done

for file in `ls -1 locales/po/*.po`;do
  ofile=$(basename --suffix=.po ${file})
  echo "Converting $file to LC_MESSAGES/${ofile}.mo"
  msgfmt ${file} -o locales/LC_MESSAGES/${ofile}.mo
done

exit 0
