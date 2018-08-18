#!/bin/bash
SCRIPTPATH="$( cd "$(dirname "$0")" ; pwd -P )"
cd $SCRIPTPATH/../../cli/

FAILED=0
for script in `ls *.php`; do
	if [[ $script == "index.php" ]]; then
		continue;
	fi

	echo Testing script: $script
	script_output=`head -n 1 $script`
	if [[ $script_output != "#!/usr/bin/php -q" ]]; then
		FAILED=2
		echo "   x Failed header check (" $script_output ")"
	fi

	script_output=`php -q $script --version`
	script_result=$?
	script_lines=`echo "$script_output" | wc -l`

	if [[ $script_output == *"System log file is not available for writing"* ]]; then
		FAILED=1
		echo "Please run this test as the website user";
		break;
	fi

	if [[ $script_result -ne 0 ]]; then
		FAILED=2
		echo "   x Failed version result test (" $script_result ")"
	fi

	if [[ $script_lines -ne 1 ]]; then
		FAILED=2
		echo "   x Failed version output test (" $script_lines ")";
	fi

	script_output=`php -q $script --help`
	script_result=$?
	script_lines=`echo "$script_output" | wc -l`

	if [[ $script_result -ne 0 ]]; then
		FAILED=2
		echo "   x Failed help result test (" $script_result ")"
	fi

	if [[ $script_lines -lt 3 ]]; then
		FAILED=2
		echo "   x Failed help output test (" $script_lines ")";
	fi
done
exit $FAILED
