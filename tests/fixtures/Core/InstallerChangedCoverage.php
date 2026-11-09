<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiInstallerChangedFixture;

use RuntimeException;
use Throwable;

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$autoload = dirname(__DIR__, 3) . '/include/vendor/autoload.php';

if (is_file($autoload)) {
	require_once $autoload;
}

$root = dirname(__DIR__, 3);

foreach ([
	'CACTI_PATH_BASE'            => $root,
	'CACTI_PATH_LIBRARY'         => $root . '/lib',
	'CACTI_PATH_INSTALL'         => $root . '/install',
	'CACTI_PATH_PLUGINS'         => $root . '/plugins',
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
		return [['directory' => 'syslog'], ['directory' => 'missingplug']];
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
	return true;
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

$planHost                       = new UpgradePlanHost();
$planHost->old_cacti_version    = '1.2.22';
$GLOBALS['cacti_version_codes'] = ['1.2.22' => true, '1.3.0' => true];
$GLOBALS['plugins_integrated']  = ['syslog'];
$planHost->getUpgradePlan();
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

eval(<<<'PHP'
namespace {
	foreach ([
		'db_install_add_column' => 'function db_install_add_column(...$args) {}',
		'db_install_add_key' => 'function db_install_add_key(...$args) {}',
		'db_install_execute' => 'function db_install_execute(...$args) {}',
		'db_install_change_column' => 'function db_install_change_column(...$args) {}',
		'db_table_exists' => 'function db_table_exists($table) { return false; }',
		'db_column_exists' => 'function db_column_exists($table, $column) { return true; }',
		'db_fetch_cell' => 'function db_fetch_cell($sql, ...$args) { return ""; }',
		'unregister_process' => 'function unregister_process(...$args) {}',
		'install_verify_schema_columns' => 'function install_verify_schema_columns() { return ""; }',
		'install_verify_authentication' => 'function install_verify_authentication() { return ""; }',
		'db_install_errors' => 'function db_install_errors($version) { return 3; }',
		'cacti_version_compare' => 'function cacti_version_compare($a, $b, $op) { return version_compare((string) $a, (string) $b, $op); }',
		'get_cacti_version_text' => 'function get_cacti_version_text($long, $version) { return (string) $version; }',
		'get_cacti_db_version' => 'function get_cacti_db_version() { return "1.2.22"; }',
		'db_fetch_row' => 'function db_fetch_row($sql, ...$args) { return ["row_count" => 1, "cacti" => "1.3.0"]; }',
		'read_config_option' => 'function read_config_option($name, $force = false) { return $GLOBALS["cacti_db"]["settings"][$name] ?? ""; }',
		'set_config_option' => 'function set_config_option($name, $value) { $GLOBALS["cacti_db"]["settings"][$name] = $value; }',
		'log_install_always' => 'function log_install_always($section, $text) {}',
		'log_install_high' => 'function log_install_high($section, $text) {}',
		'cacti_log' => 'function cacti_log($text, ...$args) {}',
		'db_execute_prepared' => 'function db_execute_prepared($sql, $params = []) { return true; }',
		'is_realm_allowed' => 'function is_realm_allowed($realm) { return !empty($GLOBALS["cacti_realm_allowed"]); }',
		'__' => 'function __($text, ...$args) { return $args !== [] ? vsprintf($text, $args) : $text; }',
	] as $name => $source) {
		if (!function_exists($name)) {
			eval('namespace { ' . $source . ' }');
		}
	}
}
PHP);

$upgradeHost                    = new UpgradeHost();
$upgradeHost->old_cacti_version = '1.2.22';
$upgradeHost->runReport         = new \Cacti\Installer\RunReport();

try {
	$upgradeHost->upgradeDatabase();
} catch (Throwable $error) {
}

$beginHost              = new BeginInstallHost();
$beginHost->runReport   = new \Cacti\Installer\RunReport();
$beginHost->stepCurrent = 0;

try {
	BeginInstallHost::beginInstall('-b', $beginHost);
} catch (RuntimeException $error) {
	if ($error->getMessage() !== 'install-fail') {
		throw $error;
	}
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

cacti_fixture_map_fragment($root . '/cli/upgrade_database.php', 146, 257, <<<'PHP'
$cacti_version_codes = ['1.2.22' => 'h', '1.3.0' => 'h'];
$old_cacti_version = '1.2.22';
$upgrade_failed = false;
$report = new \Cacti\Installer\RunReport();
$prev_cacti_version = '1.2.22';
$orig_cacti_version = '1.2.22';
if (!function_exists('db_install_errors')) {
	function db_install_errors($version) { return 3; }
}
if (!function_exists('cacti_version_compare')) {
	function cacti_version_compare($a, $b, $op) { return version_compare((string) $a, (string) $b, $op); }
}
if (!function_exists('get_cacti_version_text')) {
	function get_cacti_version_text($long, $version) { return (string) $version; }
}
if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql, ...$args) { return ['row_count' => 1, 'cacti' => '1.3.0']; }
}
if (!function_exists('unregister_process')) {
	function unregister_process(...$args) {}
}
if (!function_exists('install_verify_schema_columns')) {
	function install_verify_schema_columns() { return ''; }
}
if (!function_exists('install_verify_authentication')) {
	function install_verify_authentication() { return ''; }
}
if (!function_exists('db_install_add_column')) {
	function db_install_add_column(...$args) {}
	function db_install_add_key(...$args) {}
	function db_install_execute(...$args) {}
	function db_install_change_column(...$args) {}
	function db_table_exists($table) { return false; }
}
PHP
	, [176 => 'if (!function_exists($upgrade_function)) { include($upgrade_file); }']);

try {
	cacti_fixture_map_fragment($root . '/cli/upgrade_database.php', 146, 257, <<<'PHP'
$cacti_version_codes = ['1.2.22' => 'h', '1.3.0' => 'h'];
$old_cacti_version = '1.2.22';
$upgrade_failed = false;
$report = new \Cacti\Installer\RunReport();
$prev_cacti_version = '1.2.22';
$orig_cacti_version = '1.2.22';
if (!function_exists('db_install_errors')) {
	function db_install_errors($version) { return 3; }
}
if (!function_exists('cacti_version_compare')) {
	function cacti_version_compare($a, $b, $op) { return version_compare((string) $a, (string) $b, $op); }
}
if (!function_exists('unregister_process')) {
	function unregister_process(...$args) {}
}
if (!function_exists('install_verify_schema_columns')) {
	function install_verify_schema_columns() { return 'schema'; }
}
if (!function_exists('install_verify_authentication')) {
	function install_verify_authentication() { return ''; }
}
PHP
		, [176 => 'if (!function_exists($upgrade_function)) { include($upgrade_file); }']);
} catch (Throwable $error) {
}

print json_encode(['passed' => 8], JSON_THROW_ON_ERROR) . "\n";
