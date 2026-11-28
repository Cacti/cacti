#!/usr/bin/env bash
# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
set -euo pipefail

: "${BASE_COMMIT:?Coverage needs the pull request base commit}"
: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage needs an evidence directory}"
mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
command -v grep > /dev/null
merge_base="$(git merge-base "$BASE_COMMIT" HEAD)"
changed_paths="$(git diff --name-only "$merge_base" HEAD)"

run_probe() {
	local script="$1" pattern="$2" port="$3"
	if [[ -f "$script" ]] && grep -Eq "$pattern" <<< "$changed_paths"; then
		HOST_PORT="$port" bash "$script"
	fi
}

run_probe tests/e2e/aggregate-item-overrides.sh '^(aggregate_(graphs|templates)\.php|lib/api_aggregate\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-item-overrides)' 18273
CACTI_AGGREGATE_TEST_BROWSER=1 run_probe tests/e2e/aggregate-automation.sh '^(automation_aggregates\.php|lib/(api_automation|automation|api_aggregate)\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-automation)' 18274
run_probe tests/e2e/accessibility-coverage.sh '^((about|aggregate_graphs|auth_changepassword|auth_resetpassword|automation_templates|color|data_queries|data_templates|graph_realtime|graph_xport|graphs_new|host|host_templates|index|package|permission_denied|plugins|tree|user_admin|user_group_admin|utilities)\.php|include/top_(general_|graph_)?header\.php|install/install\.php|lib/html(_form|_graph)?\.php|tests/e2e/accessibility)' 18275
run_probe tests/e2e/queue/run.sh '^(lib/api_queue\.php|cli/queue_(admin|worker)\.php|include/global_settings\.php|install/upgrades/1_3_0\.php|tests/e2e/queue/)' 18276

run_php_probe() {
	local script="$1" pattern="$2"
	shift 2
	local php_args=(-d memory_limit=2G)
	if [[ -n "${CACTI_PR_XDEBUG_EXTENSION:-}" ]]; then
		php_args+=(-d "zend_extension=$CACTI_PR_XDEBUG_EXTENSION")
	fi
	if [[ -f "$script" ]] && grep -Eq "$pattern" <<< "$changed_paths"; then
		CACTI_PR_RAW_COVERAGE="$CACTI_PR_COVERAGE_OUTPUT" XDEBUG_MODE=coverage php "${php_args[@]}" "$script" "$@"
	fi
}

command_files='^((cmd|cmd_realtime|remote_agent)\.php|cli/(audit_database|push_out_hosts|rrdresize|splice_rrd)\.php|lib/(dsstats|poller|rrdcheck|functions)\.php|tests/fixtures/Core/ConfiguredCommands\.php)'
run_php_probe tests/fixtures/Core/ConfiguredCommands.php "$command_files"
run_php_probe tests/fixtures/Core/ConfiguredCommands.php "$command_files" deprecated
validation_files='^((data_input|data_sources|pollers|sites)\.php|lib/(clog_webapi|functions|html|import|plugins)\.php|tests/fixtures/Core/DisplayAndValidation\.php)'
run_php_probe tests/fixtures/Core/DisplayAndValidation.php "$validation_files"
run_php_probe tests/fixtures/Core/DisplayAndValidation.php "$validation_files" save

run_php_probe tests/fixtures/Core/Auth/BoundaryContracts.php '^(auth_changepassword\.php|include/global\.php|lib/auth\.php|package_import\.php|tests/fixtures/Core/Auth/BoundaryContracts\.php)'

run_php_probe tests/fixtures/Core/ScriptSubstitutionMatrix.php '^(lib/functions\.php|tests/fixtures/Core/ScriptSubstitutionMatrix\.php)'
run_php_probe tests/fixtures/Core/DeviceCollectorMessages.php '^(lib/api_device\.php|tests/fixtures/Core/DeviceCollectorMessages\.php)'
run_php_probe tests/fixtures/Core/ReportMessageDelivery.php '^(lib/(functions|reports)\.php|tests/fixtures/Core/ReportMessageDelivery\.php)'
run_php_probe tests/fixtures/Core/ExternalLinkMessage.php '^(link\.php|tests/fixtures/Core/ExternalLinkMessage\.php)$'
run_php_probe tests/fixtures/Core/TrustedActionMessages.php '^(aggregate_graphs\.php|lib/plugins\.php|tests/fixtures/Core/TrustedActionMessages\.php)$'
message_pages='automation_devices|automation_networks|color_templates|data_source_profiles|data_templates|graph_templates|graphs|graphs_new|host|host_templates|package|package_import|package_repos|plugins|pollers|settings|support'
message_libraries='api_automation|api_data_source|api_device|api_scheduler|clog_webapi|data_query|functions|html_reports|html_utility|package|plugins|reports|template|timespan_settings|utility'
run_php_probe tests/fixtures/Core/MessageFormatting.php "^((${message_pages}|lib/(${message_libraries}))\.php|tests/fixtures/Core/(MessageFormatting\.php|message-formatting-expected\.json))$"
run_php_probe tests/fixtures/Core/ConstantTimeGuardMatrix.php '^(auth_2fa\.php|auth_resetpassword\.php|tests/fixtures/Core/ConstantTimeGuardMatrix\.php)'
run_php_probe tests/fixtures/Core/PackageManifestMatrix.php '^(package_import\.php|package_repos\.php|lib/import\.php|tests/fixtures/Core/PackageManifestMatrix\.php)'
run_php_probe tests/fixtures/Core/PackageSaveMatrix.php '^(package_repos\.php|tests/fixtures/Core/PackageSaveMatrix\.php)'
run_php_probe tests/fixtures/Core/GraphDrilldownMarkup.php '^(lib/html\.php|tests/fixtures/Core/GraphDrilldownMarkup\.php)'
run_php_probe tests/fixtures/Core/RrdInfoHtmlEscape.php '^(lib/rrd\.php|tests/fixtures/Core/RrdInfoHtmlEscape\.php)'
run_php_probe tests/fixtures/Core/GraphItemsListMarkup.php '^(lib/html\.php|tests/fixtures/Core/GraphItemsListMarkup\.php)'
run_php_probe tests/fixtures/Core/SpikekillMutationMatrix.php '^(lib/spikekill\.php|tests/fixtures/Core/SpikekillMutationMatrix\.php)'
run_php_probe tests/fixtures/Core/RemoveSpikesHelp.php '^(cli/removespikes\.php|tests/fixtures/Core/RemoveSpikesHelp\.php)$'
run_php_probe tests/fixtures/Core/SpikekillRequestGate.php '^(spikekill\.php|tests/fixtures/Core/SpikekillRequestGate\.php)'

run_php_probe tests/fixtures/Core/Boost/FailedDataSourceIsolation.php '^(poller_boost\.php|tests/fixtures/Core/Boost/FailedDataSourceIsolation\.php)'

run_php_probe tests/fixtures/Core/Rrd/FallbackSourceIdentity.php '^(lib/rrd\.php|tests/fixtures/Core/Rrd/FallbackSourceIdentity\.php)'

run_php_probe tests/fixtures/SnmpAgentGlobalSettingsCoverage.php '^(lib/snmpagent\.php|tests/fixtures/SnmpAgentGlobalSettingsCoverage\.php)'

run_php_probe tests/fixtures/CmdBoostFlushRedirectCoverage.php '^(cmd\.php|tests/fixtures/CmdBoostFlushRedirectCoverage\.php)'

run_php_probe tests/fixtures/ScriptServerLogMaskingCoverage.php '^(cmd\.php|script_server\.php|lib/poller\.php|tests/fixtures/ScriptServerLogMaskingCoverage\.php)'

run_php_probe tests/fixtures/BoostBulkDrainCoverage.php '^(poller_boost\.php|lib/boost\.php|tests/fixtures/(Boost(BulkDrain(Coverage|FailureProbe)|OnDemandFailureProbe|SourceExistenceProbe)|ProbeLoader)\.php)'

run_php_probe tests/fixtures/UserAdministrationAuditCoverage.php '^(user_admin\.php|lib/functions\.php|tests/fixtures/UserAdministrationAudit)'

run_php_probe tests/fixtures/Core/GraphViewCrossFilterCoverage.php '^(lib/html\.php|lib/html_graph\.php|tests/fixtures/Core/GraphViewCrossFilterCoverage\.php)'

run_php_probe tests/fixtures/Core/HtmlGraphMarkup.php '^(lib/html_graph\.php|tests/fixtures/Core/HtmlGraphMarkup\.php)'

run_php_probe tests/fixtures/Core/TreeFilterDeviceTemplateCoverage.php '^(lib/html_tree\.php|tests/fixtures/Core/TreeFilterDeviceTemplateCoverage\.php)'

run_php_probe tests/Unit/fixtures/aggregate_label_render_probe.php '^(lib/(api_aggregate|html)\.php|tests/Unit/fixtures/aggregate_label_render_probe\.php)'
run_php_probe tests/Unit/fixtures/aggregate_label_render_probe.php '^(lib/(api_aggregate|html)\.php|tests/Unit/fixtures/aggregate_label_render_probe\.php)' graph '<em>Literal label</em>'

run_php_probe tests/fixtures/Core/DataSourceRrdRepairCoverage.php '^(data_sources\.php|tests/fixtures/Core/DataSourceRrdRepairCoverage\.php)'

run_php_probe tests/fixtures/Core/PackageArchive.php '^(lib/package\.php|tests/fixtures/Core/PackageArchive\.php)'

run_php_probe tests/fixtures/Core/PackageRepositoryWorkspace.php '^((package_import|lib/package)\.php|tests/fixtures/Core/PackageRepositoryWorkspace\.php)'
run_php_probe tests/fixtures/Core/PackageImportFileStatus.php '^(lib/(import|package)\.php|tests/fixtures/Core/PackageImportFileStatus\.php)'
run_php_probe tests/fixtures/Core/PackageDiffBoundary.php '^(package_import\.php|tests/fixtures/Core/PackageDiffBoundary\.php)'

row_guard_files='^(aggregate_graphs|auth_changepassword|auth_profile|color|data_input|data_source_profiles|graph_templates)\.php|^cli/add_device\.php|^lib/(api_tree|auth|dsstats|html_reports|plugins|poller|reports|template)\.php|^tests/fixtures/Core/RowGuardMatrix\.php'
run_php_probe tests/fixtures/Core/RowGuardMatrix.php "$row_guard_files"
for row_guard_mode in auth-changepassword add-device reports-edit; do
	run_php_probe tests/fixtures/Core/RowGuardMatrix.php "$row_guard_files" "$row_guard_mode"
done

run_probe tests/e2e/installer-coverage.sh '^(lib/installer\.php|cli/(install_cacti|upgrade_database)\.php|install/(functions|preflight|report|background|install)\.php|tests/e2e/installer)' 18277

run_php_probe tests/fixtures/Installer/RetiredThemeCleanup.php '^(install/(functions\.php|upgrades/1_3_0\.php)|tests/fixtures/Installer/RetiredThemeCleanup\.php)'

run_php_probe tests/Unit/fixtures/graph_group_probe.php '^(lib/functions\.php|tests/Unit/fixtures/graph_group_probe\.php)'

run_probe tests/e2e/basic_auth_identity_docker.sh '^(include/(auth|global)\.php|lib/auth\.php|tests/e2e/basic_auth_identity_docker\.sh)' 18392

run_php_probe tests/Unit/fixtures/document_direction_probe.php '^(include/global_languages\.php|include/top_general_header\.php|lib/auth\.php|tests/Unit/fixtures/(document_direction_probe|i18n_probe_bootstrap)\.php)'

user_settings_realtime_files='^(auth_profile\.php|graph_realtime\.php|lib/(functions|html_tree|user_setting_validation)\.php|tests/fixtures/Core/UserSettingsAndRealtimeHardening\.php)$'
for mode in core auth-guest-exit auth-disable-2fa auth-enable-2fa auth-verify-2fa realtime-denied realtime-view-denied; do
	run_php_probe tests/fixtures/Core/UserSettingsAndRealtimeHardening.php "$user_settings_realtime_files" "$mode"
done
