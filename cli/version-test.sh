#!/bin/bash
for script in `ls *.php`; do
	echo Testing script: $script
	script_output=`php -q $script --version`
	script_result=$?
	script_lines=`echo "$script_output" | wc -l`

	if [[ $script_output == *"System log file is not available for writing"* ]]; then
		echo "Please run this test as the website user";
		exit;
	fi

	if [[ $script_result -ne 0 ]]; then
		echo "   x Failed version result test (" $script_result ")"
	fi

	if [[ $script_lines -ne 1 ]]; then
		echo "   x Failed version output test (" $script_lines ")";
	fi

	script_output=`php -q $script --help`
	script_result=$?
	script_lines=`echo "$script_output" | wc -l`

	if [[ $script_result -ne 0 ]]; then
		echo "   x Failed help result test (" $script_result ")"
	fi

	if [[ $script_lines -lt 3 ]]; then
		echo "   x Failed help output test (" $script_lines ")";
	fi
done
