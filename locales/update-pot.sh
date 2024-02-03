#!/bin/sh
#   +-------------------------------------------------------------------------+
#   | Copyright (C) 2004-2024 The Cacti Group                                 |
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

# update translation files
echo "Updating Cacti language gettext language files"

cd ${BASE_PATH}
${XGETTEXT_BIN} -F -k__gettext -k__ -k__n:1,2 -k__x:1c,2 -k__xn:1c,2,3 -k__esc -k__esc_n:1,2 -k__esc_x:1c,2 -k__esc_xn:1c,2,3 -k__date -o locales/po/cacti.pot `find . -maxdepth 2 -name \*.php`
