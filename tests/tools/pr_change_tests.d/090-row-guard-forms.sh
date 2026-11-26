if [[ -f tests/fixtures/Core/RowGuardMatrix.php ]]; then
	for mode in auth-changepassword add-device reports-edit; do
		run_probe RowGuardMatrix.php "row-guard-$mode" "$mode"
	done
fi
