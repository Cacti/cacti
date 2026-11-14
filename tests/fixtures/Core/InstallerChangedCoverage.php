<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace CactiInstallerChangedFixture;

use LogicException;
use RuntimeException;
use Throwable;

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$autoload = dirname(__DIR__, 3) . '/include/vendor/autoload.php';

if (is_file($autoload)) {
	require_once $autoload;
}

$root = dirname(__DIR__, 3);

$pluginRoot = sys_get_temp_dir() . '/cacti-installer-plugins-' . getmypid();

foreach (['compatible' => '1.2.0', 'incompatible' => '9.0.0'] as $plugin => $compat) {
	if (!is_dir($pluginRoot . '/' . $plugin)) {
		mkdir($pluginRoot . '/' . $plugin, 0700, true);
	}
	copy($root . '/tests/fixtures/InstallerPlugin/setup.php', $pluginRoot . '/' . $plugin . '/setup.php');
	file_put_contents($pluginRoot . '/' . $plugin . '/INFO', "[info]\nname = " . $plugin . "\ncompat = " . $compat . "\n");
}
register_shutdown_function(static function () use ($pluginRoot) : void {
	foreach (['compatible', 'incompatible'] as $plugin) {
		unlink($pluginRoot . '/' . $plugin . '/setup.php');
		unlink($pluginRoot . '/' . $plugin . '/INFO');
		rmdir($pluginRoot . '/' . $plugin);
	}
	rmdir($pluginRoot);
});

foreach ([
	'CACTI_PATH_BASE'            => $root,
	'CACTI_PATH_LIBRARY'         => $root . '/lib',
	'CACTI_PATH_INSTALL'         => $root . '/install',
	'CACTI_PATH_PLUGINS'         => $pluginRoot,
	'CACTI_VERSION'              => '1.3.0',
	'CACTI_VERSION_FULL'         => '1.3.0',
	'POLLER_ID'                  => 1,
	'DB_STATUS_ERROR'            => 0,
	'DB_STATUS_WARNING'          => 1,
	'DB_STATUS_SUCCESS'          => 3,
	'DB_STATUS_SKIPPED'          => 4,
	'CACTI_VERSION_FORMAT_SHORT' => 0,
	'CACTI_VERSION_FORMAT_FULL'  => 1,
] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

foreach ([
	'POLLER_STATUS_NEW'        => 0,
	'POLLER_STATUS_RUNNING'    => 1,
	'POLLER_STATUS_IDLE'       => 2,
	'POLLER_STATUS_DOWN'       => 3,
	'POLLER_STATUS_DISABLED'   => 4,
	'POLLER_STATUS_RECOVERING' => 5,
	'POLLER_STATUS_HEARTBEAT'  => 6,
] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

$GLOBALS['cacti_db'] = [
	'poller'   => ['id' => 2, 'name' => 'remote', 'dbhost' => '10.0.0.2', 'dbuser' => 'u', 'dbpass' => 'p', 'dbdefault' => 'cacti', 'dbport' => 3306, 'dbretries' => 1, 'dbssl' => '', 'dbsslverifyservercert' => ''],
	'connect'  => [],
	'execute'  => true,
	'settings' => ['poller_interval' => 30, 'disable_full_sync_on_upgrade' => ''],
];

function __($text, ...$args) {
	return $args !== [] ? vsprintf($text, $args) : $text;
}
function db_fetch_assoc($sql, ...$args) {
	foreach ($GLOBALS['cacti_db']['fail'] ?? [] as $failing) {
		if (str_contains($sql, $failing)) {
			return false;
		}
	}

	if (str_contains($sql, 'information_schema')) {
		return [['TABLE_NAME' => 'one', 'COLUMN_NAME' => 'id']];
	}

	if (str_contains($sql, 'user_auth')) {
		return [['realm' => 0]];
	}

	if (str_contains($sql, 'login_providers')) {
		return [['id' => 1, 'type' => 0, 'enabled' => 'on', 'parameters' => '{}']];
	}

	if (str_contains($sql, 'plugin_config')) {
		return [['directory' => 'syslog'], ['directory' => 'missingplug'], ['directory' => 'compatible'], ['directory' => 'incompatible']];
	}

	if (str_contains($sql, 'install_tp_') || str_contains($sql, 'install_table_')) {
		return [['value' => 'local']];
	}

	if (str_contains($sql, 'FROM poller') || str_contains($sql, 'poller WHERE')) {
		return [['id' => 2, 'status' => 1, 'gap' => 0]];
	}

	return [];
}
function db_fetch_row_prepared($sql, $params = []) {
	return $GLOBALS['cacti_db']['poller'];
}
function db_execute_prepared($sql, $params = []) {
	return $GLOBALS['cacti_db']['execute'];
}
function db_connect_real(...$args) {
	$GLOBALS['cacti_db']['connect'] = $args;

	return (object) ['ok' => true];
}
function read_config_option($name, $force = false) {
	return $GLOBALS['cacti_db']['settings'][$name] ?? '';
}
function set_config_option($name, $value) {
	$GLOBALS['cacti_db']['settings'][$name] = $value;
}
function set_install_config_option($name, $value) {
	set_config_option($name, $value);
}
function log_install_always($section, $text) {
}
function cacti_log($text, ...$args) {
}
function raise_message($name) {
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function replicate_out($id) {
	return $GLOBALS['cacti_db']['replicate'] ?? true;
}
function file_get_contents($file, ...$args) {
	if (str_ends_with($file, '/cacti.sql') && array_key_exists('cacti_sql', $GLOBALS['cacti_db'])) {
		return $GLOBALS['cacti_db']['cacti_sql'];
	}

	return \file_get_contents($file, ...$args);
}
function get_cacti_base_tables() {
	return ['one'];
}
function html_escape($text) {
	return (string) $text;
}
function api_plugin_uninstall_integrated() {
}
function db_execute($sql, ...$args) {
	return true;
}
function db_table_exists($table) {
	return false;
}
function db_fetch_row($sql, ...$args) {
	return ['row_count' => 1, 'cacti' => '1.3.0'];
}
function cacti_version_compare($a, $b, $op) {
	return version_compare((string) $a, (string) $b, $op);
}
function get_cacti_version_text($long, $version) {
	return (string) $version;
}
function get_cacti_db_version() {
	return '1.2.22';
}
function db_install_errors($version) {
	return 3;
}
function register_process_start(...$args) {
	return true;
}
function unregister_process(...$args) {
}
function is_realm_allowed($realm) {
	return true;
}
function db_install_add_column(...$args) {
}
function db_install_add_key(...$args) {
}
function db_install_execute(...$args) {
}
function db_install_change_column(...$args) {
}
function db_install_update_table(...$args) {
}
function db_fetch_cell($sql, ...$args) {
	return '';
}
function upgrade_boost_process_table() : void {
}
function upgrade_reports() : void {
}
function login_providers_convert_1_3_0() : void {
}
function ldap_convert_1_3_0() : void {
}
function upgrade_dsstats() : void {
}
function upgrade_retired_themes_1_3_0() : void {
}
function db_column_exists($table, $column) {
	return true;
}
function format_cacti_version($version, $parts = 0) {
	return (string) $version;
}

require_once $root . '/lib/Installer/CompletionWarnings.php';
require_once $root . '/lib/Installer/SchemaRequirements.php';
require_once $root . '/lib/Installer/RunReport.php';
require_once $root . '/lib/Installer/UpgradePlan.php';
require_once $root . '/lib/Installer/AuthenticationRequirements.php';
require_once $root . '/lib/Installer/CollectorSynchronizer.php';
require_once $root . '/lib/Installer/CommandRunner.php';
require_once $root . '/lib/Installer/DependencyPreflight.php';

cacti_fixture_source_function($root . '/install/functions.php', 'install_full_sync', __NAMESPACE__);
cacti_fixture_source_function($root . '/install/functions.php', 'install_verify_schema_columns', __NAMESPACE__);
cacti_fixture_source_function($root . '/install/functions.php', 'install_verify_authentication', __NAMESPACE__);
cacti_fixture_source_function($root . '/lib/poller.php', 'poller_connect_to_remote', __NAMESPACE__);
cacti_fixture_source_method($root . '/lib/installer.php', 'getCompletionWarnings', __NAMESPACE__, 'CompletionWarningsHost');
cacti_fixture_source_method($root . '/lib/installer.php', 'getUpgradePlan', __NAMESPACE__, 'UpgradePlanHost');
cacti_fixture_source_method($root . '/lib/installer.php', 'runOperation', __NAMESPACE__, 'RunOperationHost');
cacti_fixture_source_method($root . '/lib/installer.php', 'fullSyncDataCollectors', __NAMESPACE__, 'FullSyncHost');
cacti_fixture_source_method($root . '/lib/installer.php', 'validateRequiredSchema', __NAMESPACE__, 'RequiredSchemaHost');

$sync = install_full_sync();

if (($sync['success'][0] ?? null) !== 2) {
	throw new RuntimeException('install_full_sync did not replicate an available collector');
}

$schema = install_verify_schema_columns();

if ($schema === '') {
	throw new RuntimeException('install_verify_schema_columns accepted an incomplete column map');
}

$auth = install_verify_authentication();

if (!is_string($auth)) {
	throw new RuntimeException('install_verify_authentication did not return a diagnostic string');
}

$GLOBALS['cacti_db']['replicate'] = false;

if ((install_full_sync()['failed'] ?? []) !== [2]) {
	throw new RuntimeException('install_full_sync reported a failed replication as synchronized');
}
unset($GLOBALS['cacti_db']['replicate']);

$GLOBALS['cacti_db']['fail'] = ['FROM poller'];

try {
	install_full_sync();

	throw new LogicException('install_full_sync ignored an unreadable collector inventory');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'Unable to read the remote collector inventory') {
		throw $error;
	}
}

foreach ([
	'Unable to read the shipped Cacti schema'          => ['cacti_sql' => false],
	'The shipped Cacti schema is incomplete'           => ['cacti_sql' => "CREATE TABLE `empty` (\n) ENGINE=InnoDB;\n"],
	'Unable to read database column metadata'          => ['fail' => ['information_schema']],
] as $expected => $state) {
	$GLOBALS['cacti_db'] = array_diff_key($GLOBALS['cacti_db'], ['fail' => 1, 'cacti_sql' => 1]) + $state;

	if (!str_starts_with(install_verify_schema_columns(), $expected)) {
		throw new RuntimeException('install_verify_schema_columns did not report: ' . $expected);
	}
}
unset($GLOBALS['cacti_db']['cacti_sql']);
$GLOBALS['cacti_db']['fail'] = ['login_providers'];

if (install_verify_authentication() !== 'Unable to verify authentication configuration after migration.') {
	throw new RuntimeException('install_verify_authentication accepted unreadable providers');
}
$GLOBALS['cacti_db']['fail'] = [];

if (!is_object(poller_connect_to_remote(2))) {
	throw new RuntimeException('poller_connect_to_remote did not connect with SSL argument defaults');
}

if (($GLOBALS['cacti_db']['connect'][8] ?? 'missing') !== '' || ($GLOBALS['cacti_db']['connect'][9] ?? 'missing') !== '') {
	throw new RuntimeException('poller_connect_to_remote did not default missing SSL files to empty strings');
}

$warningsHost                                        = new CompletionWarningsHost();
$GLOBALS['cacti_db']['settings']['install_warnings'] = (new \Cacti\Installer\CompletionWarnings(['failed' => [2]]))->encode();

if ($warningsHost->getCompletionWarnings() === []) {
	throw new RuntimeException('getCompletionWarnings dropped collector messages');
}

if ((new RequiredSchemaHost())->validateRequiredSchema() !== install_verify_schema_columns()) {
	throw new RuntimeException('validateRequiredSchema skipped the column check for a complete table set');
}

$planHost                       = new UpgradePlanHost();
$planHost->old_cacti_version    = '1.2.22';
$GLOBALS['cacti_version_codes'] = ['1.2.22' => true, '1.3.0' => true];
$GLOBALS['plugins_integrated']  = ['syslog'];
$plan                           = json_decode($planHost->getUpgradePlan()->encode(), true);

if (!in_array('incompatible: Disabled because the plugin is incompatible', $plan['plugins'] ?? [], true)
	|| preg_grep('/^compatible:/', $plan['plugins'] ?? []) !== []) {
	throw new RuntimeException('getUpgradePlan misclassified plugin compatibility');
}
$GLOBALS['cacti_db']['fail'] = ['install_tp_'];

try {
	$planHost->getUpgradePlan();

	throw new LogicException('getUpgradePlan accepted an unreadable template selection');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'Unable to read a complete installer upgrade plan') {
		throw $error;
	}
}
$GLOBALS['cacti_db']['fail'] = [];

$operationHost            = new RunOperationHost();
$operationHost->runReport = new \Cacti\Installer\RunReport();

if ($operationHost->runOperation('preflight', static fn () => '') !== '') {
	throw new RuntimeException('runOperation dropped a successful step');
}

try {
	$operationHost->runOperation('schema', static fn () => throw new RuntimeException('step-fail'));

	throw new RuntimeException('runOperation swallowed a thrown step');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'step-fail') {
		throw $error;
	}
}

class Installer {
	public const STEP_NONE                     = 0;
	public const STEP_WELCOME                  = 1;
	public const STEP_INSTALL_CONFIRM          = 10;
	public const STEP_INSTALL                  = 97;
	public const STEP_COMPLETE                 = 98;
	public const STEP_ERROR                    = 99;
	public const STEP_GO_SITE                  = -1;
	public const STEP_GO_FORUMS                = -2;
	public const STEP_GO_GITHUB                = -3;
	public const MODE_INSTALL                  = 1;
	public const MODE_POLLER                   = 2;
	public const MODE_UPGRADE                  = 3;
	public const MODE_DOWNGRADE                = 4;
	public const PROGRESS_START                = 1;
	public const PROGRESS_VERSION_BEGIN        = 80;
	public const PROGRESS_VERSION_END          = 85;
	public const PROGRESS_COLLECTOR_SYNC_START = 77;
	public const PROGRESS_COLLECTOR_SYNC_END   = 78;
	public const PROGRESS_COMPLETE             = 100;

	public static function fullSyncDataCollectorLog($ids, $format) : void {
	}

	public static function dateFromMicrotime($time) {
		return new \DateTime();
	}

	public static function setPhpOption($name, $value) : void {
	}

	public static function sectionTitle($text) {
		return (string) $text;
	}

	public static function sectionTitleError($text = '') {
		return (string) $text;
	}

	public static function sectionCode($text) {
		return (string) $text;
	}

	public static function sectionNormal($text) {
		return (string) $text;
	}

	public static function sectionNote($text) {
		return (string) $text;
	}

	public static function sectionSubTitleEnd() {
		return '';
	}

	public static function showInstallOptions() {
		return '';
	}

	public static function getCompletionWarnings() : array {
		return ['collector'];
	}

	public static function disableInvalidPlugins() : void {
	}

	public static function fullSyncDataCollectors() : void {
	}

	public static function beginInstall($time, $installer = null) : bool {
		return true;
	}

	public static function __callStatic($name, $args) {
		return '';
	}
}

$installExtra = <<<'PHP'
	public function runOperation(string $name, callable $operation) : mixed { return $name === 'preflight' ? '' : $operation(); }
	public function setProgress($progress) : void {}
	public function setStep($step) : void { $this->stepCurrent = $step; }
	public function getStep() { return $this->stepCurrent ?? 98; }
	public function setDefaults() : void {}
	public function addError(...$args) : void {}
	public function setCSRFSecret() { return true; }
	public function convertDatabase() : string { return ''; }
	public function installPoller() : string { return ''; }
	public function installTemplate() : string { return ''; }
	public function installServer() : string { return ''; }
	public function setDefaultTemplate() : void {}
	public function validateRequiredSchema() : string { return ''; }
	public function writeInstalledVersion() : bool { return true; }
	public function checkDatabaseUpgrade($version) : int { return 3; }
	public function getUpgradePlan() { return new \Cacti\Installer\UpgradePlan('1.2.22', '1.3.0', ['1.3.0']); }
	public static function getCompletionWarnings() : array { return ['collector']; }
	public $buttonPrevious;
	public function __construct() {
		$this->buttonNext = (object) ['Text' => '', 'Enabled' => false, 'Step' => 0, 'Visible' => true];
		$this->buttonPrevious = (object) ['Text' => '', 'Enabled' => false, 'Step' => 0, 'Visible' => true];
	}
	public function __call($name, $args) { return ''; }
	public static function __callStatic($name, $args) { return ''; }
PHP;

cacti_fixture_source_method($root . '/lib/installer.php', 'processStepInstallConfirm', __NAMESPACE__, 'ConfirmHost', $installExtra);
cacti_fixture_source_method($root . '/lib/installer.php', 'processStepComplete', __NAMESPACE__, 'CompleteHost', $installExtra);
cacti_fixture_source_method($root . '/lib/installer.php', 'install', __NAMESPACE__, 'InstallHost', $installExtra . "\n\tpublic function upgradeDatabase() : string { return ''; }\n");
cacti_fixture_source_method($root . '/lib/installer.php', 'runCommand', __NAMESPACE__, 'CommandHost', $installExtra);
cacti_fixture_source_method($root . '/lib/installer.php', 'upgradeDatabase', __NAMESPACE__, 'UpgradeHost', $installExtra);
cacti_fixture_source_method($root . '/lib/installer.php', 'beginInstall', __NAMESPACE__, 'BeginInstallHost', $installExtra . "\n\tpublic function install() { throw new \\RuntimeException('install-fail'); }\n");

$confirmHost                    = new ConfirmHost();
$confirmHost->mode              = Installer::MODE_UPGRADE;
$confirmHost->buttonNext        = (object) ['Text' => '', 'Enabled' => false, 'Step' => 0, 'Visible' => true];
$confirmHost->old_cacti_version = '1.2.22';

try {
	$confirmHost->processStepInstallConfirm();
} catch (Throwable $error) {
}

$completeHost               = new CompleteHost();
$completeHost->stepCurrent  = Installer::STEP_COMPLETE;

try {
	$completeHost->processStepComplete();
} catch (Throwable $error) {
}

foreach ([Installer::MODE_UPGRADE, Installer::MODE_INSTALL, Installer::MODE_POLLER] as $mode) {
	$installHost            = new InstallHost();
	$installHost->mode      = $mode;
	$installHost->runReport = new \Cacti\Installer\RunReport();
	$installHost->install();
}

(new CommandHost())->runCommand([PHP_BINARY, '-r', 'echo "ok";']);

require __DIR__ . '/InstallerChangedCoverageGlobals.php';

$upgradeHost                    = new UpgradeHost();
$upgradeHost->old_cacti_version = '1.2.13';
$upgradeHost->runReport         = new \Cacti\Installer\RunReport();
$GLOBALS['cacti_version_codes'] = ['1.2.14' => true, '1.3.0' => true];

ob_start();

try {
	$upgradeHost->upgradeDatabase();
} catch (Throwable $error) {
} finally {
	ob_end_clean();
}
$upgradeReport = json_decode($GLOBALS['cacti_db']['settings']['maintenance_lastrun_installer_report'], true);

if (array_column($upgradeReport['operations'], 'succeeded', 'version') !== ['1.2.14' => true, '1.3.0' => false]) {
	throw new RuntimeException('upgradeDatabase did not report the successful and failed migrations');
}

$beginHost              = new BeginInstallHost();
$beginHost->runReport   = new \Cacti\Installer\RunReport();
$beginHost->stepCurrent = 0;

$GLOBALS['cacti_db']['settings']['install_eula'] = 'on';

BeginInstallHost::beginInstall('-b', $beginHost);

if ($beginHost->stepCurrent !== Installer::STEP_ERROR
	|| json_decode($GLOBALS['cacti_db']['settings']['maintenance_lastrun_installer_report'], true)['state'] !== 'failed') {
	throw new RuntimeException('beginInstall did not record the failed installation');
}

FullSyncHost::fullSyncDataCollectors();

cacti_fixture_source_function($root . '/install/upgrades/1_3_0.php', 'upgrade_to_1_3_0', __NAMESPACE__);

try {
	upgrade_to_1_3_0();
} catch (Throwable $error) {
}

foreach (['include/global_arrays.php', 'install/background.php', 'install/install.php'] as $instrument) {
	cacti_fixture_instrument_file($root . '/' . $instrument);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
cacti_fixture_map_require($root . '/install/step_json.php', [
	42 => "return;\n",
]);

$GLOBALS['cacti_realm_allowed'] = false;

try {
	cacti_fixture_map_require($root . '/install/report.php', [
		26 => "// auth stubbed\n",
		30 => "throw new RuntimeException('forbidden');\n",
	]);
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'forbidden') {
		throw $error;
	}
}

$GLOBALS['cacti_realm_allowed']                                          = true;
$GLOBALS['cacti_db']['settings']['maintenance_lastrun_installer_report'] = '';

try {
	cacti_fixture_map_require($root . '/install/report.php', [
		26 => "// auth stubbed\n",
		37 => "throw new RuntimeException('not found');\n",
	]);

	throw new LogicException('report.php served an empty report');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'not found') {
		throw $error;
	}
}

$GLOBALS['cacti_db']['settings']['maintenance_lastrun_installer_report'] = '{"ok":true}';
cacti_fixture_map_require($root . '/install/report.php', [
	26 => "// auth stubbed\n",
]);

$preflightCli = [
	25 => "// skipped production autoload\n",
	27 => "\$preflightErrors = ['missing fixture'];\n",
	45 => "throw new RuntimeException('preflight');\n",
];

try {
	cacti_fixture_map_require($root . '/install/preflight.php', $preflightCli);

	throw new RuntimeException('preflight did not abort on errors');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'preflight') {
		throw $error;
	}
}

try {
	cacti_fixture_map_require($root . '/install/preflight.php', $preflightCli + [
		30 => "if (false) {\n",
		35 => "if (true) {\n",
	]);

	throw new RuntimeException('preflight json did not abort');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'preflight') {
		throw $error;
	}
}

try {
	cacti_fixture_map_require($root . '/install/preflight.php', $preflightCli + [
		30 => "if (false) {\n",
		35 => "if (false) {\n",
	]);

	throw new RuntimeException('preflight text did not abort');
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'preflight') {
		throw $error;
	}
}

cacti_fixture_map_fragment($root . '/cli/install_cacti.php', 261, 310, <<<'PHP'
if (!class_exists('Installer', false)) {
	class Installer {
		public const STEP_INSTALL_CONFIRM = 10;
		public const STEP_INSTALL = 97;
		public const STEP_COMPLETE = 98;
		public const STEP_ERROR = 99;
		public static function beginInstall($time, $installer = null) : bool { return true; }
		public static function getCompletionWarnings() : array { return ['collector']; }
	}
}
$should_install = true;
$force_install = false;
$installer = new class {
	private int $calls = 0;
	public function getStep() {
		$this->calls++;
		return $this->calls === 1 ? 10 : 98;
	}
	public function getUpgradePlan() {
		return new class {
			public function encode() { return '{}'; }
		};
	}
	public function getErrors() { return []; }
};
PHP);

// cli/upgrade_database.php loop: the migration function is resolved by line
// 176 so each run selects a missing, succeeding, or failing-verification path.
$upgradePrefix = <<<'PHP'
$cacti_version_codes = ['1.2.22' => 'h', '1.3.0' => 'h'];
$old_cacti_version = '1.2.22';
if (!function_exists('cacti_fixture_upgrade_target')) {
	function cacti_fixture_upgrade_target() : void {}
	function cacti_fixture_upgrade_throws() : void { throw new RuntimeException('migration-fixture'); }
}
PHP;

foreach ([
	'missing function'    => [[176 => "\$upgrade_function = 'cacti_fixture_upgrade_missing';"], 'failed', ['migration' => false]],
	'verified upgrade'    => [[176 => "\$upgrade_function = 'cacti_fixture_upgrade_target';"], 'succeeded', ['migration' => true, 'schema' => true, 'authentication' => true, 'version' => true]],
	'failed verification' => [[
		176 => "\$upgrade_function = 'cacti_fixture_upgrade_target';",
		206 => "\t\t\t\t\$error   = \$verify === 'install_verify_schema_columns' ? 'schema-fixture' : \$verify();",
	], 'failed', ['migration' => true, 'schema' => false]],
	'thrown migration'    => [[176 => "\$upgrade_function = 'cacti_fixture_upgrade_throws';"], 'failed', ['migration' => false]],
] as $case => [$replacements, $state, $operations]) {
	ob_start();
	cacti_fixture_map_fragment($root . '/cli/upgrade_database.php', 146, 257, $upgradePrefix, $replacements);
	ob_end_clean();
	$upgradeReport = json_decode($GLOBALS['cacti_db']['settings']['maintenance_lastrun_installer_report'], true);

	if ($upgradeReport['state'] !== $state || array_column($upgradeReport['operations'], 'succeeded', 'operation') !== $operations) {
		throw new RuntimeException('cli/upgrade_database.php mishandled the ' . $case . ' case');
	}
}

// Report invariants the run paths above do not reach.
$report = new \Cacti\Installer\RunReport();
$report->finish(true, new \Cacti\Installer\CompletionWarnings());

if (json_decode($report->encode(), true)['state'] !== 'succeeded') {
	throw new RuntimeException('RunReport did not finish a clean run');
}
$report = new \Cacti\Installer\RunReport();
$report->record('migration', 1, true, '1.2.' . str_repeat('9', 4096));
$report->finish(true, new \Cacti\Installer\CompletionWarnings(['failed' => []]));

try {
	$report->encode();

	throw new LogicException('RunReport encoded an oversized report');
} catch (LogicException $error) {
	if ($error->getMessage() !== 'Installer report exceeds its storage limit') {
		throw $error;
	}
}

// The preflight default dependency list and its platform-check cleanup.
$preflightRoot  = sys_get_temp_dir() . '/cacti-installer-preflight-' . getmypid();
$preflightLock  = ['packages' => [['name' => 'test/package', 'version' => '1.0.0']]];
$preflightFiles = [
	'composer.json'                              => json_encode(['require' => ['php' => '^8.3']]),
	'composer.lock'                              => json_encode($preflightLock),
	'include/vendor/composer/installed.json'     => json_encode($preflightLock),
	'include/vendor/composer/platform_check.php' => '<?php',
	'include/vendor/autoload.php'                => '<?php',
];

foreach (['cacti.sql', 'include/js/jquery.js', 'include/js/jquery-ui.js', 'include/js/htmx.js', 'include/js/purify.js',
	'include/js/select2.js', 'include/js/jquery.tablesorter.js', 'include/js/d3.js', 'include/js/billboard.js',
	'include/js/pace.js', 'include/js/big.js', 'include/js/lzjs.js', 'include/js/screenfull.js', 'include/js/jstree.js',
	'include/js/jquery.tablesorter.widgets.js', 'include/js/jquery.tablesorter.pager.js',
	'include/js/jquery.validate/jquery.validate.js', 'include/js/jquery.validate/jquery.validate.min.js',
	'include/js/jquery.validate/additional-methods.js', 'include/js/jquery.validate/additional-methods.min.js',
	'install/install.js', 'include/layout.js', 'install/install.css', 'include/css/jquery.contextMenu.css',
	'include/css/billboard.css', 'include/fa/css/all.css', 'include/tabler/dist/tabler-icons.min.css',
	'include/vendor/lipis/flag-icons/css/flag-icons.css', 'include/themes/print.css'] as $asset) {
	$preflightFiles[$asset] = '/* asset */';
}

foreach ($preflightFiles as $name => $contents) {
	if (!is_dir(dirname($preflightRoot . '/' . $name))) {
		mkdir(dirname($preflightRoot . '/' . $name), 0700, true);
	}
	file_put_contents($preflightRoot . '/' . $name, $contents);
}

try {
	// Composer's real autoloader is already loaded, so the default classes resolve.
	if ((new \Cacti\Installer\DependencyPreflight($preflightRoot))->errors() !== []) {
		throw new RuntimeException('DependencyPreflight rejected a complete installation');
	}
} finally {
	$entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($preflightRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

	foreach ($entries as $entry) {
		$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
	}
	rmdir($preflightRoot);
}

print json_encode(['passed' => 8], JSON_THROW_ON_ERROR) . "\n";
