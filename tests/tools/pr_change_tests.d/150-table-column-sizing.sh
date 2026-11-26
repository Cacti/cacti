if [[ -f tests/fixtures/Core/TableColumnSizing.php ]]; then
	case_number=0
	for request in '{}' '{"action":"go"}' '{"action":"change","tab":"devices"}' '{"action":"reset","report":"summary"}' '{"action":"edit"}'; do
		for mode in table div; do
			for title in '' Title; do
				run_probe TableColumnSizing.php "table-$case_number" "$request" "$mode" "$title"
				case_number=$((case_number + 1))
			done
		done
	done
fi
add_browser_spec table-column-widths
