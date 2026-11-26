# dispatch: data_input.php sites.php
validation_files='^((data_input|data_sources|pollers|sites)\.php|lib/(clog_webapi|functions|html|import|plugins)\.php|tests/fixtures/Core/DisplayAndValidation\.php)'
run_php_probe tests/fixtures/Core/DisplayAndValidation.php "$validation_files"
run_php_probe tests/fixtures/Core/DisplayAndValidation.php "$validation_files" save
