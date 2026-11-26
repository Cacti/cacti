# dispatch: lib/api_device.php lib/reports.php link.php lib/plugins.php settings.php
run_php_probe tests/fixtures/Core/DeviceCollectorMessages.php '^(lib/api_device\.php|tests/fixtures/Core/DeviceCollectorMessages\.php)'
run_php_probe tests/fixtures/Core/ReportMessageDelivery.php '^(lib/(functions|reports)\.php|tests/fixtures/Core/ReportMessageDelivery\.php)'
run_php_probe tests/fixtures/Core/ExternalLinkMessage.php '^(link\.php|tests/fixtures/Core/ExternalLinkMessage\.php)$'
run_php_probe tests/fixtures/Core/TrustedActionMessages.php '^(aggregate_graphs\.php|lib/plugins\.php|tests/fixtures/Core/TrustedActionMessages\.php)$'
message_pages='automation_devices|automation_networks|color_templates|data_source_profiles|data_templates|graph_templates|graphs|graphs_new|host|host_templates|package|package_import|package_repos|plugins|pollers|settings|support'
message_libraries='api_automation|api_data_source|api_device|api_scheduler|clog_webapi|data_query|functions|html_reports|html_utility|package|plugins|reports|template|timespan_settings|utility'
run_php_probe tests/fixtures/Core/MessageFormatting.php "^((${message_pages}|lib/(${message_libraries}))\.php|tests/fixtures/Core/(MessageFormatting\.php|message-formatting-expected\.json))$"
