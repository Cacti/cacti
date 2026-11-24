# dispatch: color.php cli/add_device.php lib/api_tree.php
row_guard_files='^(aggregate_graphs|auth_changepassword|auth_profile|color|data_input|data_source_profiles|graph_templates)\.php|^cli/add_device\.php|^lib/(api_tree|auth|dsstats|html_reports|plugins|poller|reports|template)\.php|^tests/fixtures/Core/RowGuardMatrix\.php'
run_php_probe tests/fixtures/Core/RowGuardMatrix.php "$row_guard_files"
for row_guard_mode in auth-changepassword add-device reports-edit; do
	run_php_probe tests/fixtures/Core/RowGuardMatrix.php "$row_guard_files" "$row_guard_mode"
done
