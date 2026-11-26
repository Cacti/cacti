# dispatch: lib/installer.php install/upgrades/1_3_0.php
run_probe tests/e2e/installer-coverage.sh '^(lib/installer\.php|cli/(install_cacti|upgrade_database)\.php|install/(functions|preflight|report|background|install)\.php|tests/e2e/installer)' 18277
run_php_probe tests/fixtures/Installer/RetiredThemeCleanup.php '^(install/(functions\.php|upgrades/1_3_0\.php)|tests/fixtures/Installer/RetiredThemeCleanup\.php)'
