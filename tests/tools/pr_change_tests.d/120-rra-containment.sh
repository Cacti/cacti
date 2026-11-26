if [[ -f tests/fixtures/Core/RraContainmentMatrix.php ]]; then
	run_probe RraContainmentMatrix.php rra-normal normal
	run_probe RraContainmentMatrix.php rra-missing missing
fi
