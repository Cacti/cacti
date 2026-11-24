# dispatch: cmd_realtime.php cli/rrdresize.php
command_files='^((cmd|cmd_realtime|remote_agent)\.php|cli/(audit_database|push_out_hosts|rrdresize|splice_rrd)\.php|lib/(dsstats|poller|rrdcheck|functions)\.php|tests/fixtures/Core/ConfiguredCommands\.php)'
run_php_probe tests/fixtures/Core/ConfiguredCommands.php "$command_files"
run_php_probe tests/fixtures/Core/ConfiguredCommands.php "$command_files" deprecated
