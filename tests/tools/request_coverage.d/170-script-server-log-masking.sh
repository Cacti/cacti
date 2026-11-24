# dispatch: script_server.php lib/poller.php
run_php_probe tests/fixtures/ScriptServerLogMaskingCoverage.php '^(cmd\.php|script_server\.php|lib/poller\.php|tests/fixtures/ScriptServerLogMaskingCoverage\.php)'
