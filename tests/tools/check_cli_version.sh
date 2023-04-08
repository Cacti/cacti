#!/usr/bin/env bash
#+-------------------------------------------------------------------------+
#| Copyright (C) 2004-2023 The Cacti Group                                 |
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

SCRIPTPATH="$( cd "$(dirname "$0")" ; pwd -P )"
cd "${SCRIPTPATH}/../../"
FILES1=$(find cli -name \*.php | grep -v "index.php" | sort)
FILES2=$(ls -1 poller*.php | egrep -v "(index.php|pollers.php)" | sort)
FILES3="cactid.php cmd.php"
WEBUSER=$(ps -ef | egrep '(httpd|apache2|apache)' | grep -v `whoami` | grep -v root | head -n1 | awk '{print $1}')
PWD=$(pwd)

FAILED=0
HEADER="#!/usr/bin/env php"

echo "Current User is: $(whoami)"
echo "Web User is: ${WEBUSER}"
echo "Working Directory: ${PWD}"

for script in $FILES1 $FILES2 $FILES3; do
	if [[ $script == "index.php" ]]; then
		continue;
	fi

	echo "Testing script: ${script}"
	script_output=$(head -n 1 "${script}")
	if [[ "${script_output}" != "$HEADER" ]]; then
		FAILED=2
		echo "   x Failed header check (${script_output})"
		echo "       - should be '${HEADER}'"
		echo "       -     found '${script_output}'"
	fi

	script_output=$(php -q ${script} --version)
	script_result=$?
	script_lines=$(echo "${script_output}" | wc -l)

	if [[ "${script_output}" == *"System log file is not available for writing"* ]]; then
		FAILED=1
		echo "Please run this test as the website user";
		break;
	fi

	if [[ $script_result -ne 0 ]]; then
		FAILED=2
		echo "   x Failed version result test (${script_result})"
		echo "   ==============================================================================="
		echo "${script_output}"
		echo "   ==============================================================================="
	fi

	if [[ $script_lines -ne 1 ]]; then
		FAILED=3
		echo "   x Failed version output test (${script_lines})";
		echo "   ==============================================================================="
		echo "${script_output}"
		echo "   ==============================================================================="
	fi

	script_output=$(php -q ${script} --help)
	script_result=$?
	script_lines=$(echo "${script_output}" | wc -l)

	if [[ $script_result -ne 0 ]]; then
		FAILED=4
		echo "   x Failed help result test (${script_result})"
		echo "   ==============================================================================="
		echo "${script_output}"
		echo "   ==============================================================================="
	fi

	if [[ $script_lines -lt 3 ]]; then
		FAILED=5
		echo "   x Failed help output test (${script_lines})";
	fi
done
exit $FAILED
