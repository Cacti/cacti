<?php
/*
  +-------------------------------------------------------------------------+
  | Copyright (C) 2004-2022 The Cacti Group                                 |
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

include_once(dirname(__FILE__) . '/../lib/poller.php');

class Installer implements JsonSerializable {
	const EXIT_DB_EMPTY = 1;
	const EXIT_DB_OLD = 2;

	/***********************************************************
	 * The STEP_ constants are defined in the following files: *
	 *                                                         *
	 * lib/installer.php                                       *
	 * install/install.js                                      *
	 *                                                         *
	 * All files must be updated to match for the installation *
	 * process to work properly                                *
	 ***********************************************************/

	const STEP_NONE = 0;
	const STEP_WELCOME = 1;
	const STEP_CHECK_DEPENDENCIES = 2;
	const STEP_INSTALL_TYPE = 3;
	const STEP_PERMISSION_CHECK = 4;
	const STEP_BINARY_LOCATIONS = 5;
	const STEP_INPUT_VALIDATION = 6;
	const STEP_PROFILE_AND_AUTOMATION = 7;
	const STEP_TEMPLATE_INSTALL = 8;
	const STEP_CHECK_TABLES = 9;
	const STEP_INSTALL_CONFIRM = 10;
	const STEP_INSTALL_OLDVERSION = 11;
	const STEP_INSTALL = 97;
	const STEP_COMPLETE = 98;
	const STEP_ERROR = 99;
	const STEP_GO_SITE = -1;
	const STEP_GO_FORUMS = -2;
	const STEP_GO_GITHUB = -3;
	const STEP_TEST_REMOTE = -4;

	/* Installer mode */
	const MODE_NONE = 0;
	const MODE_INSTALL = 1;
	const MODE_POLLER = 2;
	const MODE_UPGRADE = 3;
	const MODE_DOWNGRADE = 4;

	/* Progress through the STEP_INSTALL section */
	const PROGRESS_NONE = 0;
	const PROGRESS_START = 1;
	const PROGRESS_CSRF_BEGIN = 2;
	const PROGRESS_CSRF_END = 3;
	const PROGRESS_UPGRADES_BEGIN = 5;
	const PROGRESS_UPGRADES_END = 30;
	const PROGRESS_TABLES_BEGIN = 35;
	const PROGRESS_TEMPLATES_BEGIN = 41;
	const PROGRESS_TEMPLATES_END = 60;
	const PROGRESS_PROFILE_START = 61;
	const PROGRESS_PROFILE_POLLER = 63;
	const PROGRESS_PROFILE_DEFAULT = 64;
	const PROGRESS_PROFILE_END = 65;
	const PROGRESS_AUTOMATION_START = 66;
	const PROGRESS_AUTOMATION_END = 68;
	const PROGRESS_DEVICE_START = 71;
	const PROGRESS_DEVICE_TEMPLATE = 72;
	const PROGRESS_DEVICE_GRAPH = 73;
	const PROGRESS_DEVICE_TREE = 74;
	const PROGRESS_DEVICE_END = 75;
	const PROGRESS_COLLECTOR_SYNC_START = 77;
	const PROGRESS_COLLECTOR_SYNC_END = 78;
	const PROGRESS_VERSION_BEGIN = 80;
	const PROGRESS_VERSION_END = 85;
	const PROGRESS_COMPLETE = 100;

	private $old_cacti_version;

	/* Common variables */
	private $mode;
	private $stepCurrent;
	private $stepPrevious;
	private $stepNext;
	private $stepData = null;
	private $stepError = array();

	private $output;
	private $templates;
	private $rrdVersion;
	private $paths;
	private $theme;
	private $locales;
	private $language;
	private $tables;
	private $runtime;
	private $errors;
	private $profile;

	private $automationMode = null;
	private $automationOverride = null;

	private $buttonNext = null;
	private $buttonPrevious = null;
	private $buttonTest = null;

	private $iconClass;
	private $eula;
	private $cronInterval;
	private $defaultAutomation;
	private $permissions;
	private $extensions;
	private $modules;
	private $automationRange;
	private $snmpOptions;

	/*
	 * class Installer initialization
	 *
	 * usage:
	 *    $installer = new Installer($installData)
	 *
	 * @arg installData - array of fields to update
	 */
	public function __construct($install_params = array()) {
		log_install_high('step', 'Install Parameters: ' . clean_up_lines(var_export($install_params, true)));

		$this->old_cacti_version = get_cacti_db_version_raw();
		$this->setRuntime(isset($install_params['Runtime']) ? $install_params['Runtime'] : 'unknown');

		$step = read_config_option('install_step', true);
		log_install_high('step', 'Initial: ' . clean_up_lines(var_export($step, true)));

		if (empty($step)) {
			$step = $this->getStepDefault();
		}
		$this->stepError = false;

		if ($step == Installer::STEP_INSTALL) {
			$install_version = read_config_option('install_version', true);

			log_install_high('step', 'Previously complete: ' . clean_up_lines(var_export($install_version, true)));

			if (empty($install_version)) {
				$install_version = $this->old_cacti_version;
			}

			$install_params = array();
			$install_error = read_config_option('install_error', true);

			if (!empty($install_error)) {
				$step = Installer::STEP_ERROR;
			} elseif (!is_install_needed($install_version)) {
				log_install_debug('step', 'Does match: ' . clean_up_lines(var_export($this->old_cacti_version, true)));
			}
		} elseif ($step >= Installer::STEP_COMPLETE) {
			$install_version = read_config_option('install_version', true);

			log_install_high('step', 'Previously complete: ' . clean_up_lines(var_export($install_version, true)));

			if (is_install_needed($install_version)) {
				log_install_debug('step', 'Does not match: ' . clean_up_lines(var_export(CACTI_VERSION_FULL, true)));

				$this->stepError = Installer::STEP_WELCOME;

				db_execute('DELETE FROM settings WHERE name LIKE \'install_%\'');
			} else {
				$install_params = array();
			}
		}

		log_install_high('step', 'After: ' . clean_up_lines(var_export($step, true)));
		$this->setStep($step);

		$this->iconClass = array(
			DB_STATUS_ERROR   => 'fa fa-thumbs-down',
			DB_STATUS_WARNING => 'fa fa-exclamation-triangle',
			DB_STATUS_RESTART => 'fa fa-exclamation-triangle',
			DB_STATUS_SUCCESS => 'fa fa-thumbs-up',
			DB_STATUS_SKIPPED => 'fa fa-check-circle'
		);

		$this->errors        = array();
		$this->templates     = array();
		$this->eula          = read_config_option('install_eula', true);
		$this->cronInterval  = read_config_option('cron_interval', true);
		$this->locales       = get_installed_locales();
		$this->stepData      = null;
		$this->setLanguage($this->getLanguage());
		$this->setTheme($this->getTheme());

		if (empty($this->theme)) {
			$this->setTheme('modern');
		}

		if ($step < Installer::STEP_INSTALL) {
			$this->setDefaults($install_params);
		}

		log_install_debug('step', 'Error: ' . clean_up_lines(var_export($this->stepError, true)));
		log_install_debug('step', 'Done: ' . clean_up_lines(var_export($this->stepCurrent, true)));
	}

	/* jsonSerialize() - provides JSON object of return data with optional
	 *                   values output dependant on Runtime mode */
	public function jsonSerialize(): mixed {
		if (empty($this->output)) {
			$this->output = $this->processCurrentStep();
		}

		$basics = array(
			'Mode'     => $this->mode,
			'Step'     => $this->stepCurrent,
			'Errors'   => $this->errors
		);

		$webdata = array();

		/* Are we running in either Web or Json mode? */
		if ($this->runtime == 'Web' || $this->runtime == 'Json') {
			$webdata += array(
				'Eula'     => $this->eula,
				'Prev'     => $this->buttonPrevious,
				'Next'     => $this->buttonNext,
				'Test'     => $this->buttonTest,
				'Theme'    => $this->theme,
				'StepData' => $this->stepData,
			);
		}

		/* Are we running in only Web mode? */
		if ($this->runtime == 'Web') {
			$webdata += array(
				'Html'     => $this->output,
			);
		}

		return array_merge($basics, $webdata);
	}

	/* getData() - alias for jsonSerialize() */
	public function getData() {
		return $this->jsonSerialize();
	}

	/* getErrors() - retrieve an array of all recorded errors */
	public function getErrors() {
		return (isset($this->errors) && !empty($this->errors)) ? $this->errors : array();
	}

	/* processParameters - process array of parameters to override defaults
	 * @arg params_install - array of parameters to process where key
	 *                       matches XXX from setXXX/getXXX functions */
	protected function processParameters($params_install = array()) {
		if (empty($params_install) || !is_array($params_install)) {
			$params_install = array();
		}

		// Always set the current step first
		if (isset($params_install['Step'])) {
			$this->setStep($params_install['Step']);
			unset($params_install['Step']);
		}

		foreach ($params_install as $key => $value) {
			switch ($key) {
				case 'Mode':
					$this->setMode($value);
					break;
				case 'Eula':
					$this->setEula($value);
					break;
				case 'Prev':
					$this->buttonPrevious = new InstallerButton($value);
					break;
				case 'Next':
					$this->buttonNext = new InstallerButton($value);
					break;
				case 'Test':
					$this->buttonTest = new InstallerButton($value);
					break;
				case 'Language':
					$this->setLanguage($value);
					break;
				case 'Profile':
					$this->setProfile($value);
					break;
				case 'CronInterval':
					$this->setCronInterval($value);
					break;
				case 'AutomationMode':
					$this->setAutomationMode($value);
					break;
				case 'AutomationOverride':
					$this->setAutomationOverride($value);
					break;
				case 'AutomationRange':
					$this->setAutomationRange($value);
					break;
				case 'Templates':
					$this->setTemplates($value);
					break;
				case 'Tables':
					$this->setTables($value);
					break;
				case 'Paths':
					$this->setPaths($value);
					break;
				case 'RRDVersion':
					$this->setRRDVersion($value);
					break;
				case 'Theme':
					$this->setTheme($value);
					break;
				case 'SnmpOptions':
					$this->setSnmpOptions($value);
					break;
				case 'Runtime':
					break;
				default:
					log_install_always('badkey', "$key => $value");
			}
		}
	}

	/* setDefaults - apply default values from array object
	 * @arg install_params - optional key/value array where key matches
	 *                       XXX from setXXX/getXXX functions  */
	private function setDefaults($install_params = array()) {
		$this->defaultAutomation = array(
			array(
				'name'          => 'Net-SNMP Device',
				'hash'          => '07d3fe6a52915f99e642d22e27d967a4',
				'sysDescrMatch' => 'Linux',
				'sysNameMatch'  => '',
				'sysOidMatch'   => '',
				'availMethod'   => 2,
				'sequence'      => 1
			),
			array(
				'name'          => 'Windows Device',
				'hash'          => '5b8300be607dce4f030b026a381b91cd',
				'sysDescrMatch' => 'Windows',
				'sysNameMatch'  => '',
				'sysOidMatch'   => '',
				'availMethod'   => 2,
				'sequence'      => 2
			),
			array(
				'name'          => 'Cisco Router',
				'hash'          => 'cae6a879f86edacb2471055783bec6d0',
				'sysDescrMatch' => '(Cisco Internetwork Operating System Software|IOS)',
				'sysNameMatch'  => '',
				'sysOidMatch'   => '',
				'availMethod'   => 2,
				'sequence'      => 3
			)
		);

		$this->tables             = $this->getTables();

		// You have to set the paths before you start executing commands
		$this->paths              = install_file_paths();
		$this->setPaths($this->getPaths());

		$this->permissions        = $this->getPermissions();
		$this->modules            = $this->getModules();

		$this->setTemplates($this->getTemplates());
		$this->setProfile($this->getProfile());
		$this->setAutomationMode($this->getAutomationMode());
		$this->setAutomationOverride($this->getAutomationOverride());
		$this->setAutomationRange($this->getAutomationRange());
		$this->setRRDVersion($this->getRRDVersion(), 'default ');
		$this->snmpOptions = $this->getSnmpOptions();
		$this->setMode($this->getMode());

		log_install_high('', 'Installer::processParameters(' . clean_up_lines(json_encode($install_params)) . ')');
		if (!empty($install_params)) {
			$this->processParameters($install_params);
		}

		if ($this->stepError !== false && $this->stepCurrent > $this->stepError) {
			$this->setStep($this->stepError);
		}
	}

	/* setTrueFalse() - determine whether @param can be mapped to either
	 *                  True or False and if so, assign result to $field
	 * @param  - value to be set if it can be mapped to True or False
	 * @field  - variable reference to be set
	 * @option - name of the option */
	private function setTrueFalse($param, &$field, $option = '', $save = true) {
		$value = null;
		if ($param === true || $param === 'true' || $param === 'on' || $param === 1 || $param === '1') {
			$value = true;
		} elseif ($param === false || $param === 'false' || $param === '' || $param === 0 || $param === '0') {
			$value = false;
		}

		if ($value !== null) {
			$field = $value;
			if ($save) {
				set_config_option('install_' . $option, $param);
			}
		}

		$result = $value !== null;
		log_install_high('', "setTrueFalse($option, " . var_export($param, true) . " sets $value, returns $result");
		return $result;
	}

	/* addError() - adds a new error to the array or updates an existing one
	 * @step    - Which step of the installer reports the error and
	 *          - should be Installer::STEP_ constant
	 * @section - Title of section causing a problem
	 * @item    - Individual item that caused the problem
	 * @text    - Descriptive text of the error */
	public function addError($step, $section, $item, $text = false) {
		if (!isset($this->errors[$section])) {
			$this->errors[$section] = array();
		}

		if ($text === false) {
			$this->errors[$section][] = $item;
			log_install_medium('errors', "addError($section, $item)");
		} else {
			$this->errors[$section][$item] = $text;
			log_install_medium('errors', "addError($section, $item, $text)");
		}

		log_install_debug('errors', 'stepError = ' . $step . ' -> ' . clean_up_lines(var_export($this->stepError, true)));
		if ($this->stepError === false || $this->stepError > $step) {
			$this->stepError = $step;
		}
		log_install_debug('errors-json', clean_up_lines(var_export($this->errors, true)));
	}

	/* setProgress() - set the progress point of Installer::STEP_INSTALL
	 * @param_progress - one of Installer::PROGRESS_ constants */
	private function setProgress($param_process) {
		log_install_medium('', "Progress: $param_process");
		set_config_option('install_progress', $param_process);
		set_config_option('install_updated', microtime(true));
	}

	/* sanitizeRRDVersion() - ensure version number is valid
	 * @param_rrdver    - version to be sanitized
	 * @default_version - version to return if not sanitized */
	private function sanitizeRRDVersion($param_rrdver, $default_version = '') {
		$rrdver = $default_version;
		if (isset($param_rrdver) && strlen($param_rrdver)) {
			log_install_debug('rrdversion', 'sanitizeRRDVersion() - Checking for version string');
			if (preg_match("/(?:version|v)*\s*((?:[0-9]+\.?)+)/i", $param_rrdver, $matches)) {
				log_install_debug('rrdversion', 'sanitizeRRDVersion() - Checking for version string - ' . (cacti_sizeof($matches) - 1) . ' matches found');
				if (cacti_sizeof($matches) > 1) {
					log_install_debug('rrdversion', 'sanitizeRRDVersion() - Comparing ' . $param_rrdver . ' <= 1.3');
					if (cacti_version_compare('1.3', $matches[1], '<=')) {
						$rrdver = $matches[1];
						log_install_debug('rrdversion', 'sanitizeRRDVersion() - Valid version');
					}
				}
			}
		} elseif (!empty($default_version)) {
			$rrdver = $this->sanitizeRRDVersion($default_version, '1.3');
		}

		log_install_medium('rrdversion', 'sanitizeRRDVersion() - returning ' . $rrdver);
		return $rrdver;
	}

	/******************************************************************
	 *                                                                *
	 * Setters/Getters                                                *
	 * ---------------                                                *
	 *                                                                *
	 * This section contains various setXXX and getXXX functions      *
	 * to prime the installer.  Most of these functions are private   *
	 * to prevent external usage which must come via the installData  *
	 * parameter when creating a new Installer class object           *
	 *                                                                *
	 ******************************************************************/

	/* getPermissions() - gets the permissions for folders that we require
	 *                    to be available for writing during install or
	 *                    always (after install) */
	private function getPermissions() {
		global $config;

		$permissions = array('install' => array(), 'always' => array());

		$install_paths = array(
			$config['base_path'] . '/resource/snmp_queries',
			$config['base_path'] . '/resource/script_server',
			$config['base_path'] . '/resource/script_queries',
			$config['base_path'] . '/scripts',
		);

		$always_paths = array(
			sys_get_temp_dir(),
			$config['base_path'] . '/log',
			$config['base_path'] . '/cache/boost',
			$config['base_path'] . '/cache/mibcache',
			$config['base_path'] . '/cache/realtime',
			$config['base_path'] . '/cache/spikekill'
		);

		$csrf_paths = array();

		$install_key = 'always';
		if ($this->mode != Installer::MODE_POLLER) {
			$install_key = 'install';

			if (!empty($config['path_csrf_secret'])) {
				$csrf_paths[] = $config['path_csrf_secret'];
			}
		}

		foreach ($install_paths as $path) {
			if (is_dir($path)) {
				$valid = (is_resource_writable($path . '/'));
				$permissions[$install_key][$path . '/'] = $valid;
			} else {
				$valid = (is_resource_writable($path));
				$permissions[$install_key][$path] = $valid;
			}
			log_install_debug('permission', "$path = $valid ($install_key)");
			if (!$valid) {
				$this->addError(Installer::STEP_PERMISSION_CHECK, 'Permission', $path, __('Path was not writable'));
			}
		}

		foreach ($always_paths as $path) {
			$valid = (is_resource_writable($path . '/'));
			$permissions['always'][$path . '/'] = $valid;
			log_install_debug('permission', "$path = $valid (always)");
			if (!$valid) {
				$this->addError(Installer::STEP_PERMISSION_CHECK, 'Permission', $path, __('Path is not writable'));
			}
		}

		foreach ($csrf_paths as $path) {
			if (is_dir($path)) {
				$path .= '/csrf-secret.php';
			}

			$valid = file_exists($path);
			if ($valid) {
				$csrf_secret = file_get_contents($path);
				$valid = !empty($csrf_secret);
			}

			if (!$valid) {
				$valid = (is_resource_writable($path));
			}
			$permissions['install'][$path] = $valid;

			log_install_debug('permission', "$path = $valid (csrf)");
			if (!$valid) {
				$this->addError(Installer::STEP_PERMISSION_CHECK, 'Permission', $path, __('Path was not writable'));
			}
		}

		return $permissions;
	}

	/* setRuntime() - sets the runtime mode of the Installer
	 * @param_runtime - Default is 'unknown', acceptable modes are 'Cli'
	 *                  and 'Json' */
	public function setRuntime($param_runtime = 'unknown') {
		if ($param_runtime == 'Web' || $param_runtime == 'Cli' || $param_runtime == 'Json') {
			$this->runtime = $param_runtime;
		} else {
			$this->addError(Installer::STEP_WELCOME, '', 'Failed to apply specified runtime');
		}
	}

	private function getLanguage() {
		$language = read_config_option('install_language');
		$section = 'install';

		if (empty($language)) {
			$language = read_config_option('i18n_default_language');
			$section = 'i18n';
			if (empty($language) && isset($_SESSION[SESS_USER_ID])) {
				$language = read_user_setting('user_language', get_new_user_default_language(), true);
				$section = 'user';
			}
		}
		log_install_medium('language', 'getLanguage(): ' . $language . ' [' . $section . ']');
		return $language;
	}

	/* setLanguage() - sets the langauge of the Installer
	 * @param_language - Must be a valid language which is returned from
	 *                   apply_locale() function located in Core
	 *
	 * Errors: will add an error at STEP_WELCOME if invalid language */
	private function setLanguage($param_language = '') {
		if (isset($param_language) && strlen($param_language)) {
			$language = apply_locale($param_language);
			if (empty($language)) {
				$this->addError(Installer::STEP_WELCOME, 'Language', 'Failed to apply specified language');
			} else {
				log_install_debug('language', 'setLanguage(): ' . $param_language);
				$this->language = $param_language;
				set_config_option('i18n_default_language', $param_language);
				set_config_option('install_language', $param_language);
				if (isset($_SESSION[SESS_USER_ID])) {
					$_SESSION[SESS_USER_LANGUAGE] = $param_language;
					set_user_setting('user_language', $param_language);
				}

				$test_i18n = read_config_option('i18n_default_language');
				$test_install = read_config_option('install_language');
				log_install_debug('language','setLanguage(): ' . $test_i18n . ' / ' . $test_install);
			}
		}
	}

	/* setEula() - sets whether the Eula was accepted or not
	 * @param_eula - valid values are 'Accepted', 'True'
	 *
	 * Errors: will add an error at STEP_WELCOME if not accepted */
	private function setEula($param_eula = '') {
		if ($param_eula == 'Accepted' || $param_eula === 'true') {
			$param_eula = 1;
		} else	if (!is_numeric($param_eula)) {
			$param_eula = 0;
		}

		$this->eula = ($param_eula > 0);
		if (!$this->eula) {
			$this->addError(Installer::STEP_WELCOME, 'Eula', 'Eula not accepted');
		}
		set_config_option('install_eula', $this->eula);
	}

	/* getRRDVersion() - gets the RRDVersion from the system or if overridden
	 *                  during the installer, from the installer option */
	private function getRRDVersion() {
		$rrdver = read_config_option('install_rrdtool_version');
		if (empty($rrdver)) {
			//			log_install_high('rrdversion', 'getRRDVersion(): Getting tool version');
			//			$rrdver = get_rrdtool_version();
			//			if (empty($rrdver)) {
			log_install_high('rrdversion', 'getRRDVersion(): Getting installed tool version');
			$rrdver = get_installed_rrdtool_version();
			//			}
		}
		log_install_medium('rrdversion', 'getRRDVersion(): ' . $rrdver);
		return $rrdver;
	}

	/* setCSRFSecret() - Initializes the csrf secret file for csrf protection */
	private function setCSRFSecret() {
		global $config;

		$this->setProgress(Installer::PROGRESS_CSRF_BEGIN);

		if (!empty($config['path_csrf_secret'])) {
			$path_csrf_secret = $config['path_csrf_secret'];
			log_install_debug('csrf', 'setCSRFSecret(): secret ' . $path_csrf_secret);

			$secret = @file_exists($path_csrf_secret) ? file_get_contents($path_csrf_secret) : '';
			log_install_debug('csrf', 'setCSRFSecret(): secret ' . (empty($secret) ? 'not ' : '') . 'empty');

			if (empty($secret)) {
				if (is_resource_writable($path_csrf_secret)) {
					log_install_medium('csrf', 'setCSRFSecret(): Updated CSRF secret - "' . $path_csrf_secret . '"');
					install_create_csrf_secret($path_csrf_secret);
				} else {
					log_install_high('csrf', 'setCSRFSecret(): Unable to create file - "' . $path_csrf_secret . '"');
				}
			} else {
				log_install_debug('csrf', 'setCSRFSecret(): Secret already exists - "' . $path_csrf_secret . '"');
			}
		}

		$this->setProgress(Installer::PROGRESS_CSRF_END);
	}

	/* setRRDVersion() - sets the RRDVersion installer option, overrides
	 *                 - system default.
	 * @param_rrdver - a valid version number.
	 * @prefix       - a display prefix, not used in values
	 *
	 * Errors: will add an error at STEP_BINARY_LOCATIONS if invalid version
	 *         was detected */
	private function setRRDVersion($param_rrdver = '', $prefix = '') {
		global $config;
		if (isset($param_rrdver) && strlen($param_rrdver)) {
			$rrdver = $this->sanitizeRRDVersion($param_rrdver, '');
			if (empty($rrdver)) {
				$this->addError(Installer::STEP_BINARY_LOCATIONS, 'RRDVersion', 'setRRDVersion()', __('Failed to set specified %sRRDTool version: %s', $prefix, $param_rrdver));
			} else {
				$this->paths['rrdtool_version']['default'] = $param_rrdver;
				set_config_option('install_rrdtool_version', $param_rrdver);
				set_config_option('rrdtool_version', $param_rrdver);
			}
		}
	}

	/* getTheme() - gets the current theme */
	private function getTheme() {
		$theme = read_config_option('install_theme');
		if (empty($theme)) {
			if (isset($_SESSION[SESS_USER_ID])) {
				$theme = read_user_setting('selected_theme');
			}

			if (empty($theme)) {
				$theme = read_config_option('selected_theme');
			}
		}
		$theme = empty($theme) ? 'modern' : $theme;
		log_install_medium('theme', 'getTheme(): ' . $theme);
		return $theme;
	}

	/* setTheme() - sets the Theme installer option, override the system default.
	 * @param_theme - a valid theme which must exist in /include/themes/
	 *
	 * Errors: will add an error at STEP_BINARY_WELCOME if invalid theme
	 *         was detected */
	private function setTheme($param_theme = '') {
		global $config;
		if (isset($param_theme) && strlen($param_theme)) {
			log_install_medium('theme', 'Checking theme: ' . $param_theme);
			$themePath = $config['base_path'] . '/include/themes/' . $param_theme . '/main.css';
			if (file_exists($themePath)) {
				log_install_debug('theme', 'setTheme(): ' . $param_theme);
				$this->theme = $param_theme;
				set_config_option('install_theme', $this->theme);
				set_config_option('selected_theme', $this->theme);
				if (isset($_SESSION[SESS_USER_ID])) {
					set_user_setting('selected_theme', $this->theme);
					$_SESSION['selected_theme'] = $this->theme;
				}
			} else {
				$this->addError(Installer::STEP_WELCOME, 'Theme', __('Invalid Theme Specified'));
			}
		}
	}

	/* getPaths() - gets the various programs/paths to that are defined in
	 *              $this->paths (setup in constructor) where they are
         *              either prefixed with path_ or specifically the sendmail
	 *              path.  If a default exists, that is usd, otherwise the
	 *              system configuration option is read and set back into
	 *		$this->paths array */
	public function getPaths() {
		$paths = array();
		foreach ($this->paths as $name => $array) {
			if (substr($name, 0, 5) == 'path_' || $name == "settings_sendmail_path") {
				if (array_key_exists('default', $array)) {
					$paths[$name] = $array['default'];
				} else {
					$paths[$name] = read_config_option($name);
				}
			}
		}
		return $paths;
	}

	/* setPaths() - sets paths set in the array, checking for a number of
	 *              issues.  The array should be key->path which is compared
	 *              against $this->paths
	 *
	 * Errors: will add an error to STEP_BINARY_LOCATIONS if a problem is
	 *         found with the value. */
	private function setPaths($param_paths = array()) {
		global $config;

		if (is_array($param_paths)) {
			log_install_debug('paths', 'setPaths(' . $this->stepCurrent . ', ' . cacti_count($param_paths) . ')');

			/* get all items on the form and write values for them  */
			foreach ($param_paths as $name => $path) {
				$key_exists = array_key_exists($name, $this->paths);
				$check = isset($this->paths[$name]['install_check']) ? $this->paths[$name]['install_check'] : 'file_exists';
				$optional = isset($this->paths[$name]['install_optional']) ? $this->paths[$name]['install_optional'] : false;
				$blank = isset($this->paths[$name]['install_blank']) ? $this->paths[$name]['install_blank'] : false;
				log_install_high('paths', sprintf('setPaths(): name: %-25s, key_exists: %-5s, optional: %-5s, check: %s, path: %s', $name, $key_exists, $optional, $check, $path));
				if ($key_exists) {
					$should_set = true;
					if ($blank && empty($path)) {
						// allow this
					} elseif ($check == 'writable') {
						$should_set = is_resource_writable(dirname($path) . '/') || $optional;
						if ($should_set) {
							$should_set = is_resource_writable($path) || $optional;
						}
						if (!$should_set && !$optional) {
							$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('Resource is not writable'));
						}
					} elseif ($check == 'file_exists') {
						$should_set = (!empty($path) && file_exists($path)) || $optional;
						if (!$should_set) {
							$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('File not found'));
						}
					}

					if ($should_set && $name == 'path_php_binary') {
						$input = mt_rand(2, 64);
						$output = shell_exec(
							cacti_escapeshellcmd($path) . ' -q ' .
								cacti_escapeshellarg($config['base_path'] .  '/install/cli_test.php') .
							' ' . $input
						);

						if ($output != $input * $input) {
							$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('PHP did not return expected result'));
							$should_set = false;
						}
					}

					if ($should_set) {
						unset($this->errors['Paths'][$name]);
						set_config_option($name, empty($path) ? '' : $path);
					}

					$this->paths[$name]['default'] = $path;
					log_install_debug('paths', sprintf('setPaths(): name: %-25s, key_exists: %-5s, optional: %-5s, should_set: %3s, check: %s', $name, $key_exists, $optional, $should_set ? 'Yes' : 'No', $check));
					log_install_debug('paths', sprintf('setPaths(): name: %-25s, data: %s', $name, clean_up_lines(var_export($this->paths[$name], true))));
				} else {
					$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('Unexpected path parameter'));
				}
			}
		}
	}

	/* getProfile() - gets the data source profile to be used as the system
	 *                default once installation has been completed.  It is
	 *		  also used by the package installation to attribute
	 *                the installed packages to this collector */
	private function getProfile() {
		$db_profile = read_config_option('install_profile', true);

		if (empty($db_profile) && db_table_exists('data_source_profiles')) {
			$db_profile = db_fetch_cell('SELECT id FROM data_source_profiles WHERE `default` = \'on\' LIMIT 1');

			if ($db_profile === false) {
				$db_profile = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY id LIMIT 1');
			}
		}

		log_install_medium('automation', 'getProfile() returns with ' . $db_profile);

		return $db_profile;
	}

	/* setProfile() - sets the data source profile as the default one to be
	 *                be used by the system.
	 * @param_profile - must be an existing data_source_profile id
	 *
	 * Error: will add an error to STEP_PROFILE_AND_AUTOMATION when an
	 *        invalid id is passed */
	private function setProfile($param_profile = null) {
		if (db_table_exists('data_source_profiles')) {
			if (!empty($param_profile)) {
				$valid = db_fetch_cell_prepared('SELECT id FROM data_source_profiles WHERE id = ?', array($param_profile));

				if ($valid === false || $valid != $param_profile) {
					$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Profile', __('Failed to apply specified profile %s != %s', $valid, $param_profile));
				} else {
					$this->profile = $valid;
					set_config_option('install_profile', $valid);
				}
			}
			log_install_medium('automation', "setProfile($param_profile) returns with $this->profile");
		} else {
			$this->profile = 1;
		}
	}

	/* getAutomationMode() - Gets the automation mode option, if not found
	 *                       uses the system default */
	public function getAutomationMode() {
		$enabled = read_config_option('install_automation_mode', true);

		log_install_debug('automation', 'automation_mode: ' . clean_up_lines($enabled));

		if ($enabled == null && db_table_exists('automation_networks')) {
			$row = db_fetch_row('SELECT id, enabled FROM automation_networks LIMIT 1');

			log_install_debug('automation', 'Network data: ' . clean_up_lines(var_export($row, true)));

			$enabled = 0;

			if (!empty($row)) {
				if ($row['enabled'] == 'on') {
					$enabled = 1;
				}
			}
		}

		log_install_medium('automation', "getAutomationMode() returns '$enabled'");

		return $enabled;
	}

	/* setAutomationMode() - sets whether the automation system should
	 *                       be enabled or disabled by default.
	 * @param_mode - must be a valid true or false value
	 *
	 * Errors: will add an error to STEP_PROFILE_AND_AUTOMATION if an
	 *         invalid value is passed */
	private function setAutomationMode($param_mode = null) {
		if ($param_mode != null) {
			if (!$this->setTrueFalse($param_mode, $this->automationMode, 'automation_mode')) {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Automation', 'Mode', __('Failed to apply specified mode: %s', $param_mode));
			}
		}
		log_install_medium('automation', "setAutomationMode($param_mode) returns with $this->automationMode");
	}

	/* getAutomationOverride() - gets whether the automation snmp options
	 *                           are being overridden */
	public function getAutomationOverride() {
		return read_config_option('install_automation_override', true);
	}

	/* setAutomationOverride() - sets whether the extra snmp options are to
	 *                           be overwritten by the SnmpOptions provided
	 *
	 * @param_override - must be a valid true or false value
	 *
	 * Errors: will add an error to STEP_PROFILE_AND_AUTOMATION if an
	 *         invalid value is passed */
	private function setAutomationOverride($param_override = null) {
		if ($param_override != null) {
			if (!$this->setTrueFalse($param_override, $this->automationOverride, 'automation_override')) {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Automation', 'Override', __('Failed to apply specified automation override: %s', $param_override));
			}
		}
		log_install_medium('automation', "setAutomationOverride($param_override) returns with $this->automationOverride");
	}

	/* setCronInterval() - sets the expected system cron interval but does
	 *                     not actually affect the system cron
	 * @param_interval - a value that must exist in the system global
	 *                   variables $cron_intervals
	 *
	 * Errors: will set an error in STEP_PROFILE_AND_AUTOMATION when an
	 *         invalid value is passed */
	private function setCronInterval($param_interval = null) {
		global $cron_intervals;
		if ($param_interval != null) {
			if (array_key_exists($param_interval, $cron_intervals)) {
				$this->cronInterval = $param_interval;
				set_config_option('cron_interval', $param_interval);
			} else {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Poller', 'Cron', __('Failed to apply specified cron interval'));
			}
		}
		log_install_medium('automation', "setCronInterval($param_interval) returns with $this->cronInterval");
	}

	/* getAutomationRange() - get the default network range to be used by
	 *                        Automation for scanning the network.  If no
	 *                        previous value is found, defaults to
	 *                        192.168.1.0/24 */
	public function getAutomationRange() {
		$range = read_config_option('install_automation_range', true);
		if (empty($range) && db_table_exists('automation_networks')) {
			$row = db_fetch_row('SELECT id, subnet_range
				FROM automation_networks
				LIMIT 1');
			$enabled = 0;
			$network = '';
			log_install_debug('automation', "getAutomationRange(): found '" . clean_up_lines(var_export($row, true)));
			if (!empty($row)) {
				$range = $row['subnet_range'];
			}
		}
		$result = empty($range) ? '192.168.0.1/24' : $range;
		log_install_medium('automation', "getAutomationRange() returns '$result'");
		return $result;
	}

	/* setAutomationRange() - sets the network range to be used by
	 *                        Automation when scanning the network
	 * @param_range - a valid network range which is converted and returned
	 *                by cacti_pton().  If the return value is false, it is
	 *                considered invalid
	 *
	 * Errors: will add an error to STEP_PROFILE_AND_AUTOMATION if an
	 *         invalid value is passed */
	private function setAutomationRange($param_range = null) {
		if (!empty($param_range)) {
			$param_array = explode(",", $param_range);

			if (cacti_sizeof($param_array)) {
				foreach ($param_array as $param_network) {
					$ip_details = automation_get_network_info($param_network);

					if ($ip_details === false) {
						$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Automation', 'Range', __('Failed to apply \'%s\' as Automation Range', $param_network));
					}
				}

				$this->automationRange = $param_range;

				set_config_option('install_automation_range', $param_range);
			}
		} else {
			$param_range = '';

			$this->automationRange = $param_range;
		}

		log_install_medium('automation', "setAutomationRange($param_range) returns with $this->automationRange");
	}

	/* getSnmpOptions() - gets an array of all the extra SNMP options to be
	 *                    set which Automation will use when scanning the
	 *                    network */
	private function getSnmpOptions() {
		global $fields_snmp_item_with_retry;
		$db_snmp_options = db_fetch_assoc('SELECT name, value FROM settings where name like \'install_snmp_option_%\'');
		$options = array();
		foreach ($db_snmp_options as $row) {
			$key = str_replace('install_snmp_option_', '', $row['name']);
			$options[$key] = $row['value'];
		}

		log_install_debug('snmp_options', 'Option array: ' . clean_up_lines(var_export($options, true)));
		return $options;
	}

	/* setSnmpOptions() - sets the extra SNMP options to be used by
	 *                    Automation when scanning the network
	 *
	 * Errors: will add an error to STEP_PROFILE_AND_AUTOMATION if an
	 *         invalid value is passed */
	private function setSnmpOptions($param_snmp_options = array()) {
		global $fields_snmp_item_with_retry;
		$known_snmp_options = $fields_snmp_item_with_retry;

		if (is_array($param_snmp_options)) {
			db_execute('DELETE FROM settings WHERE name like \'install_snmp_option_%\'');
			log_install_medium('snmp_options', "Updating snmp_options");
			log_install_debug('snmp_options', "Parameter data:" . clean_up_lines(var_export($param_snmp_options, true)));
			$known_map = array(
				'SnmpVersion'         => 'snmp_version',
				'SnmpCommunity'       => 'snmp_community',
				'SnmpSecurityLevel'   => 'snmp_security_level',
				'SnmpUsername'        => 'snmp_username',
				'SnmpAuthProtocol'    => 'snmp_auth_protocol',
				'SnmpPassword'        => 'snmp_password',
				'SnmpPrivProtocol'    => 'snmp_priv_protocol',
				'SnmpPrivPassphrase'  => 'snmp_priv_passphrase',
				'SnmpContext'         => 'snmp_context',
				'SnmpEngineId'        => 'snmp_engine_id',
				'SnmpPort'            => 'snmp_port',
				'SnmpTimeout'         => 'snmp_timeout',
				'SnmpMaxOids'         => 'max_oids',
				'SnmpRetries'         => 'snmp_retries',
			);

			foreach ($param_snmp_options as $option_name => $option_value) {
				log_install_high('snmp_options', "snmp_option: " . clean_up_lines(var_export($option_name, true)));
				$bad_option = true;
				if (array_key_exists($option_name, $known_map)) {
					$key = $known_map[$option_name];
					log_install_debug('snmp_options', "snmp_option found:" . clean_up_lines(var_export($key, true)));

					if (array_key_exists($key, $known_snmp_options)) {
						$bad_option = false;
						log_install_high('snmp_options', "snmp_option set:" . clean_up_lines(var_export($option_value, true)));
						log_install_debug('snmp_options', "Set snmp_option: install_snmp_option_$key = $option_value");
						set_config_option("install_snmp_option_$key", $option_value);
					}
				}
				if ($bad_option) {
					$this->addError(Installer::STEP_TEMPLATE_INSTALL, 'SnmpOptions', $option_name, __('No matching snmp option exists'));
				}
			}
		}
	}

	/* getModules() - returns a list of required modules and their
	 *                installation status */
	private function getModules() {
		global $config;

		if (isset($this->extensions) || empty($this->extensions)) {
			$extensions = utility_php_extensions();

			foreach ($extensions as $name => $e) {
				if (!$e['installed']) {
					$this->addError(Installer::STEP_CHECK_DEPENDENCIES, 'Modules', $name . ' is missing');
				}
			}

			$this->extensions = $extensions;
		}

		return $this->extensions;
	}

	/* getTemplates() - returns a list of expected templates and whether
	 *                  they have been selected for installation */
	private function getTemplates() {
		$known_templates = install_setup_get_templates();

		if ($known_templates === false) {
			return false;
		}

		$db_templates = array_rekey(
			db_fetch_assoc('SELECT name, value FROM settings WHERE name LIKE \'install_template_%\''),
			'name',
			'value'
		);

		$hasTemplates = read_config_option('install_has_templates', true);
		$selected = array();
		$select_count = 0;
		log_install_debug('templates', 'getTemplates(): First: ' . (empty($hasTemplates) ? 'Yes' : 'No') . ', Templates - ' . clean_up_lines(var_export($known_templates, true)));
		log_install_debug('templates', 'getTemplates(): DB: ' . clean_up_lines(var_export($db_templates, true)));

		foreach ($known_templates as $known) {
			$filename    = $known['filename'];
			$key_base    = str_replace('.', '_', $filename);
			$key_install = 'install_template_' . $key_base;
			$key_check   = 'chk_template_' . $key_base;

			log_install_high('templates', 'getTemplates(): Checking template ' . $known['name'] . ' using base: ' . $key_base);
			log_install_debug('templates', 'getTemplates(): Checking template ' . $known['name'] . ' using key.: ' . $key_install);
			log_install_debug('templates', 'getTemplates(): Checking template ' . $known['name'] . ' filename..: ' . $filename);

			$value = '';
			if (array_key_exists($key_install, $db_templates)) {
				$value = $db_templates[$key_install];
			}

			log_install_debug('templates', 'getTemplates(): Checking template ' . $known['name'] . ' against...: ' . $value);

			$isSelected = !empty($hasTemplates) && ($value == $filename);
			$selected[$key_check] = $isSelected;
			if ($isSelected) {
				$select_count++;
			}
		}

		$selected['all'] = ($select_count == cacti_count($selected) || empty($hasTemplates));

		log_install_high('templates', 'getTemplates(): Returning with ' . clean_up_lines(var_export($selected, true)));

		return $selected;
	}

	/* setTemplates() - sets a list of templates that should be installed
	 *                  during the installServer() phase.
	 * @param_templates - an array of templates to install in the form of
	 *                    'template'=>(true|false)
	 *
	 * Errors: will add an error to STEP_TEMPLATE_INSTALL if a template is
	 *         passed that is not expected */
	private function setTemplates($param_templates = array()) {
		if (is_array($param_templates)) {
			db_execute('DELETE FROM settings WHERE name like \'install_template_%\'');
			$known_templates = install_setup_get_templates();

			log_install_medium('templates', "setTemplates(): Updating templates");
			log_install_debug('templates', "setTemplates(): Parameter data:" . clean_up_lines(var_export($param_templates, true)));
			log_install_debug('templates', "setTemplates(): Template data:" . clean_up_lines(var_export($known_templates, true)));

			$param_all = false;
			if (array_key_exists('all', $param_templates)) {
				$this->setTrueFalse($param_templates['all'], $param_all, 'param_all', false);
				unset($param_templates['all']);
				log_install_debug('templates', "setTemplates(): All flag:" . clean_up_lines(var_export($param_all, true)));
			}

			$count = 0;
			foreach ($param_templates as $name => $enabled) {
				$template = false;

				log_install_high('templates', 'setTemplates(): ' . $name . ' => ' . ($enabled ? 'true' : 'false'));
				foreach ($known_templates as $known) {
					$filename = $known['filename'];
					$key = 'chk_template_' . str_replace('.', '_', $filename);
					if ($name == $key || $name == $filename) {
						$template = $known;
						break;
					}
				}

				if ($template === false) {
					$this->addError(Installer::STEP_TEMPLATE_INSTALL, 'Templates', $name, __('No matching template exists'));
				} else {
					$set = false;
					$key = str_replace('.', '_', $template['filename']);
					$this->setTrueFalse($enabled, $set, $key, false);
					$use = ($set) || ($param_all);
					$value = ($use) ? $template['filename'] : '';
					log_install_high('templates', "setTemplates(): Use: $use, Set: $set, All: $param_all, key: install_template_$key = " . $value);

					// Don't default install templates if upgrade
					if ($this->getMode() == Installer::MODE_DOWNGRADE) {
						$value = '';
						$use   = false;
					}

					set_config_option("install_template_$key", $value);
					$this->templates[$name] = $use;
				}
			}

			$all = true;
			if (array_key_exists('all', $this->templates)) {
				unset($this->templates['all']);
			}

			foreach ($this->templates as $use) {
				if (!$use) {
					$all = false;
				}
			}
			$this->templates['all'] = $all;

			set_config_option('install_has_templates', true);
		}
	}

	/* getTables() - gets a list of tables that require conversion to
	 *               mb4_unicode_utf8 */
	private function getTables() {
		$known_tables = install_setup_get_tables();

		$db_tables = array_rekey(
			db_fetch_assoc('SELECT name, value FROM settings where name like \'install_table_%\''),
			'name',
			'value'
		);

		$hasTables    = read_config_option('install_has_tables', true);
		$selected     = array();
		$select_count = 0;

		foreach ($known_tables as $known) {
			$table  = $known['Name'];
			$key    = $known['Name'];
			$option = '';

			if (array_key_exists('install_table_' . $key, $db_tables)) {
				$option = $db_tables['install_table_' . $key];
			}

			$isSelected = !empty($hasTables) && (!empty($option));
			$selected['chk_table_' . $key] = $isSelected;
			if ($isSelected) {
				$select_count++;
			}
		}

		$selected['all'] = ($select_count == cacti_count($selected) || empty($hasTables));

		return $selected;
	}

	/* setTables - sets a list of tables to be converted to the latest
	 *             default coalition
	 * @param_tables - array of table names
	 *
	 * Errors: does not add errors as a table may not be present in the
	 *         conversion list due to being converted elsewhere */
	private function setTables($param_tables = array()) {
		if (is_array($param_tables)) {
			db_execute('DELETE FROM settings WHERE name like \'install_table_%\'');

			$known_tables = install_setup_get_tables();

			log_install_medium('tables', "setTables(): Updating Tables");
			log_install_debug('tables', "setTables(): Parameter data:" . clean_up_lines(var_export($param_tables, true)));

			$param_all = false;

			if (array_key_exists('all', $param_tables)) {
				$this->setTrueFalse($param_tables['all'], $param_all, 'allTables', false);
				unset($param_tables['all']);
			}

			foreach ($known_tables as $known) {
				$name = $known['Name'];
				$key  = 'chk_table_' . $name;
				$set  = false;

				log_install_high('tables', "setTables(): Checking table '$name' against key $key ...");
				log_install_debug('tables', "setTables(): Table: " . clean_up_lines(var_export($known, true)));

				if (!array_key_exists($key, $param_tables)) {
					$param_tables[$key] = null;
				}

				$this->setTrueFalse($param_tables[$key], $set, 'table_' . $name, false);

				$use   = ($set || $param_all);
				$value = $use ? $name : '';

				log_install_high('tables', "setTables(): Use: $use, Set: $set, All: $param_all, key: install_table_$name = " . $value);

				set_config_option("install_table_$name", $value);

				$this->tables[$key] = $use;
			}

			$all = true;
			if (array_key_exists('all', $this->tables)) {
				unset($this->tables['all']);
			}

			foreach ($this->tables as $use) {
				if (!$use) {
					$all = false;
				}
			}
			$this->tables['all'] = $all;

			set_config_option('install_has_tables', true);
		}
	}

	/* getMode - gets the current mode */
	public function getMode() {
		if (isset($this->mode)) {
			$mode = $this->mode;
		} else {
			$mode = Installer::MODE_INSTALL;
			if ($this->old_cacti_version != 'new_install') {
				if (cacti_version_compare($this->old_cacti_version, CACTI_VERSION, '=')) {
					$equal = '=';
					$mode = Installer::MODE_NONE;
				} elseif (cacti_version_compare($this->old_cacti_version, CACTI_VERSION, '<')) {
					$equal = '<';
					$mode = Installer::MODE_UPGRADE;
				} else {
					$equal = '>=';
					$mode = Installer::MODE_DOWNGRADE;
				}

				log_install_high('mode', 'getMode(): Version Mode - ' . clean_up_lines(var_export($this->old_cacti_version, true))
				. $equal . clean_up_lines(var_export(CACTI_VERSION, true)));
			} elseif ($this->hasRemoteDatabaseInfo()) {
				$mode = Installer::MODE_POLLER;
			}

			if ($mode == Installer::MODE_POLLER || $mode == Installer::MODE_INSTALL) {
				$db_mode = read_config_option('install_mode', true);
				log_install_high('mode', 'getMode(): DB Install mode ' . clean_up_lines(var_export($db_mode, true)));
				if ($db_mode !== false && $db_mode !== null) {
					$mode = $db_mode;
				}
			}
			$this->mode = $mode;
		}
		log_install_high('mode', 'getMode(): ' . clean_up_lines(var_export($mode, true)));

		return $mode;
	}

	/* setMode() - sets the current mode of operation
	 * @param_mode - the mode to set, must be one of the MODE_ constants
	 *
	 * Errors: will add an error when an invalid value is passed */
	private function setMode($param_mode = 0) {
		if (intval($param_mode) > Installer::MODE_NONE && intval($param_mode) <= Installer::MODE_DOWNGRADE) {
			log_install_high('mode', 'setMode(' . $param_mode . ')');
			set_config_option('install_mode', $param_mode);
			$this->mode = $param_mode;
			$this->updateButtons();
		} elseif ($param_mode != 0) {
			$this->addError(Installer::STEP_INSTALL_TYPE, 'mode', 'Failed to apply mode: ' . $param_mode);
		}
	}

	/* getSetDefault() - returns the default step */
	private function getStepDefault() {
		$mode = $this->getMode();
		$step = $mode == Installer::MODE_NONE ? Installer::STEP_COMPLETE : Installer::STEP_WELCOME;
		log_install_medium('step', "getStepDefault(): Resetting to $step as not found ($mode)");
		return $step;
	}

	/* getStep() - returns the current step */
	public function getStep() {
		return $this->stepCurrent;
	}

	/* setStep() - sets the current step
	 * @param_step - must be a valid value as defined by STEP_ constants */
	private function setStep($param_step = -1) {
		$step = Installer::STEP_WELCOME;
		if (empty($param_step)) {
			$param_step = 1;
		}

		if (intval($param_step) > Installer::STEP_NONE && intval($param_step) <= Installer::STEP_ERROR) {
			if ($param_step == Installer::STEP_WELCOME || $step <= Installer::STEP_INSTALL_CONFIRM || $step < $param_step) {
				$step = $param_step;
			}
		}

		if ($step == Installer::STEP_NONE) {
			$step == Installer::STEP_WELCOME;
		}

		log_install_debug('step', 'setStep(): ' . var_export($step, true));

		// Make current step the first if it is unknown
		log_install_high('step', 'setStep(): stepError ' . clean_up_lines(var_export($this->stepError, true)) . ' < ' . clean_up_lines(var_export($step, true)));
		if ($this->stepError !== false && $this->stepError < $step) {
			$step = $this->stepError;
		}

		$this->stepCurrent  = ($step == Installer::STEP_NONE ? Installer::STEP_WELCOME : $step);
		$this->stepPrevious = Installer::STEP_NONE;
		$this->stepNext     = Installer::STEP_NONE;
		if ($step <= Installer::STEP_COMPLETE) {
			$this->stepNext     = ($step >= Installer::STEP_COMPLETE ? Installer::STEP_NONE : $step + 1);
			if ($step >= Installer::STEP_WELCOME) {
				$this->stepPrevious = ($step <= Installer::STEP_WELCOME ? Installer::STEP_NONE : $step - 1);
			}
		}

		set_config_option('install_step', $this->stepCurrent);
		$this->updateButtons();
		set_config_option('install_prev', $this->stepPrevious);
		set_config_option('install_next', $this->stepNext);
	}

	/* Some utility functions */

	public function shouldRedirectToHome() {
		return (cacti_version_compare($this->old_cacti_version, CACTI_VERSION, '='));
	}

	public function shouldExitWithReason() {
		if ($this->isDatabaseEmpty()) {
			return Installer::EXIT_DB_EMPTY;
		} elseif ($this->isDatabaseTooOld()) {
			return Installer::EXIT_DB_OLD;
		}

		return false;
	}

	public function isValidCollation($collation_vars, $charset_vars, $type) {
		$collation_value = '';
		$charset_value   = '';

		if (
			cacti_sizeof($collation_vars) &&
			array_key_exists('collation_' . $type, $collation_vars)
		) {
			$collation_value = $collation_vars['collation_' . $type];
		}

		if (
			cacti_sizeof($charset_vars) &&
			array_key_exists('charset_' . $type, $charset_vars)
		) {
			$charset_value = $charset_vars['charset_' . $type];
		}
		return ($collation_value == 'utf8mb4_unicode_ci' ||
			$charset_value   == 'utf8mb4');
	}

	public function isDatabaseEmpty() {
		return empty($this->old_cacti_version);
	}

	public function isDatabaseTooOld() {
		return preg_match('/^0\.6/', $this->old_cacti_version);
	}

	public function isNewInstall() {
		return ($this->old_cacti_version == 'new_install');
	}

	public function isPre_v0_8_UpgradeNeeded() {
		return version_compare($this->old_cacti_version, '0.8.5a', '<=');
	}

	public function hasRemoteDatabaseInfo() {
		global $rdatabase_default;
		return !empty($rdatabase_default);
	}

	public function isConfigurationWritable() {
		global $config;
		return is_resource_writable($config['base_path'] . '/include/config.php');
	}

	public function isRemoteDatabaseGood() {
		global $rdatabase_default, $rdatabase_username, $rdatabase_hostname, $rdatabase_port;
		return (isset($rdatabase_default) &&
			isset($rdatabase_username) &&
			isset($rdatabase_hostname) &&
			isset($rdatabase_port)) ? true : false;
	}

	public function exitWithReason($reason) {
		global $config;

		switch ($reason) {
			case Installer::EXIT_DB_EMPTY:
				return $this->exitSqlNeeded();
			case Installer::EXIT_DB_OLD:
				return $this->exitDbTooOld();
			default:
				return $this->exitWithUnknownReason($reason);
		}
	}

	private function exitWithUnknownReason($reason) {
		$output  = Installer::sectionTitleError();
		$output .= Installer::sectionNormal(__('The Installer could not proceed due to an unexpected error.'));
		$output .= Installer::sectionNormal(__('Please report this to the Cacti Group.'));
		$output .= Installer::sectionCode(__('Unknown Reason: %s', $reason));
		return $output;
	}

	private function exitDbTooOld() {
		global $database_username, $database_default;
		$output  = Installer::sectionTitleError();
		$output .= Installer::sectionNormal(__('You are attempting to install Cacti %s onto a 0.6.x database. Unfortunately, this can not be performed.', CACTI_VERSION));
		$output .= Installer::sectionNormal(__('To be able continue, you <b>MUST</b> create a new database, import "cacti.sql" into it:', CACTI_VERSION));
		$output .= Installer::sectionCode(sprintf("mysql -u %s -p [new_database] < cacti.sql", $database_username, $database_default));
		$output .= Installer::sectionNormal(__('You <b>MUST</b> then update "include/config.php" to point to the new database.'));
		$output .= Installer::sectionNormal(__('NOTE: Your existing data will not be modified, nor will it or any history be available to the new install'));
		return $output;
	}

	private function exitSqlNeeded() {
		global $config, $database_username, $database_default, $database_password;
		$output  = Installer::sectionTitleError();
		$output .= Installer::sectionNormal(__("You have created a new database, but have not yet imported the 'cacti.sql' file. At the command line, execute the following to continue:"));
		$output .= Installer::sectionCode(sprintf("mysql -u %s -p %s < cacti.sql", $database_username, $database_default));
		$output .= Installer::sectionNormal(__("This error may also be generated if the cacti database user does not have correct permissions on the Cacti database. Please ensure that the Cacti database user has the ability to SELECT, INSERT, DELETE, UPDATE, CREATE, ALTER, DROP, INDEX on the Cacti database."));
		$output .= Installer::sectionNormal(__("You <b>MUST</b> also import MySQL TimeZone information into MySQL and grant the Cacti user SELECT access to the mysql.time_zone_name table"));

		if ($config['cacti_server_os'] == 'unix') {
			$output .= Installer::sectionNormal(__("On Linux/UNIX, run the following as 'root' in a shell:"));
			$output .= Installer::sectionCode(sprintf("mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql"));
		} else {
			$output .= Installer::sectionNormal(__("On Windows, you must follow the instructions here <a target='_blank' href='https://dev.mysql.com/downloads/timezones.html'>Time zone description table</a>.  Once that is complete, you can issue the following command to grant the Cacti user access to the tables:"));
		}

		$output .= Installer::sectionNormal(__("Then run the following within MySQL as an administrator:"));
		$output .= Installer::sectionCode(sprintf("mysql &gt; GRANT SELECT ON mysql.time_zone_name to '%s'@'localhost' IDENTIFIED BY '%s'", $database_username, $database_password));
		return $output;
	}

	/* updateButtons() - update the buttons used by the installer */
	private function updateButtons() {
		if (empty($this->buttonNext)) {
			$this->buttonNext = new InstallerButton();
		}

		if (empty($this->buttonPrevious)) {
			$this->buttonPrevious = new InstallerButton();
		}

		if (empty($this->buttonTest)) {
			$this->buttonTest = new InstallerButton();
			$this->buttonTest->Enabled = false;
			$this->buttonTest->Text    = __('Test Connection');
		}

		$this->buttonNext->Text     = __('Next');
		$this->buttonPrevious->Text = __('Previous');

		$this->buttonTest->Visible = false;
		$this->buttonTest->setStep(Installer::STEP_TEST_REMOTE);

		switch ($this->stepCurrent) {
			case Installer::STEP_WELCOME:
				$this->buttonNext->Text = __('Begin');
				break;

			case Installer::STEP_INSTALL_CONFIRM:
				/* checkdependencies - send to install/upgrade */
				if ($this->mode == Installer::MODE_UPGRADE) {
					$this->buttonNext->Text = __('Upgrade');
				} elseif ($this->mode == Installer::MODE_DOWNGRADE) {
					$this->buttonNext->Text = __('Downgrade');
				}
				break;

			case Installer::STEP_PERMISSION_CHECK:
				switch ($this->mode) {
					case Installer::MODE_UPGRADE:
					case Installer::MODE_DOWNGRADE:
						$this->stepNext = Installer::STEP_TEMPLATE_INSTALL;
						break;
				}

				break;

			case Installer::STEP_BINARY_LOCATIONS:
				switch ($this->mode) {
					case Installer::MODE_POLLER:
						$this->stepNext = Installer::STEP_CHECK_TABLES;
						break;
				}
				break;

			case Installer::STEP_CHECK_TABLES:
				switch ($this->mode) {
					case Installer::MODE_POLLER:
						$this->stepPrevious = Installer::STEP_BINARY_LOCATIONS;
						break;

					case Installer::MODE_UPGRADE:
					case Installer::MODE_DOWNGRADE:
						$this->stepPrevious = Installer::STEP_PERMISSION_CHECK;
						break;
				}
				break;

			case Installer::STEP_INSTALL_CONFIRM:
				/* upgrade - if user upgrades send to settings check */
				if ($this->isPre_v0_8_UpgradeNeeded()) {
					/* upgrade - if user runs old version send to upgrade-oldversion */
					$this->stepNext = Installer::STEP_INSTALL_OLDVERSION;
				} else {
					$this->stepNext = Installer::STEP_INSTALL;
				}
				break;

			case Installer::STEP_COMPLETE:
				$this->stepPrevious = Installer::STEP_NONE;
				break;
		}

		$this->buttonNext->setStep($this->stepNext);
		$this->buttonPrevious->setStep($this->stepPrevious);
	}

	/**************************************************
	 * The following sections of code are all related
	 * to the installation process of the current step
	 **************************************************/
	public function processCurrentStep() {
		$exitReason = $this->shouldExitWithReason();
		if ($exitReason !== false) {
			$this->buttonNext->Enabled = false;
			$this->buttonPrevious->Enabled = false;
			$this->buttonTest->Enabled = false;
			return $this->exitWithReason($exitReason);
		}

		switch ($this->stepCurrent) {
			case Installer::STEP_WELCOME:
				return $this->processStepWelcome();
			case Installer::STEP_CHECK_DEPENDENCIES:
				return $this->processStepCheckDependencies();
			case Installer::STEP_INSTALL_TYPE:
				return $this->processStepMode();
			case Installer::STEP_BINARY_LOCATIONS:
				return $this->processStepBinaryLocations();
			case Installer::STEP_PERMISSION_CHECK:
				return $this->processStepPermissionCheck();
			case Installer::STEP_INPUT_VALIDATION:
				return $this->processStepInputValidation();
			case Installer::STEP_PROFILE_AND_AUTOMATION:
				return $this->processStepProfileAndAutomation();
			case Installer::STEP_TEMPLATE_INSTALL:
				return $this->processStepTemplateInstall();
			case Installer::STEP_CHECK_TABLES:
				return $this->processStepCheckTables();
			case Installer::STEP_INSTALL_CONFIRM:
				return $this->processStepInstallConfirm();
			case Installer::STEP_INSTALL:
				return $this->processStepInstall();
			case Installer::STEP_ERROR:
			case Installer::STEP_COMPLETE:
				return $this->processStepComplete();
		}

		return $this->exitWithReason((0 - $this->stepCurrent));
	}

	public function processStepWelcome() {
		global $config, $cacti_version_codes;

		$output  = Installer::sectionTitle(__('Cacti Version') . ' ' . CACTI_VERSION_BRIEF . ' - ' . __('License Agreement'));

		if (!array_key_exists(CACTI_VERSION, $cacti_version_codes)) {
			$output .= Installer::sectionError(__('This version of Cacti (%s) does not appear to have a valid version code, please contact the Cacti Development Team to ensure this is corrected.  If you are seeing this error in a release, please raise a report immediately on GitHub', CACTI_VERSION));
		}

		$output .= Installer::sectionNormal(__('Thanks for taking the time to download and install Cacti, the complete graphing solution for your network. Before you can start making cool graphs, there are a few pieces of data that Cacti needs to know.'));
		$output .= Installer::sectionNormal(__('Make sure you have read and followed the required steps needed to install Cacti before continuing. Install information can be found for <a href="%1$s">Unix</a> and <a href="%2$s">Win32</a>-based operating systems.', '../docs/html/install_unix.html', '../docs/html/install_windows.html'));

		if ($this->mode == Installer::MODE_UPGRADE) {
			$output .= Installer::sectionNote(__('This process will guide you through the steps for upgrading from version \'%s\'. ', $this->old_cacti_version));
			$output .= Installer::sectionNormal(__('Also, if this is an upgrade, be sure to read the <a href="%s">Upgrade</a> information file.', '../docs/html/upgrade.html'));
		}

		if ($this->mode == Installer::MODE_DOWNGRADE) {
			$output .= Installer::sectionNote(__('It is NOT recommended to downgrade as the database structure may be inconsistent'));
		}

		$output .= Installer::sectionNormal(__('Cacti is licensed under the GNU General Public License, you must agree to its provisions before continuing:'));

		$output .= Installer::sectionCode(
			__('This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.') . '<br/><br/>' .
			__('This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.')
		);

		$langOutput = '<select id=\'language\' name=\'language\'>';
		foreach ($this->locales as $key => $value) {
			$selected = '';
			$langOutput .= PHP_EOL . $this->language . " == $key [$value]" . PHP_EOL;
			if ($this->language == $key) {
				$selected = ' selected';
			}

			$flags = explode("-", $key);
			if (cacti_count($flags) > 1) {
				$flagName = strtolower($flags[1]);
			} else {
				$flagName = strtolower($flags[0]);
			}
			$langOutput .= '<option value=\'' . $key . '\'' . $selected . ' data-class=\'flag-icon-' . $flagName . '\'><span class="flag-icon flag-icon-squared flag-icon-' . $flagName . '"></span>' . $value . '</option>';
		}
		$langOutput .= '</select>';

		$themePath = $config['base_path'] . '/include/themes/';
		$themes = glob($themePath . '*', GLOB_ONLYDIR);
		$themeOutput = '<select id=\'theme\' name=\'theme\'>';
		foreach ($themes as $themeFolder) {
			$theme = substr($themeFolder, strlen($themePath));
			if (file_exists($themePath . $theme . '/main.css')) {
				$selected = '';
				if ($theme == $this->theme) {
					$selected = ' selected';
				}

				$themeOutput .= '<option value=\'' . $theme . '\'' . $selected . '>' . ucfirst($theme) . '</option>';
			}
		}
		$themeOutput .= '</select>';
		$eula = ($this->eula) ? ' checked' : '';
		$output .= Installer::sectionNormal('<span>' . __('Select default theme: ') . $themeOutput . '</span><span style=\'float: right\'><input type=\'checkbox\' id=\'accept\' name=\'accept\'' . $eula . '><label for=\'accept\'>' . __('Accept GPL License Agreement') . '</label></span><span>' . $langOutput . '</span>');

		$this->stepData = array('Eula' => $this->eula, 'Theme' => $this->theme, 'Language' => $this->language);
		$this->buttonNext->Enabled = ($this->eula == 1);

		return $output;
	}

	public function processStepCheckDependencies() {
		global $config;
		global $database_default, $database_username, $database_port;
		global $rdatabase_default, $rdatabase_username, $rdatabase_port;

		cacti_system_zone_set();

		$enabled = array(
			'location'          => DB_STATUS_SUCCESS,
			'php_modules'       => DB_STATUS_SUCCESS,
			'php_optional'      => DB_STATUS_SUCCESS,
			'mysql_timezone'    => DB_STATUS_SUCCESS,
			'mysql_performance' => DB_STATUS_SUCCESS
		);

		$output  = Installer::sectionTitle(__('Pre-installation Checks'));
		$output .= Installer::sectionSubTitle(__('Location checks'), 'location');

		// Get request URI and break into parts
		if ($this->runtime == 'Cli' || $this->runtime == 'Json') {
			$test_request_uri = 'index.php';
		} else {
			$test_request_uri = $_SERVER['REQUEST_URI'];
		}

		$test_request_parts = parse_url($test_request_uri);
		$test_request_path = $test_request_parts['path'];
		$test_request_len = strlen($test_request_parts['path']);

		// Get current script name (filename only)
		$test_script_name = basename($_SERVER['SCRIPT_NAME']);
		$test_script_len = strlen($test_script_name);

		// Get end of the path of URI and see if it's our script
		$test_script_part = substr($test_request_path, $test_request_len - $test_script_len);
		$test_script_result = strcmp($test_script_part, $test_script_name);

		// Assume desired path is the URI
		$test_final_path = $test_request_parts['path'];

		// Script was found in path, so remove it
		if ($test_script_result === 0) {
			$test_final_path = substr($test_final_path, 0, strlen($test_final_path) - $test_script_len);
		}

		// Add the install subfolder to defined path location
		// and check if it matches, if not there will likely be problems
		$test_config_path = $config['url_path'] . 'install/';
		$test_compare_result = strcmp($test_config_path, $test_final_path);

		// The path was not what we expected so print an error
		if ($test_compare_result !== 0) {
			$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Please update config.php with the correct relative URI location of Cacti (url_path).') . '</span>');
			$enabled['location'] = DB_STATUS_ERROR;
		} else {
			$output .= Installer::sectionNormal(__('Your Cacti configuration has the relative correct path (url_path) in config.php.'));
		}

		$output .= Installer::sectionSubTitleEnd();

		$recs = utility_php_recommends();

		foreach ($recs as $rec_title => $recommends) {
			if (!empty($recommends)) {
				$output .= Installer::sectionSubTitle(__('PHP - Recommendations (%s)', $rec_title), 'php_recommends_' . $rec_title);

				ob_start();

				html_start_box(__('PHP Recommendations' . ' (' . $recommends[0]['value'] . ')'), '100%', false, '4', '', false);
				html_header(array(__('Name'), __('Current'), __('Recommended'), __('Status'), __('Description')));

				$status = DB_STATUS_SUCCESS;
				if (!cacti_sizeof($recommends)) {
					$recommends = array(
						array(
							'status' => DB_STATUS_ERROR,
							'name' => __('PHP Binary'),
							'current' => read_config_option('path_php_binary', true),
							'value' => '',
							'description' => __('The PHP binary location is not valid and must be updated.'),
						),
						array(
							'status' => DB_STATUS_WARNING,
							'name' => '',
							'current' => '',
							'value' => '',
							'description' => __('Update the path_php_binary value in the settings table.'),
						)
					);
				}

				foreach ($recommends as $recommend) {
					if ($recommend['name'] == 'location') continue;
					if ($recommend['status'] == DB_STATUS_SUCCESS) {
						$status_font = 'green';
						$status_text = __('Passed');
					} elseif ($recommend['status'] == DB_STATUS_WARNING) {
						$status_font = 'orange';
						$status_text = __('Warning');
						if ($status > DB_STATUS_WARNING) {
							$status = DB_STATUS_WARNING;
						}
					} elseif ($recommend['status'] == DB_STATUS_RESTART) {
						$status_font = 'orange';
						$status_text = '<span title="' . __('The specificed value appears to be different in the running config versus the INI file.') . '">' . __('Restart Required') . '</span>';
						if ($status > DB_STATUS_RESTART) {
							$status = DB_STATUS_RESTART;
						}
					} else {
						$status_font = 'red';
						$status_text = __('Error');
						if ($status > DB_STATUS_ERROR) {
							$status = DB_STATUS_ERROR;
						}
					}

					form_alternate_row('php_' . $recommend['name'], true);
					form_selectable_cell($recommend['name'], '');
					form_selectable_cell($recommend['current'], '');
					form_selectable_cell('>= ' . $recommend['value'], '');
					form_selectable_cell("<font color='$status_font'>$status_text</font>", '');
					form_selectable_cell($recommend['description'], '');
					form_end_row();
				}

				html_end_box(false);

				$output .= Installer::sectionNormal(ob_get_contents());
				ob_clean();

				$output .= Installer::sectionSubTitleEnd();
				$enabled['php_recommends_' . $rec_title] = $status;
			}
		}

		$output .= Installer::sectionSubTitle(__('PHP - Module Support (Required)'), 'php_modules');
		$output .= Installer::sectionNormal(__('Cacti requires several PHP Modules to be installed to work properly. If any of these are not installed, you will be unable to continue the installation until corrected. In addition, for optimal system performance Cacti should be run with certain MySQL system variables set.  Please follow the MySQL recommendations at your discretion.  Always seek the MySQL documentation if you have any questions.'));

		$output .= Installer::sectionNormal(__('The following PHP extensions are mandatory, and MUST be installed before continuing your Cacti install.'));

		ob_clean();

		html_start_box(__('Required PHP Modules'), '100%', false, '3', '', false);
		html_header(array(__('Name'), __('Required'), __('Installed')));

		$enabled['php_modules'] = DB_STATUS_SUCCESS;

		foreach ($this->modules as $id => $e) {
			form_alternate_row('line' . $id);
			form_selectable_cell($id, '');
			form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
			form_selectable_cell(Installer::formatModuleStatus($e), '');
			form_end_row();

			if (!$e['installed']) {
				$enabled['php_modules'] = DB_STATUS_ERROR;
			}
		}

		html_end_box(false);

		$output .= Installer::sectionNormal(ob_get_contents());
		ob_clean();

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('PHP - Module Support (Optional)'), 'php_optional');

		$output .= Installer::sectionNormal(__('The following PHP extensions are recommended, and should be installed before continuing your Cacti install.  NOTE: If you are planning on supporting SNMPv3 with IPv6, you should not install the php-snmp module at this time.'));

		$ext = utility_php_optionals();

		html_start_box(__('Optional Modules'), '100%', false, '3', '', false);
		html_header(array(__('Name'), __('Optional'), __('Installed')));

		foreach ($ext as $id => $e) {
			form_alternate_row('line' . $id, true);
			form_selectable_cell($id, '');
			form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
			form_selectable_cell(Installer::formatModuleStatus($e, 'orange'), '');
			form_end_row();

			if (!$e['installed']) {
				$enabled['php_optional'] = DB_STATUS_WARNING;
			}
		}

		html_end_box();

		$output .= Installer::sectionNormal(ob_get_contents());
		ob_clean();

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('MySQL - TimeZone Support'), 'mysql_timezone');
		$mysql_timezone_access = db_fetch_assoc('SHOW COLUMNS FROM mysql.time_zone_name', false);
		if (cacti_sizeof($mysql_timezone_access)) {
			$timezone_populated = db_fetch_cell('SELECT COUNT(*) FROM mysql.time_zone_name');
			if (!$timezone_populated) {
				$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your MySQL TimeZone database is not populated.  Please populate this database before proceeding.') . '</span>');
				$enabled['mysql_timezone'] = DB_STATUS_ERROR;
			}
		} else {
			$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your Cacti database login account does not have access to the MySQL TimeZone database.  Please provide the Cacti database account "select" access to the "time_zone_name" table in the "mysql" database, and populate MySQL\'s TimeZone information before proceeding.') . '</span>');
			$enabled['mysql_timezone'] = DB_STATUS_ERROR;
		}

		if ($enabled['mysql_timezone'] == DB_STATUS_SUCCESS) {
			$output .= Installer::sectionNormal(__('Your Cacti database account has access to the MySQL TimeZone database and that database is populated with global TimeZone information.'));
		}

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('MySQL - Settings'), 'mysql_performance');
		$output .= Installer::sectionNormal(__('These MySQL performance tuning settings will help your Cacti system perform better without issues for a longer time.'));

		html_start_box(__('Recommended MySQL System Variable Settings'), '100%', false, '3', '', false);
		$output_temp = ob_get_contents();
		ob_clean();

		$enabled['mysql_performance'] = utilities_get_mysql_recommendations();

		$output_util = ob_get_contents();
		ob_clean();

		html_end_box(false);

		$output .= Installer::sectionNormal($output_temp . $output_util . ob_get_contents());
		ob_end_clean();

		$output .= Installer::sectionSubTitleEnd();

		$this->stepData = array('Sections' => $enabled);
		$this->buttonNext->Enabled = ($enabled['php_modules'] != DB_STATUS_ERROR) &&
			($enabled['php_recommends_cli'] != DB_STATUS_ERROR) &&
			($enabled['php_recommends_web'] != DB_STATUS_ERROR) &&
			($enabled['mysql_timezone']     != DB_STATUS_ERROR);
		return $output;
	}

	public function processStepMode() {
		global $config;
		global $database_default, $database_username, $database_hostname, $database_port;
		global $rdatabase_default, $rdatabase_username, $rdatabase_hostname, $rdatabase_port;
		// install/upgrade
		$output = Installer::sectionTitle(__('Installation Type'));

		switch ($this->mode) {
			case Installer::MODE_UPGRADE:
				// upgrade detected
				$output .= Installer::sectionSubTitle(__('Upgrade'));
				$output .= Installer::sectionNormal(__('Upgrade from <strong>%s</strong> to <strong>%s</strong>', $this->old_cacti_version, CACTI_VERSION_FULL));

				$output .= Installer::sectionWarning(__('In the event of issues, It is highly recommended that you clear your browser cache, closing then reopening your browser (not just the tab Cacti is on) and retrying, before raising an issue with The Cacti Group'));
				$output .= Installer::sectionNormal(__('On rare occasions, we have had reports from users who experience some minor issues due to changes in the code.  These issues are caused by the browser retaining pre-upgrade code and whilst we have taken steps to minimise the chances of this, it may still occur.  If you need instructions on how to clear your browser cache, <a href=\'https://www.refreshyourcache.com\' target=\'_blank\'>https://www.refreshyourcache.com/</a> is a good starting point.'));
				$output .= Installer::sectionNormal(__('If after clearing your cache and restarting your browser, you still experience issues, please raise the issue with us and we will try to identify the cause of it.'));

				$output .= Installer::sectionSubTitleEnd();
				break;
			case Installer::MODE_DOWNGRADE:
				$output .= Installer::sectionSubTitle(__('Upgrade'));
				$output .= Installer::sectionNormal(__('Downgrade from <strong>%s</strong> to <strong>%s</strong>', $this->old_cacti_version, CACTI_VERSION_FULL));
				$output .= Installer::sectionWarning(__('You appear to be downgrading to a previous version.  Database changes made for the newer version will not be reversed and <i>could</i> cause issues.'));
				$output .= Installer::sectionSubTitleEnd();
				break;
			default:
				// new install
				$output .= Installer::sectionSubTitle(__('Please select the type of installation'));
				$output .= Installer::sectionNormal(__('Installation options:'));

				$output .= Installer::sectionNormal(
					'<ul>' .
						'<li><b><i>' . __('New Primary Server') . '</i></b> - ' . __('Choose this for the Primary site.') . '</li>' .
						'<li><b><i>' . __('New Remote Poller')  . '</i></b> - ' . __('Remote Pollers are used to access networks that are not readily accessible to the Primary site.') . '</li>' .
						'</ul>'
				);

				$selectedInstall = '';
				$selectedPoller = '';

				$sections = array(
					'connection_local' => 0,
					'connection_remote' => 0,
					'error_file' => 0,
					'error_poller' => 0,
					'poller_vars' => 0,
					'poller_steps' => 0,
				);

				$this->buttonNext->Enabled = true;

				switch ($this->mode) {
					case Installer::MODE_POLLER:
						$selectedPoller = ' selected';
						$sections['connection_local'] = 1;
						$sections['connection_remote'] = 1;
						$sections['poller_steps'] = 1;
						$sections['error_file'] = !$this->isConfigurationWritable();
						$sections['error_poller'] = !$this->isRemoteDatabaseGood();

						if ($sections['error_poller']) {
							$sections['poller_vars'] = 1;
							$sections['connection_remote'] = 0;
						}

						if (!($sections['error_file'] || $sections['error_poller'])) {
							$this->buttonNext->Enabled = ($this->mode != Installer::MODE_POLLER);
							$this->buttonTest->Enabled = true;
							$this->buttonTest->Visible = true;
						} else {
							$this->buttonNext->Enabled = false;
							$this->buttonNext->Visible = false;
						}

						break;
					default:
						$selectedInstall = ' selected';
						$sections['connection_local'] = 1;
						break;
				}

				$output .= Installer::sectionNormal(
					'<select id="install_type" name="install_type">' .
					'<option value="1"' . $selectedInstall . '>' . __('New Primary Server') . '</option>' .
					'<option value="2"' . $selectedPoller . '>' . __('New Remote Poller') . '</option>' .
					'</select>'
				);

				$output .= Installer::sectionNormal(__('The following information has been determined from Cacti\'s configuration file. If it is not correct, please edit "include/config.php" before continuing.'));

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Local Database Connection Information'), 'connection_local');

				$output .= Installer::sectionCode(
					__('Database: <b>%s</b>', $database_default) . '<br>' .
					__('Database User: <b>%s</b>', $database_username) . '<br>' .
					__('Database Hostname: <b>%s</b>', $database_hostname) . '<br>' .
					__('Port: <b>%s</b>', $database_port) . '<br>' .
					__('Server Operating System Type: <b>%s</b>', $config['cacti_server_os']) . '<br>'
				);

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Central Database Connection Information'), 'connection_remote');

				$output .= Installer::sectionCode(
					__('Database: <b>%s</b>', $rdatabase_default) . '<br>' .
					__('Database User: <b>%s</b>', $rdatabase_username) . '<br>' .
					__('Database Hostname: <b>%s</b>', $rdatabase_hostname) . '<br>' .
					__('Port: <b>%s</b>', $rdatabase_port) . '<br>' .
					__('Server Operating System Type: <b>%s</b>', $config['cacti_server_os']) . '<br>'
				);

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Configuration Readonly!'), 'error_file');

				$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' . __('Your config.php file must be writable by the web server during install in order to configure the Remote poller.  Once installation is complete, you must set this file to Read Only to prevent possible security issues.') . '</span>');

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Configuration of Poller'), 'error_poller');
				$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' . __('Your Remote Cacti Poller information has not been included in your config.php file.  Please review the config.php.dist, and set the variables: <i>$rdatabase_default, $rdatabase_username</i>, etc.  These variables must be set and point back to your Primary Cacti database server.  Correct this and try again.') . '</span>', 'config_remote');

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Remote Poller Variables'), 'poller_vars');

				$output .= Installer::sectionNormal(__('The variables that must be set in the config.php file include the following:'));
				$output .= Installer::sectionCode(
					'$rdatabase_type     = \'mysql\';<br>' .
					'$rdatabase_default  = \'cacti\';<br>' .
					'$rdatabase_hostname = \'cacti.example.com\'; // Central Cacti server.<br>' .
					'$rdatabase_username = \'cactiuser\';<br>' .
					'$rdatabase_password = \'cactiuser\';<br>' .
					'$rdatabase_port     = \'3306\';<br>' .
					'$rdatabase_ssl      = false;<br>'
				);

				$output .= Installer::sectionNormal(__('The Installer automatically assigns a $poller_id and adds it to the config.php file.'), 'config_remote_poller');

				$output .= Installer::sectionNormal(__('Once the variables are all set in the config.php file, you must also grant the $rdatabase_username access to the main Cacti database server.  Follow the same procedure you would with any other Cacti install.  You may then press the \'Test Connection\' button.  If the test is successful you will be able to proceed and complete the install.'), 'config_remote_var');

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Additional Steps After Installation'), 'poller_steps');

				$output .= Installer::sectionNormal(__('It is essential that the Central Cacti server can communicate via MySQL to each remote Cacti database server.  Once the install is complete, you must edit the Remote Data Collector and ensure the settings are correct.  You can verify using the \'Test Connection\' when editing the Remote Data Collector.'), 'config_remote_db');

				$output .= Installer::sectionSubTitleEnd();

				$this->stepData = array('Sections' => $sections);
				$this->buttonNext->Enabled = ($this->mode != Installer::MODE_POLLER);
				break;
		}

		return $output;
	}

	public function processStepBinaryLocations() {
		$output = Installer::sectionTitle(__('Critical Binary Locations and Versions'));
		$output .= Installer::sectionNormal(__('Make sure all of these values are correct before continuing.'));

		if (!empty($this->errors)) {
			$output .= Installer::sectionWarning(__('One or more paths appear to be incorrect, unable to proceed'));
		}
		$i = 0;

		ob_start();
		print '<div class="cactiTable" style="width:100%;text-align:center">';

		/* find the appropriate value for each 'config name' above by config.php, database,
		 * or a default for fall back */
		$class = 'odd';
		$errors = array();
		if (isset($this->errors) && array_key_exists('Paths', $this->errors)) {
			$errors = $this->errors['Paths'];
		}

		foreach ($this->paths as $name => $array) {
			$class = ($class == 'even' ? 'odd' : 'even');

			$current_value = '';
			if (isset($array['default'])) {
				$current_value = $array['default'];
			}

			log_install_debug('paths', 'processStepBinaryLocations(): Displaying ' . $array['friendly_name'] . ' (' . $name . ' - ' . $class . '): ' . $current_value);

			/* run a check on the path specified only if specified above, then fill a string with
			the results ('FOUND' or 'NOT FOUND') so they can be displayed on the form */
			$form_check_string = '';

			/* draw the actual header and textbox on the form */
			print "<div class='formRow $class'><div class='formColumnLeft'><div class='formFieldName'>" . $array['friendly_name'] . "<div class='formTooltip'><div class='cactiTooltipHint fa fa-question-circle'><span style='display:none;'>" . $array['description'] . "</span></div></div></div></div>";

			print "<div class='formColumnRight'><div class='formData'>";

			$data = array('text' => 'Valid');
			if (array_key_exists($name, $errors)) {
				$data['text'] = $errors[$name];
				$data['error'] = true;
			}

			switch ($array['method']) {
				case 'textbox':
					form_text_box($name, $current_value, '', '', '40', 'text');
					break;
				case 'filepath':
					form_filepath_box($name, $current_value, '', '', '40', 'text', 0, $data);
					break;
				case 'drop_array':
					form_dropdown($name, $array['array'], '', '', $current_value, '', '');
					break;
			}

			/*** Disable output of error for now, pending QA ***
			if (isset($this->errors['Paths'][$name])) {
				print Installer::sectionError(__($this->errors['Paths'][$name]));
			}
			 */

			print '</div></div>';

			print '</div>';

			$i++;
		}
		print '</div>';

		$html = ob_get_contents();
		$output .= Installer::sectionNormal($html);
		ob_end_clean();

		return $output;
	}

	public function processStepPermissionCheck() {
		global $config;

		/* Print message and error logs */
		$output = Installer::sectionTitle(__('Directory Permission Checks'));
		$output .= Installer::sectionNormal(__('Please ensure the directory permissions below are correct before proceeding.  During the install, these directories need to be owned by the Web Server user.  These permission changes are required to allow the Installer to install Device Template packages which include XML and script files that will be placed in these directories.  If you choose not to install the packages, there is an \'install_package.php\' cli script that can be used from the command line after the install is complete.'));

		if ($this->mode == Installer::MODE_INSTALL) {
			$output .= Installer::sectionNormal(__('After the install is complete, you can make some of these directories read only to increase security.'));
		} else {
			$output .= Installer::sectionNormal(__('These directories will be required to stay read writable after the install so that the Cacti remote synchronization process can update them as the Main Cacti Web Site changes'));
		}

		if ($this->mode != Installer::MODE_POLLER) {
			$output .= Installer::sectionNote(__('If you are installing packages, once the packages are installed, you should change the scripts directory back to read only as this presents some exposure to the web site.'));
		} else {
			$output .= Installer::sectionNote(__('For remote pollers, it is critical that the paths that you will be updating frequently, including the plugins, scripts, and resources paths have read/write access as the data collector will have to update these paths from the main web server content.'));
		}

		$sections = array();
		$permissions = '';
		if ($this->mode != Installer::MODE_POLLER) {
			$permissions .= Installer::sectionSubTitle(__('Required Writable at Install Time Only'), 'writable_install');

			$sections['writable_install'] = DB_STATUS_SUCCESS;
			$class = 'even';
			foreach ($this->permissions['install'] as $path => $valid) {
				$class = ($class == 'even' ? 'odd' : 'even');

				/* draw the actual header and textbox on the form */
				$permissions .= "<div class='formRow $class'><div class='formColumnLeft'><div class='formFieldName'>" . $path . "</div></div>";

				$permissions .= "<div class='formColumnRight'><div class='formData' width='100%'>";

				if ($valid) {
					$permissions .=
						'<i class="' . $this->iconClass[DB_STATUS_SUCCESS] . '"></i> ' .
						'<font color="#008000">' . __('Writable') . '</font>';
				} else {
					$permissions .=
						'<i class="' . $this->iconClass[DB_STATUS_ERROR] . '"></i> ' .
						'<font color="#FF0000">' . __('Not Writable') . '</font>';

					$writable = false;
					$sections['writable_install'] = DB_STATUS_ERROR;
				}

				$permissions .= "</div></div></div>";
			}
		}

		$permissions .= Installer::sectionSubTitleEnd();

		$permissions .= Installer::sectionSubTitle(__('Required Writable after Install Complete'), 'writable_always');

		$sections['writable_always'] = DB_STATUS_SUCCESS;

		$class = 'even';
		foreach ($this->permissions['always'] as $path => $valid) {
			$class = ($class == 'even' ? 'odd' : 'even');

			/* draw the actual header and textbox on the form */
			$permissions .= "<div class='formRow $class'><div class='formColumnLeft'><div class='formFieldName'>" . $path . "</div></div>";

			$permissions .= "<div class='formColumnRight'><div class='formData' width='100%'>";

			if ($valid) {
				$permissions .=
					'<i class="' . $this->iconClass[DB_STATUS_SUCCESS] . '"></i> ' .
					'<font color="#008000">' . __('Writable') . '</font>';
			} else {
				$permissions .=
					'<i class="' . $this->iconClass[DB_STATUS_ERROR] . '"></i> ' .
					'<font color="#FF0000">' . __('Not Writable') . '</font>';
				$sections['writable_always'] = DB_STATUS_ERROR;
				$writable = false;
			}

			$permissions .= '</div></div></div>';
		}

		$permissions .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitleEnd();
		$output .= Installer::sectionSubTitle(__('Potential permission issues'), 'host_access');
		$sections['host_access'] = DB_STATUS_SUCCESS;

		/* Print help message for unix and windows if directory is not writable */
		if (isset($writable)) {

			$running_user = get_running_user();

			$sections['host_access'] = DB_STATUS_WARNING;
			$text = __('Please make sure that your webserver has read/write access to the cacti folders that show errors below.');
			$paths = array_merge($this->permissions['install'], $this->permissions['always']);
			if ($config['cacti_server_os'] == 'unix') {
				$text .= '  ' . __('If SELinux is enabled on your server, you can either permanently disable this, or temporarily disable it and then add the appropriate permissions using the SELinux command-line tools.');
				$code = '';
				foreach ($paths as $path => $valid) {
					if (!$valid) {
						$code .= sprintf("chown -R %s.%s %s<br />", $running_user, $running_user, $path);
					}
				}
			} else {
				// NOTE: $code part needs updating with the correct command
				$text .= '  ' . __('The user \'%s\' should have MODIFY permission to enable read/write.', $running_user);
				$code = '';
				foreach ($paths as $path => $valid) {
					if (!$valid) {
						$code = sprintf('icacls %s %s/resource/', $running_user, $config['base_path']);
					}
				}
			}
			$output .= Installer::sectionNormal($text);
			$output .= Installer::sectionNormal(__('An example of how to set folder permissions is shown here, though you may need to adjust this depending on your operating system, user accounts and desired permissions.'));
			$output .= Installer::sectionNote('<span class="cactiInstallSectionCode" style="width: 95%; display: inline-flex;">' . $code . '</span>', '', '', __('EXAMPLE:'));
			$output .= Installer::sectionNote(__('Once installation has completed the CSRF path, should be set to read-only.'));
		} else {
			$output .= Installer::sectionNormal('<font color="#008000">' . __('All folders are writable') . '</font>');
		}

		$output .= Installer::sectionSubTitleEnd();
		$output .= $permissions;

		$this->buttonNext->Enabled = !isset($writable);
		$this->stepData = array('Sections' => $sections);
		return $output;
	}

	public function processStepInputValidation() {
		$output  = Installer::sectionTitle(__('Input Validation Whitelist Protection'));
		$output .= Installer::sectionNormal(__('Cacti Data Input methods that call a script can be exploited in ways that a non-administrator can perform damage to either files owned by the poller account, and in cases where someone runs the Cacti poller as root, can compromise the operating system allowing attackers to exploit your infrastructure.'));
		$output .= Installer::sectionNormal(__('Therefore, several versions ago, Cacti was enhanced to provide Whitelist capabilities on the these types of Data Input Methods.  Though this does secure Cacti more thoroughly, it does increase the amount of work required by the Cacti administrator to import and manage Templates and Packages.'));
		$output .= Installer::sectionNormal(__('The way that the Whitelisting works is that when you first import a Data Input Method, or you re-import a Data Input Method, and the script and or arguments change in any way, the Data Input Method, and all the corresponding Data Sources will be immediatly disabled until the administrator validates that the Data Input Method is valid.'));
		$output .= Installer::sectionNormal(__('To make identifying Data Input Methods in this state, we have provided a validation script in Cacti\'s CLI directory that can be run with the following options:'));

		$output .= Installer::sectionNormal(
			'<ul>' .
				'<li><b><i>php -q input_whitelist.php --audit</i></b> - ' . __('This script option will search for any Data Input Methods that are currently banned and provide details as to why.') . '</li>' .
				'<li><b><i>php -q input_whitelist.php --update</i></b> - ' . __('This script option un-ban the Data Input Methods that are currently banned.') . '</li>' .
				'<li><b><i>php -q input_whitelist.php --push</i></b> - ' . __('This script option will re-enable any disabled Data Sources.') . '</li>' .
				'</ul>'
		);

		$output .= Installer::sectionNormal(__('It is strongly suggested that you update your config.php to enable this feature by uncommenting the <b>$input_whitelist</b> variable and then running the three CLI script options above after the web based install has completed.'));

		$output .= Installer::sectionNormal(__('Check the Checkbox below to acknowledge that you have read and understand this security concern'));

		$output .= Installer::sectionNormal('<input type="checkbox" id="confirm" name="confirm"><label for="confirm">' . __('I have read this statement') . '</label>');

		$this->buttonNext->Enabled = false;
		$this->buttonNext->Step = Installer::STEP_PROFILE_AND_AUTOMATION;

		return $output;
	}

	public function processStepProfileAndAutomation() {
		global $cron_intervals;

		$profiles = db_fetch_assoc('SELECT dsp.id, dsp.name, dsp.default
			FROM data_source_profiles AS dsp
			ORDER BY dsp.step, dsp.name');

		if (cacti_sizeof($profiles)) {
			$output  = Installer::sectionTitle(__('Default Profile'));
			$output .= Installer::sectionNormal(__('Please select the default Data Source Profile to be used for polling sources.  This is the maximum amount of time between scanning devices for information so the lower the polling interval, the more work is placed on the Cacti Server host.  Also, select the intended, or configured Cron interval that you wish to use for Data Collection.'));

			$fields_schedule = array(
				'default_profile' => array(
					'method' => 'drop_sql',
					'friendly_name' => __('Default Profile'),
					'sql' => 'SELECT dsp.id, dsp.name, dsp.default FROM data_source_profiles AS dsp ORDER BY dsp.step, dsp.name',
					'value' => '|arg1:default_profile|',
				),
				'cron_interval' => array(
					'method' => 'drop_array',
					'friendly_name' => __('Cron Interval'),
					'array' => $cron_intervals,
					'value' => '|arg1:cron_interval|',
				)
			);

			ob_start();
			$values = array('default_profile' => $this->profile, 'cron_interval' => $this->cronInterval);

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => inject_form_variables($fields_schedule, $values),
				)
			);

			$html = ob_get_contents();
			ob_end_clean();

			$output .= Installer::sectionNormal($html);

			$output .= Installer::sectionTitle(__('Default Automation Network'));
			$output .= Installer::sectionNormal(__('Cacti can automatically scan the network once installation has completed. This will utilise the network range below to work out the range of IPs that can be scanned.  A predefined set of options are defined for scanning which include using both \'public\' and \'private\' communities.'));
			$output .= Installer::sectionNormal(__('If your devices require a different set of options to be used first, you may define them below and they will be utilized before the defaults'));
			$output .= Installer::sectionNormal(__('All options may be adjusted post installation'));

			global $fields_snmp_item_with_retry;

			$output .= Installer::sectionSubTitle(__('Default Options'));
			$fields_automation = array(
				'automation_mode' => array(
					'method' => 'checkbox',
					'friendly_name' => __('Scan Mode'),
					'value' => '|arg1:automation_mode|',
				),
				'automation_range' => array(
					'method' => 'textbox',
					'friendly_name' => __('Network Range'),
					'value' => '|arg1:automation_range|',
					'max_length' => '100',
				),
				'automation_override' => array(
					'method' => 'checkbox',
					'friendly_name' => __('Additional Defaults'),
					'value' => '|arg1:automation_override|',
				)
			);

			ob_start();
			$values = array(
				'automation_mode'     => $this->automationMode ? 'on' : '',
				'automation_range'    => $this->automationRange,
				'automation_override' => $this->automationOverride ? 'on' : '',
			);

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => inject_form_variables($fields_automation, $values),
				)
			);
			$html = ob_get_contents();

			$output .= Installer::sectionNormal($html);
			$output .= Installer::sectionSubTitle(__('Additional SNMP Options'), 'automation_snmp_options');

			ob_clean();
			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => inject_form_variables($fields_snmp_item_with_retry, $this->snmpOptions),
				)
			);
			$html = ob_get_contents();
			$output .= Installer::sectionNormal($html);
			ob_end_clean();
		} else {
			$output  = Installer::sectionTitleError(__('Error Locating Profiles'));
			$output .= Installer::sectionNormal(__('The installation cannot continue because no profiles could be found.'));
			$output .= Installer::sectionNote(__('This may occur if you have a blank database and have not yet imported the cacti.sql file'));
			$output .= Installer::sectionCode('mysql> source cacti.sql');
		}

		return $output;
	}

	public function processStepTemplateInstall() {
		$output = Installer::sectionTitle(__('Template Setup'));

		if ($this->getMode() == Installer::MODE_UPGRADE) {
			$output .= Installer::sectionNormal(__('Please select the Device Templates that you wish to update during the Upgrade.'));
			$output .= Installer::sectionWarning(__('Updating Templates that you have already made modifications to is not advisable.  The Upgrade of the Templates will NOT remove modifications to Graph and Data Templates, and can lead to unexpected behavior.  However, if you have not made changes to any Graph, Data Query, or Data Template, reimporting the Package should not have any affect.  In that case, you would have to \'Sync Graphs\' to from the Templates after update.'));
		} else {
			$output .= Installer::sectionNormal(__('Please select the Device Templates that you wish to use after the Install.  If you Operating System is Windows, you need to ensure that you select the \'Windows Device\' Template.  If your Operating System is Linux/UNIX, make sure you select the \'Local Linux Machine\' Device Template.'));
		}

		$templates = install_setup_get_templates();
		ob_start();
		html_start_box(__('Templates'), '100%', false, '3', 'center', '', '');
		html_header_checkbox(array(__('Name'), __('Description'), __('Author'), __('Homepage')));
		foreach ($templates as $id => $p) {
			$name = (isset($p['name']) && !empty($p['name'])) ? $p['name'] : '';
			$description = (isset($p['description']) && !empty($p['description'])) ? $p['description'] : '';
			$author = (isset($p['author']) && !empty($p['author'])) ? $p['author'] : '';
			$homepage = (isset($p['homepage']) && !empty($p['homepage'])) ? '<a href="' . $p['homepage'] . '" target=_new>' . $p['homepage'] . '</a>' : '';

			form_alternate_row('line' . $id, true);
			form_selectable_cell($name, $id);
			form_selectable_cell($description, $id);
			form_selectable_cell($author, $id);
			form_selectable_cell($homepage, $id);
			form_checkbox_cell($p['name'], 'template_'  . str_replace(".", "_",  $p['filename']));
			form_end_row();
		}
		html_end_box(false);
		$output .= Installer::sectionNormal(ob_get_contents());
		ob_end_clean();
		$output .= Installer::sectionNormal(__('Device Templates allow you to monitor and graph a vast assortment of data within Cacti.  After you select the desired Device Templates, press \'Next\' and the installation will complete.  Please be patient on this step, as the importation of the Device Templates can take a few minutes.'));

		$this->stepData = array('Templates' => $this->templates);
		return $output;
	}

	public function processStepCheckTables() {
		global $config;
		$output = Installer::sectionTitle(__('Server Collation'));

		$collation_vars = array_rekey(db_fetch_assoc('SHOW VARIABLES LIKE "collation_%";'), 'Variable_name', 'Value');
		$charset_vars   = array_rekey(db_fetch_assoc('SHOW VARIABLES LIKE "character_set_%";'), 'Variable_name', 'Value');

		$collation_valid = $this->isValidCollation($collation_vars, $charset_vars, 'server');
		if ($collation_valid) {
			$output .= Installer::sectionNormal(__('Your server collation appears to be UTF8 compliant'));
		} else {
			$output .= Installer::sectionWarning(
				__('Your server collation does NOT appear to be fully UTF8 compliant. ') .
				__('Under the [mysqld] section, locate the entries named \'character-set-server\' and \'collation-server\' and set them as follows:') .
				Installer::sectionCode('[mysqld]<br>' .
				'character-set-server=utf8mb4<br>' .
				'collation-server=utf8mb4_unicode_ci')
			);
		}

		$output .= Installer::sectionTitle(__('Database Collation'));
		$database = db_fetch_cell('SELECT DATABASE()');

		$collation_valid = $this->isValidCollation($collation_vars, $charset_vars, 'database');
		if ($collation_valid) {
			$output .= Installer::sectionNormal(__('Your database default collation appears to be UTF8 compliant'));
		} else {
			$output .= Installer::sectionWarning(
				__('Your database default collation does NOT appear to be full UTF8 compliant. ') .
				__('Any tables created by plugins may have issues linked against Cacti Core tables if the collation is not matched.   Please ensure your database is changed to \'utf8mb4_unicode_ci\' by running the following: ') .
				Installer::sectionCode(
					'mysql> ALTER DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
				)
			);
		}

		$output .= Installer::sectionTitle(__('Table Setup'));

		$tables = install_setup_get_tables();

		if (cacti_sizeof($tables)) {
			$max_vars = ini_get('max_input_vars');
			if (empty($max_vars)) {
				$max_vars = 1000;
			}

			if ($max_vars < cacti_count($tables) + 10) {
				$output .= Installer::sectionError(__('You have more tables than your PHP configuration will allow us to display/convert.  Please modify the max_input_vars setting in php.ini to a value above %s', cacti_count($tables) + 100));
				$this->buttonNext->Enabled = false;
			} else {
				$output .= Installer::sectionWarning(__('Conversion of tables may take some time especially on larger tables.  The conversion of these tables will occur in the background but will not prevent the installer from completing.  This may slow down some servers if there are not enough resources for MySQL to handle the conversion.'));
				$output .= Installer::sectionNote('max_input_vars: ' . $max_vars . ', tables: ' . cacti_count($tables));
				$show_warning = false;
				ob_start();
				html_start_box(__('Tables'), '100%', false, '3', 'center', '', '');
				html_header_checkbox(array(__('Name'), __('Collation'), __('Row Format'), __('Engine'), __('Rows')));
				foreach ($tables as $id => $p) {
					$enabled = ($p['Rows'] < 1000000 ? true : false);

					$style = ($enabled ? '' : 'text-decoration: line-through;');

					if ($enabled) {
						$cstyle = ($p['Collation'] != 'utf8mb4_unicode_ci' ? 'deviceDown' : $style);
						$estyle = ($p['Engine'] != 'InnoDB' ? 'deviceDown' : $style);
						$rstyle = ($p['Row_format'] != 'Dynamic' ? 'deviceDown' : $style);
					} else {
						$cstyle = $estyle = $rstyle = $style;
					}

					form_alternate_row('line' . $id, true, $enabled);
					form_selectable_cell($p['Name'], $id, '', $style);
					form_selectable_cell($p['Collation'], $id, '', $cstyle);
					form_selectable_cell($p['Row_format'], $id, '', $rstyle);
					form_selectable_cell($p['Engine'], $id, '', $estyle);
					form_selectable_cell($p['Rows'], $id, '', $style);

					if ($enabled) {
						form_checkbox_cell($p['Name'], 'table_'  . $p['Name']);
					} else {
						$show_warning = true;
						form_selectable_cell('', $id, '', $style);
					}
					form_end_row();
				}
				html_end_box(false);

				if ($show_warning) {
					$output .= Installer::sectionWarning(__('One or more tables are too large to convert during the installation.  You should use the cli/convert_tables.php script to perform the conversion, then refresh this page. For example: '));
					$output .= Installer::sectionCode(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/cli/convert_tables.php -u -i');
				}

				$output .= Installer::sectionNormal(__('The following tables should be converted to UTF8 and InnoDB with a Dynamic row format.  Please select the tables that you wish to convert during the installation process.'));
				$output .= Installer::sectionNormal(ob_get_contents());

				ob_end_clean();
			}
		} else {
			$output .= Installer::sectionNormal(__('All your tables appear to be UTF8 and Dynamic row format compliant'));
		}

		$this->stepData = array('Tables' => $this->tables);
		return $output;
	}

	public function processStepInstallConfirm() {
		switch ($this->mode) {
			case Installer::MODE_UPGRADE:
				$title = __('Confirm Upgrade');
				$button = __('Upgrade');
				break;
			case Installer::MODE_DOWNGRADE:
				$title = __('Confirm Downgrade');
				$button = __('Downgrade');
				break;
			default:
				$title = __('Confirm Installation');
				$button = __('Install');
				break;
		}

		if ($this->mode == Installer::MODE_DOWNGRADE) {
			$output = Installer::sectionTitleError(__('DOWNGRADE DETECTED'));
			$output .= Installer::sectionCode(__('YOU MUST MANUALLY CHANGE THE CACTI DATABASE TO REVERT ANY UPGRADE CHANGES THAT HAVE BEEN MADE.<br/>THE INSTALLER HAS NO METHOD TO DO THIS AUTOMATICALLY FOR YOU'));
			$output .= Installer::sectionNormal(__('Downgrading should only be performed when absolutely necessary and doing so may break your installation'));
		} else {
			$output = Installer::sectionTitle($title);
			$output .= Installer::sectionNormal(__('Your Cacti Server is almost ready.  Please check that you are happy to proceed.'));

			$output .= Installer::sectionNote(
				__('Press \'%s\' then click \'%s\' to complete the installation process after selecting your Device Templates.', $title, $button)
			);
		}
		$output .= Installer::sectionNormal('<input type="checkbox" id="confirm" name="confirm"><label for="confirm">' . $title);

		$this->buttonNext->Text = $button;
		$this->buttonNext->Enabled = false;
		$this->buttonNext->Step = Installer::STEP_INSTALL;

		return $output;
	}

	public function processStepInstall() {
		global $config;
		$time = intval(read_config_option('install_updated', true));

		$output  = Installer::sectionTitle(__('Installing Cacti Server v%s', CACTI_VERSION_FULL));
		$output .= Installer::sectionNormal(__('Your Cacti Server is now installing'));
		$output .= Installer::sectionNormal(
			'<table width="100%"><tr>' .
				'<td class="cactiInstallProgressLeft">Refresh in</td>' .
				'<td class="cactiInstallProgressCenter">&nbsp;</td>' .
				'<td class="cactiInstallProgressRight">Progress<span style=\'float:right\'>Last updated: ' . date('H:i:s', $time) . '</span></td>' .
			'</tr><tr>' .
			'<td class="cactiInstallProgressLeft">' .
			'<div id="cactiInstallProgressCountdown"><div></div></div>' .
				'</td>' .
				'<td class="cactiInstallProgressCenter">&nbsp;</td>' .
				'<td class="cactiInstallProgressRight">' .
			'<div id="cactiInstallProgressBar"><div></div></div>' .
				'</td>' .
			'</tr></table>'
		);

		$backgroundTime = read_config_option('install_started', true);
		if (empty($backgroundTime)) {
			$backgroundTime = false;
		}

		$backgroundLast = read_config_option('install_updated', true);
		if (empty($backgroundLast)) {
			$backgroundLast = false;
		}

		$backgroundNeeded = $backgroundTime === false;
		if ($backgroundTime === false) {
			$backgroundTime = microtime(true);

			set_config_option('install_started', $backgroundTime);
		}

		log_install_debug('background', 'backgroundTime = ' . $backgroundTime, 0);
		log_install_debug('background', 'backgroundNeeded = ' . $backgroundNeeded);

		// Check if background started too long ago
		if (!$backgroundNeeded) {
			log_install_debug('background', PHP_EOL . '----------------' . PHP_EOL . 'Check Expire' . PHP_EOL . '----------------');

			$backgroundDateStarted = DateTime::createFromFormat('U.u', $backgroundTime);
			$backgroundLast = read_config_option('install_updated', true);

			log_install_debug('background', 'backgroundDateStarted = ' . $backgroundDateStarted->format('Y-m-d H:i:s') . PHP_EOL);
			log_install_debug('background', 'backgroundLast = ' . $backgroundTime);

			if (empty($backgroundLast) || $backgroundLast < $backgroundTime) {
				log_install_high('background', 'backgroundLast = ' . $backgroundTime . " (Replaced)");
				$backgroundLast = $backgroundTime;
			}

			$backgroundExpire = time() - 1500;
			log_install_debug('background', 'backgroundExpire = ' . $backgroundExpire);

			if ($backgroundLast < $backgroundExpire) {
				$newTime = microtime(true);

				set_config_option('install_started', $newTime);
				set_config_option('install_updated', $newTime);

				$backgroundTime = read_config_option('install_started', true);
				if (empty($backgroundTime)) {
					$backgroundTime = false;
				}

				$backgroundLast = read_config_option('install_updated', true);
				if (empty($backgroundLast)) {
					$backgroundLast = false;
				}

				$backgroundNeeded = ("$newTime" == "$backgroundTime");

				log_install_debug('background', PHP_EOL . '=======' . PHP_EOL . 'Expired' . PHP_EOL . '=======' . PHP_EOL);
				log_install_debug('background', '         newTime = ' . $newTime);
				log_install_debug('background', '  backgroundTime = ' . $backgroundTime);
				log_install_debug('background', '  backgroundLast = ' . $backgroundLast);
				log_install_debug('background', 'backgroundNeeded = ' . $backgroundNeeded);
			}
		}

		if ($backgroundNeeded) {
			$php = cacti_escapeshellcmd(read_config_option('path_php_binary', true));
			$php_file = cacti_escapeshellarg($config['base_path'] . '/install/background.php') . ' ' . $backgroundTime;

			log_install_always('', __('Spawning background process: %s %s', $php, $php_file));
			exec_background($php, $php_file);
		}

		$output .= Installer::getInstallLog();

		$this->buttonNext->Visible = false;
		$this->buttonPrevious->Visible = false;

		$progressCurrent = read_config_option('install_progress', true);
		if (empty($progressCurrent)) {
			$progressCurrent = Installer::PROGRESS_NONE;
		}

		$stepData = array('Current' => $progressCurrent, 'Total' => Installer::PROGRESS_COMPLETE);
		$this->stepData = $stepData;

		return $output;
	}

	public function processStepComplete() {
		global $cacti_version_codes, $database_statuses;

		$cacheFile = read_config_option('install_cache_db', true);

		if ($this->stepCurrent == Installer::STEP_COMPLETE) {
			$output = Installer::sectionTitle(__('Complete'));
			$output .= Installer::sectionNormal(__('Your Cacti Server v%s has been installed/updated.  You may now start using the software.', CACTI_VERSION_FULL));
			db_execute('DELETE FROM settings WHERE name LIKE "install_%"');
		} elseif ($this->stepCurrent == Installer::STEP_ERROR) {
			$output = Installer::sectionTitleError();
			$output .= Installer::sectionNormal(__('Your Cacti Server v%s has been installed/updated with errors', CACTI_VERSION_BRIEF_FULL));
		}

		// Remove integrated plugin references
		api_plugin_uninstall_integrated();

		$output .= Installer::sectionSubTitleEnd();

		$sections = array();
		clearstatcache();
		if ((!empty($cacheFile)) && (is_file($cacheFile))) {
			$cacti_versions = array_keys($cacti_version_codes);

			$sql_text = $database_statuses;

			$sqlclass = array(
				DB_STATUS_ERROR   => 'cactiInstallSqlFailure',
				DB_STATUS_WARNING => 'cactiInstallSqlWarning',
				DB_STATUS_SUCCESS => 'cactiInstallSqlSuccess',
				DB_STATUS_SKIPPED => 'cactiInstallSqlSkipped',
			);

			$file = fopen($cacheFile, "r");
			$sectionId = null;
			$sectionStatus = null;
			if ($file !== false) {
				$version_last = '';
				$line = 0;
				while (!feof($file)) {
					$line++;
					$change = fgets($file);
					if (empty($change)) {
						break;
					}

					$action = preg_split('~[ ]*<\[(version|status|sql|error)\]>[ ]*~i', $change);
					if (empty($action) || cacti_sizeof($action) != 5) {
						log_install_medium('upgrade', $cacheFile . '[' . $line . ']: Read unexpected change - ' . cacti_sizeof($action) . ' - \'' . clean_up_lines(var_export($change, true)) . '\'');
					} else {
						$version = $action[1];
						if (!empty($version)) {
							if ($version != $version_last) {
								$version_last = $version;
								if (!empty($sectionId)) {
									$sections[$sectionId] = $sectionStatus;
									$output .= '</table>';
									$output .= $this->sectionSubTitleEnd();
								}

								$sectionId = str_replace(".", "_", $version);
								$output .= $this->sectionSubTitle('Database Upgrade - Version ' . $version, $sectionId);
								$output .= $this->sectionNormal('The following table lists the status of each upgrade performed on the database');
								$output .= '<table class=\'cactiInstallSqlResults\'>';

								$sectionStatus = DB_STATUS_SKIPPED;
							}

							// show results from version upgrade
							$sql_temp = $action[3];

							if (isset($sqlclass[$action[2]])) {
								$cssClass = $sqlclass[$action[2]];
							} else {
								$cssClass = $action[2];
							}

							$dbIcon = '';
							if (isset($this->iconClass[$action[2]])) {
								$dbIcon = $this->iconClass[$action[2]];
							}

							if (isset($database_statuses[$action[2]])) {
								$dbStatus = $database_statuses[$action[2]];
							} else {
								$dbStatus = 'Unknown ' . $action[2];
							}

							$output .= '<tr class=\'cactiInstallSqlRow\'>';
							$output .= '<td class=\'cactiInstallSqlIcon ' . $cssClass . '\' width=\'50\'><i class=\'' . $dbIcon . '\'></i></td>';
							$output .= '<td class=\'cactiInstallSqlLeft\'>' . $sql_temp . '</td>';
							$output .= '<td class=\'cactiInstallSqlRight ' . $cssClass . '\'>' . $dbStatus . '</td>';
							$output .= '</tr>';

							// set sql failure if status set to zero on any action
							if ($action[2] < $sectionStatus) {
								$sectionStatus = $action[2];
							}
						}
					}
				}

				if (!empty($sectionId)) {
					$output .= '</table>';
					$sections[$sectionId] = $sectionStatus;
				}

				$output .= Installer::sectionSubTitleEnd();

				fclose($file);
			}
		}

		$output .= $this->sectionSubTitle('Process Log');
		$output .= Installer::getInstallLog();

		$this->buttonPrevious->Visible = false;
		$this->buttonNext->Enabled = true;

		if (isset($sections)) {
			$this->stepData = array('Sections' => $sections);
		}

		if ($this->stepCurrent == Installer::STEP_ERROR) {
			$this->buttonPrevious->Text = __('Get Help');
			$this->buttonPrevious->Step = Installer::STEP_GO_FORUMS;
			$this->buttonPrevious->Visible = true;
			$this->buttonPrevious->Enabled = true;

			$this->buttonNext->Text = __('Report Issue');
			$this->buttonNext->Step = Installer::STEP_GO_GITHUB;
		} else {
			$this->buttonNext->Text = __('Get Started');
			$this->buttonNext->Step = Installer::STEP_GO_SITE;
		}

		return $output;
	}

	/*****************************************************************
	 *                                                               *
	 * The following functions perform the leg work for installation *
	 *                                                               *
	 *****************************************************************/

	private function install() {
		global $config;
		$failure = '';

		switch ($this->mode) {
			case Installer::MODE_UPGRADE:
				$which = 'UPGRADE';
				break;
			case Installer::MODE_DOWNGRADE:
				$which = 'DOWNGRADE';
				break;
			default:
				$which = 'INSTALL';
				break;
		}

		log_install_always('', __('Starting %s Process for v%s', $which, CACTI_VERSION_FULL));

		$this->setProgress(Installer::PROGRESS_START);

		$this->setCSRFSecret();

		$this->convertDatabase();

		if ($this->mode == Installer::MODE_POLLER) {
			$failure = $this->installPoller();
		} else {
			if ($this->mode == Installer::MODE_INSTALL) {
				$failure = $this->installTemplate();
				if (empty($failure)) {
					$failure = $this->installServer();
				}
			} elseif ($this->mode == Installer::MODE_UPGRADE) {
				$failure = $this->upgradeDatabase();
				if (empty($failure)) {
					$failure = $this->installTemplate();
				}
			}
			Installer::disableInvalidPlugins();
		}

		log_install_always('', __('Finished %s Process for v%s', $which, CACTI_VERSION_FULL));

		set_config_option('install_error', $failure);

		if (empty($failure)) {
			$this->setProgress(Installer::PROGRESS_VERSION_BEGIN);
			db_execute('TRUNCATE TABLE version');
			db_execute('INSERT INTO version (cacti) VALUES (\'' . CACTI_VERSION . '\');');
			set_config_option('install_version', CACTI_VERSION_FULL);
			$this->setProgress(Installer::PROGRESS_VERSION_END);

			// Sync the remote data collectors
			$this->setProgress(Installer::PROGRESS_COLLECTOR_SYNC_START);
			Installer::fullSyncDataCollectors();
			$this->setProgress(Installer::PROGRESS_COLLECTOR_SYNC_END);

			// No failures so lets update the version
			$this->setProgress(Installer::PROGRESS_COMPLETE);
			$this->setStep(Installer::STEP_COMPLETE);
		} else {
			log_install_always('', $failure);

			$this->setProgress(Installer::PROGRESS_COMPLETE);
			$this->setStep(Installer::STEP_ERROR);
		}
	}

	private function installTemplate() {
		global $config;

		$templates = db_fetch_assoc("SELECT value
			FROM settings
			WHERE name LIKE 'install_template_%'
			AND value <> ''");

		$this->setProgress(Installer::PROGRESS_TEMPLATES_BEGIN);

		if (cacti_sizeof($templates)) {
			log_install_always('', __('Found %s templates to install', cacti_sizeof($templates)));
			$path = $config['base_path'] . '/install/templates/';

			$i = 0;

			foreach ($templates as $template) {
				$i++;
				$result  = false;
				$package = $template['value'];

				log_install_always('', __('About to import Package #%s \'%s\'.', $i, $package));

				if (!empty($package)) {
					set_config_option('install_updated', microtime(true));
					$result = import_package($path . $package, $this->profile, false, false, false, false);

					if ($result !== false) {
						log_install_always('', __('Import of Package #%s \'%s\' under Profile \'%s\' succeeded', $i, $package, $this->profile));
						$this->setProgress(Installer::PROGRESS_TEMPLATES_BEGIN + $i);
					}
				}

				if ($result === false) {
					log_install_always('', __('Import of Package #%s \'%s\' under Profile \'%s\' failed', $i, $package, $this->profile));
					$this->addError(Installer::STEP_ERROR, 'Package:' . $package, 'FAIL: XML version code error');
				}
			}

			// If we are Windows, switch everything to PNG
			if ($config['cacti_server_os'] != 'unix') {
				db_execute('UPDATE graph_templates_graph SET image_format_id = 1');
				set_config_option('default_image_format', '1');
			}

			// Repair automation rules if broken
			repair_automation();

			foreach ($this->defaultAutomation as $item) {
				$host_template_id = db_fetch_cell_prepared(
					'SELECT id
					FROM host_template
					WHERE hash = ?',
					array($item['hash'])
				);

				if (!empty($host_template_id)) {
					log_install_always('', __('Mapping Automation Template for Device Template \'%s\'', $item['name']));

					$exists = db_fetch_cell_prepared(
						'SELECT host_template
						FROM automation_templates
						WHERE host_template = ?',
						array($host_template_id)
					);

					if (empty($exists)) {
						db_execute_prepared(
							'INSERT INTO automation_templates
							(host_template, availability_method, sysDescr, sysName, sysOid, sequence)
							VALUES (?, ?, ?, ?, ?, ?)',
							array(
								$host_template_id, $item['availMethod'], $item['sysDescrMatch'],
								$item['sysNameMatch'], $item['sysOidMatch'], $item['sequence']
							)
						);
					}
				}
			}
		} else {
			log_install_always('', __('No templates were selected for import'));
		}

		$this->setProgress(Installer::PROGRESS_TEMPLATES_END);

		return '';
	}

	private function installPoller() {
		log_install_always('', __('Updating remote configuration file'));
		global $local_db_cnn_id;

		$failure = remote_update_config_file();
		if (empty($failure)) {

			/* change cacti version */
			db_execute('DELETE FROM version', true, $local_db_cnn_id);
			db_execute("INSERT INTO version (cacti) VALUES ('" . CACTI_VERSION_FULL . "')", true, $local_db_cnn_id);

			/* make the poller and poller_output_boost InnoDB */
			db_execute('ALTER TABLE poller_output ENGINE=InnoDB');
			db_execute('ALTER TABLE poller_output_boost ENGINE=InnoDB');
		}
		return $failure;
	}

	private function installServer() {
		global $config;

		$this->setProgress(Installer::PROGRESS_PROFILE_START);

		$profile_id = intval($this->profile);
		$profile = db_fetch_row_prepared(
			'SELECT id, name, step, heartbeat
			FROM data_source_profiles
			WHERE id = ?',
			array($profile_id)
		);

		log_install_high('automation', "Profile ID: $profile_id (" . $this->profile . ") returned " . clean_up_lines(var_export($profile, true)));

		if ($profile['id'] == $this->profile) {
			log_install_always('automation', __('Setting default data source profile to %s (%s)', $profile['name'], $profile['id']));
			$this->setProgress(Installer::PROGRESS_PROFILE_DEFAULT);

			db_execute('UPDATE data_source_profiles
				SET `default` = ""');

			db_execute_prepared(
				'UPDATE data_source_profiles
				SET `default` = \'on\'
				WHERE `id` = ?',
				array($profile['id'])
			);

			db_execute_prepared(
				'UPDATE data_template_data
				SET rrd_step = ?, data_source_profile_id = ?',
				array($profile['step'], $profile['id'])
			);

			db_execute_prepared(
				'UPDATE data_template_rrd
				SET rrd_heartbeat = ?',
				array($profile['heartbeat'])
			);

			$this->setProgress(Installer::PROGRESS_PROFILE_POLLER);
			set_config_option('poller_interval', $profile['step']);
		} else {
			log_install_always('', __('Failed to find selected profile (%s), no changes were made', $profile_id));
		}

		$this->setProgress(Installer::PROGRESS_PROFILE_END);

		$this->setProgress(Installer::PROGRESS_AUTOMATION_START);
		$automation_row = db_fetch_row('SELECT id, enabled, subnet_range FROM automation_networks ORDER BY id LIMIT 1');
		log_install_debug('automation', 'Automation Row:' . clean_up_lines(var_export($automation_row, true)));
		if (!empty($automation_row)) {
			log_install_always('', __(
				'Updating automation network (%s), mode "%s" => "%s", subnet "%s" => %s"',
				$automation_row['id'],
				$automation_row['enabled'],
				$this->automationMode ? 'on' : '',
				$automation_row['subnet_range'],
				$this->automationRange
			));

			db_execute_prepared(
				'UPDATE automation_networks SET
				subnet_range = ?,
				enabled = ?
				WHERE id = ?',
				array($this->automationRange, ($this->automationMode ? 'on' : ''), $automation_row['id'])
			);
		} else {
			log_install_always('', __('Failed to find automation network, no changes were made'));
		}

		if ($this->automationOverride) {
			log_install_always('', __('Adding extra snmp settings for automation'));

			$snmp_options = db_fetch_assoc('select name, value from settings where name like \'install_snmp_option_%\'');
			$snmp_id = db_fetch_cell('select id from automation_snmp_items limit 1');

			if ($snmp_id) {
				log_install_always('', __('Selecting Automation Option Set %s', $snmp_id));

				$save = array('id' => '', 'snmp_id' => $snmp_id);
				foreach ($snmp_options as $snmp_option) {
					$snmp_name = str_replace('install_snmp_option_', '', $snmp_option['name']);
					$snmp_value = $snmp_option['value'];

					if ($snmp_name != 'snmp_security_level') {
						$save[$snmp_name] = $snmp_value;
						set_config_option($snmp_name, $snmp_value);
					}
				}

				log_install_always('', __('Updating Automation Option Set %s', $snmp_id));
				$item_id = sql_save($save, 'automation_snmp_items');
				if ($item_id) {
					log_install_always('', __('Successfully updated Automation Option Set %s', $snmp_id));

					log_install_always('', __('Resequencing Automation Option Set %s', $snmp_id));
					db_execute_prepared(
						'UPDATE automation_snmp_items
							     SET sequence = sequence + 1
							     WHERE snmp_id = ?',
						array($snmp_id)
					);
				} else {
					log_install_always('', __('Failed to updated Automation Option Set %s', $snmp_id));
				}
			} else {
				log_install_always('', __('Failed to find any automation option set'));
			}
		}

		$this->setProgress(Installer::PROGRESS_AUTOMATION_END);

		$this->setProgress(Installer::PROGRESS_DEVICE_START);

		// Add the correct device type
		if ($config['cacti_server_os'] == 'win32') {
			$hash = '5b8300be607dce4f030b026a381b91cd';
			$version      = 2;
			$community    = 'public';
			$avail        = 'snmp';
			$ip           = 'localhost';
			$description  = 'Local Windows Machine';
		} else {
			$hash = '2d3e47f416738c2d22c87c40218cc55e';
			$version      = 0;
			$community    = 'public';
			$avail        = 'none';
			$ip           = 'localhost';
			$description  = 'Local Linux Machine';
		}

		$host_template_id = db_fetch_cell_prepared('SELECT id FROM host_template WHERE hash = ?', array($hash));

		// Add the host
		if (!empty($host_template_id)) {
			$this->setProgress(Installer::PROGRESS_DEVICE_TEMPLATE);
			log_install_always('', __('Device Template for First Cacti Device is %s', $host_template_id));

			$results = shell_exec(cacti_escapeshellcmd(read_config_option('path_php_binary')) . ' -q ' .
				cacti_escapeshellarg($config['base_path'] . '/cli/add_device.php') .
				' --description=' . cacti_escapeshellarg($description) .
				' --ip=' . cacti_escapeshellarg($ip) .
				' --template=' . $host_template_id .
				' --notes=' . cacti_escapeshellarg('Initial Cacti Device') .
				' --poller=1 --site=0 --avail=' . cacti_escapeshellarg($avail) .
				' --version=' . $version .
				' --community=' . cacti_escapeshellarg($community));

			$host_id = db_fetch_cell_prepared(
				'SELECT id
				FROM host
				WHERE host_template_id = ?
				LIMIT 1',
				array($host_template_id)
			);

			if (!empty($host_id)) {
				$this->setProgress(Installer::PROGRESS_DEVICE_GRAPH);
				$templates = db_fetch_assoc_prepared(
					'SELECT *
					FROM host_graph
					WHERE host_id = ?',
					array($host_id)
				);

				if (cacti_sizeof($templates)) {
					log_install_always('', __('Creating Graphs for Default Device'));
					foreach ($templates as $template) {
						set_config_option('install_updated', microtime(true));
						automation_execute_graph_template($host_id, $template['graph_template_id']);
					}

					$this->setProgress(Installer::PROGRESS_DEVICE_TREE);
					log_install_always('', __('Adding Device to Default Tree'));
					shell_exec(cacti_escapeshellcmd(read_config_option('path_php_binary')) . ' -q ' .
						cacti_escapeshellarg($config['base_path'] . '/cli/add_tree.php') .
						' --type=node' .
						' --node-type=host' .
						' --tree-id=1' .
						' --host-id=' . $host_id);
				} else {
					log_install_always('', __('No templated graphs for Default Device were found'));
				}
			}
		} else {
			log_install_always('', __('WARNING: Device Template for your Operating System Not Found.  You will need to import Device Templates or Cacti Packages to monitor your Cacti server.'));
		}

		/* just in case we have hard drive graphs to deal with */
		$host_id = db_fetch_cell("SELECT id FROM host WHERE hostname='127.0.0.1'");

		if (!empty($host_id)) {
			log_install_always('', __('Running first-time data query for local host'));
			run_data_query($host_id, 6);
		}

		/* it's always a good idea to re-populate
		 * the poller cache to make sure everything
		 * is refreshed and up-to-date */
		set_config_option('install_updated', microtime(true));
		log_install_always('', __('Repopulating poller cache'));
		repopulate_poller_cache();

		/* fill up the snmpcache */
		set_config_option('install_updated', microtime(true));
		log_install_always('', __('Repopulating SNMP Agent cache'));
		snmpagent_cache_rebuilt();

		/* generate RSA key pair */
		set_config_option('install_updated', microtime(true));
		log_install_always('', __('Generating RSA Key Pair'));
		rsa_check_keypair();

		$this->setProgress(Installer::PROGRESS_DEVICE_END);
		return '';
	}

	private function convertDatabase() {
		global $config;

		$tables = db_fetch_assoc("SELECT value FROM settings WHERE name like 'install_table_%'");
		if (cacti_sizeof($tables)) {
			log_install_always('', __('Found %s tables to convert', cacti_sizeof($tables)));
			$this->setProgress(Installer::PROGRESS_TABLES_BEGIN);
			$i = 0;
			foreach ($tables as $key => $table) {
				$i++;
				$name = $table['value'];
				if (!empty($name)) {
					log_install_always('', __('Converting Table #%s \'%s\'', $i, $name), true);
					$results = shell_exec(cacti_escapeshellcmd(read_config_option('path_php_binary')) . ' -q ' .
						cacti_escapeshellarg($config['base_path'] . '/cli/convert_tables.php') .
						' --table=' . cacti_escapeshellarg($name) .
						' --utf8 --innodb --dynamic');

					set_config_option('install_updated', microtime(true));
					log_install_debug('convert', sprintf('Convert table #%s \'%s\' results: %s', $i, $name, $results), true);
					if ((stripos($results, 'Converting table') !== false && stripos($results, 'Successful') !== false) ||
						stripos($results, 'Skipped table') !== false
					) {
						set_config_option($key, '');
					}
				}
			}
		} else {
			log_install_always('', __('No tables where found or selected for conversion'));
		}
	}

	private function upgradeDatabase() {
		global $cacti_version_codes, $config, $cacti_upgrade_version, $database_statuses, $database_upgrade_status;
		$failure = DB_STATUS_SKIPPED;

		$cachePrev = read_config_option('install_cache_db', true);
		$cacheFile = tempnam(sys_get_temp_dir(), 'cdu');

		log_install_always('', __('Switched from %s to %s', $cachePrev, $cacheFile));
		set_config_option('install_cache_db', $cacheFile);

		$database_upgrade_status = array('file' => $cacheFile);
		log_install_always('', __('NOTE: Using temporary file for db cache: %s', $cacheFile));

		$prev_cacti_version = format_cacti_version($this->old_cacti_version, CACTI_VERSION_FORMAT_SHORT);
		$orig_cacti_version = format_cacti_version(get_cacti_db_version(), CACTI_VERSION_FORMAT_SHORT);

		// loop through versions from old version to the current, performing updates for each version in the chain
		foreach ($cacti_version_codes as $cacti_upgrade_version => $hash_code) {
			// skip versions old than the database version
			if (cacti_version_compare($this->old_cacti_version, $cacti_upgrade_version, '>=')) {
				//log_install_always('', 'Skipping v' . $cacti_upgrade_version . ' upgrade');
				continue;
			}

			//log_install_always('', 'Checking v' . $cacti_upgrade_version . ' upgrade routines');

			// construct version upgrade include path
			$upgrade_file = $config['base_path'] . '/install/upgrades/' . str_replace('.', '_', $cacti_upgrade_version) . '.php';
			$upgrade_function = 'upgrade_to_' . str_replace('.', '_', $cacti_upgrade_version);

			// check for upgrade version file, then include, check for function and execute
			$ver_status = DB_STATUS_SKIPPED;
			if (file_exists($upgrade_file)) {
				log_install_always('', __('Upgrading from v%s (DB %s) to v%s', $prev_cacti_version, $orig_cacti_version, $cacti_upgrade_version));

				include_once($upgrade_file);
				if (function_exists($upgrade_function)) {
					call_user_func($upgrade_function);
					echo PHP_EOL;
					$ver_status = $this->checkDatabaseUpgrade($cacti_upgrade_version);
				} else {
					log_install_always('', __('WARNING: Failed to find upgrade function for v%s', $cacti_upgrade_version));
					$ver_status = DB_STATUS_WARNING;
				}

				/* Only update database version if database successfully upgraded */
				if ($ver_status != DB_STATUS_ERROR) {
					if (cacti_version_compare($orig_cacti_version, $cacti_upgrade_version, '<')) {
						db_execute("UPDATE version SET cacti = '" . $cacti_upgrade_version . "'");
						$orig_cacti_version = $cacti_upgrade_version;
					}
					$prev_cacti_version = $cacti_upgrade_version;
				}
			}

			if ($failure > $ver_status) {
				$failure = $ver_status;
			}

			if ($failure == DB_STATUS_ERROR) {
				break;
			}
		}

		set_config_option('install_cache_result', $failure);
		if ($failure == DB_STATUS_ERROR) {
			return 'WARNING: One or more upgrades failed to install correctly';
		}

		if (cacti_version_compare($orig_cacti_version, $cacti_upgrade_version, '<=')) {
			db_execute("UPDATE version SET cacti = '" . CACTI_VERSION_FULL . "'");
		}
		return false;
	}

	private function checkDatabaseUpgrade($cacti_upgrade_version) {
		global $database_upgrade_status;
		$failure = DB_STATUS_SKIPPED;

		if (cacti_sizeof($database_upgrade_status)) {
			if (isset($database_upgrade_status[$cacti_upgrade_version])) {
				foreach ($database_upgrade_status[$cacti_upgrade_version] as $cache_item) {
					log_install_debug('dbc', $cacti_upgrade_version . ': ' . clean_up_lines(var_export($cache_item, true)));
					if ($cache_item['status'] < $failure) {
						$failure = $cache_item['status'];
					}

					if ($cache_item['status'] == DB_STATUS_ERROR) {
						$this->addError(Installer::STEP_ERROR, 'DB:' . $cacti_upgrade_version, 'FAIL: ' . $cache_item['sql']);
					}
				}
			}
		}

		return $failure;
	}

	public static function beginInstall($backgroundArg, $installer = null) {
		$eula = read_config_option('install_eula', true);
		if (empty($eula)) {
			log_install_always('', __('Install aborted due to no EULA acceptance'));
			return false;
		}

		$backgroundTime = read_config_option('install_started', true);
		if (empty($backgroundTime)) {
			$backgroundTime = false;
		}

		log_install_high('', "beginInstall(): '$backgroundTime' (time) != '$backgroundArg' (arg) && '-b' != '$backgroundArg' (arg)");

		if ("$backgroundTime" != "$backgroundArg" && "-b" != "$backgroundArg") {
			$dateTime = DateTime::createFromFormat('U.u', $backgroundTime);

			if ($dateTime === false) {
				$dateTime = new DateTime();
			}

			$dateArg = DateTime::createFromFormat('U.u', $backgroundArg);

			if ($dateArg === false) {
				$dateArg = new DateTime();
			}

			$background_error = __(
				'Background was already started at %s, this attempt at %s was skipped',
				$dateTime->format('Y-m-d H:i:s.u'),
				$dateArg->format('Y-m-d H:i:s.u')
			);

			log_install_always('', $background_error);

			if ($installer != null) {
				$installer->addError(Installer::STEP_INSTALL, '', $background_error);
			}

			return false;
		}

		Installer::setPhpOption('max_execution_time', 0);
		Installer::setPhpOption('memory_limit', -1);
		try {
			$backgroundTime = microtime(true);
			if ($installer == null) {
				$installer = new Installer();
			}
			$installer->setDefaults();
			$installer->install();
		} catch (Exception $e) {
			log_install_always('', __('Exception occurred during installation: #%s - %s', $e->getCode(), $e->getMessage()));
		}

		$backgroundDone = microtime(true);
		set_config_option('install_complete', $backgroundDone);

		$dateBack = DateTime::createFromFormat('U.u', $backgroundTime);
		$dateTime = DateTime::createFromFormat('U.u', $backgroundDone);

		log_install_always('', __('Installation was started at %s, completed at %s', $dateBack->format('Y-m-d H:i:s'), $dateTime->format('Y-m-d H:i:s')));
		return true;
	}

	public static function getInstallLog() {
		global $config;

		$page_nr = 1;
		$total_rows = 500;
		$logcontents = tail_file($config['base_path'] . '/log/cacti.log', 100, -1, ' INSTALL:', $page_nr, $total_rows);

		$output_log = '';
		foreach ($logcontents as $logline) {
			$output_log = $logline . '<br/>' . $output_log;
		}

		if (empty($output_log)) {
			$output_log = '--- NO LOG FOUND ---';
		}

		$output = Installer::sectionCode($output_log);
		return $output;
	}

	public static function formatModuleStatus($module, $badColor = 'red') {
		if ($module['installed']) {
			return '<font color=green>' . __('Yes') . '</font>';
		} elseif (!($module['web'] || $module['cli'])) {
			return '<font color=' . $badColor . '>' . __('No - %s', __('Both')) . '</font>';
		} elseif (!$module['web']) {
			return '<font color=orange>' . __('%s - No', __('Web')) . '</font>';
		} elseif (!$module['cli']) {
			return '<font color=orange>' . __('%s - No', __('Cli')) . '</font>';
		}
	}

	public static function setPhpOption($option_name, $option_value) {
		log_install_always('', __('Setting PHP Option %s = %s', $option_name, $option_value));
		$value = ini_get($option_name);
		if ($value != $option_value) {
			ini_set($option_name, $option_value);
			$value = ini_get($option_name);
			if ($value != $option_value) {
				log_install_always('', __('Failed to set PHP option %s, is %s (should be %s)', $option_name, $value, $option_value));
			}
		}
	}

	private static function fullSyncDataCollectorLog($poller_ids, $format) {
		if (cacti_sizeof($poller_ids) > 0) {
			foreach ($poller_ids as $id) {
				$poller = db_fetch_cell_prepared('SELECT name FROM poller WHERE id = ?', array($id));

				log_install_always('sync', __($format, $poller, $id));
			}
		}
	}
	private static function fullSyncDataCollectors() {
		// Perform full sync to complete upgrade
		$status = install_full_sync();

		if ($status['total'] == 0) {
			log_install_always('sync', __('No Remote Data Collectors found for full synchronization'));
		} else {
			Installer::fullSyncDataCollectorLog($status['timeout'], 'Remote Data Collector with name \'%s\' and id %d previous timed out.  Please manually Sync when once online to complete upgrade.');
			Installer::fullSyncDataCollectorLog($status['skipped'], 'Remote Data Collector with name \'%s\' and id %d is not available to sync.  Please manually Sync when once online to complete upgrade.');
			Installer::fullSyncDataCollectorLog($status['failed'], 'Remote Data Collector with name \'%s\' and id %d failed Full Sync.  Please manually Sync when once online to complete upgrade.');
			Installer::fullSyncDataCollectorLog($status['success'], 'Remote Data Collector with name \'%s\' and id %d completed Full Sync.');
		}
	}

	private static function disableInvalidPlugins() {
		global $plugins_integrated, $config;

		foreach ($plugins_integrated as $plugin) {
			if (api_plugin_is_enabled($plugin)) {
				set_config_option('install_updated', microtime(true));
				api_plugin_remove_hooks($plugin);
				api_plugin_remove_realms($plugin);
			}
		}

		$plugins = array_rekey(
			db_fetch_assoc('SELECT directory AS plugin, version
				FROM plugin_config
				WHERE status IN(1,2)'),
			'plugin',
			'version'
		);

		if (cacti_sizeof($plugins)) {
			foreach ($plugins as $plugin => $version) {
				$disable = true;
				$integrated = in_array($plugin, $plugins_integrated);

				set_config_option('install_updated', microtime(true));

				if (
					is_dir($config['base_path'] . '/plugins/' . $plugin)
					&& file_exists($config['base_path'] . "/plugins/$plugin/setup.php")
					&& file_exists($config['base_path'] . "/plugins/$plugin/INFO")
					&& !$integrated
				) {
					$info = parse_ini_file($config['base_path'] . "/plugins/$plugin/INFO", true);
					if (isset($info['info']['compat']) && version_compare(CACTI_VERSION, $info['info']['compat']) > -1) {
						$disable = false;
					}
				}

				if ($disable) {
					if ($integrated) {
						cacti_log("Removing $plugin version $version as it is now integrated with Cacti " . CACTI_VERSION);
					} else {
						cacti_log("Disabling $plugin version $version as it is not compatible with Cacti " . CACTI_VERSION);
					}
					api_plugin_disable_all($plugin);
				}
			}
		}


		foreach ($plugins_integrated as $plugin) {
			if (!api_plugin_is_enabled($plugin)) {
				db_execute_prepared(
					'DELETE FROM plugin_config
					WHERE directory = ?',
					array($plugin)
				);
			}
		}
	}

	/*****************************************************************
	 *                                                               *
	 * The following functions are for rendering output which is     *
	 * returned when $this->Runtime is in Web mode only		 *
	 *                                                               *
	 *****************************************************************/


	public static function sectionTitleError($title = '') {
		if (empty($title)) {
			$title = __('Error');
		}
		return Installer::sectionTitle($title, null, 'cactiInstallSectionTitleError');
	}

	public static function sectionTitle($title = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		return Installer::section($title, $id, $class, 'cactiInstallSectionTitle', 'h2');
	}

	public static function sectionSubTitle($title = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$subtitle  = Installer::section($title, $id, $class, 'cactiInstallSectionTitle', 'h3');
		$subtitle .=  '<div class="installSubSection">';

		return $subtitle;
	}

	public static function sectionSubTitleEnd() {
		return '</div>';
	}

	public static function sectionNormal($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionNormal';

		return Installer::section($text, $id, trim($class), 'cactiInstallSection', 'p');
	}

	public static function sectionNote($text = '', $id = '', $class = '', $title = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		if (empty($title)) {
			$title = 'NOTE:';
		}

		$class .= ' cactiInstallSectionNote';

		return Installer::section('<span class="cactiInstallSectionNoteTitle">' . $title . '</span><span class=\'cactiInstallSectionNoteBody\'>' . $text . '</span>', $id, trim($class), '', 'p');
	}

	public static function sectionWarning($text = '', $id = '', $class = '', $title = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		if (empty($title)) {
			$title = __('WARNING:');
		}

		$class .= ' cactiInstallSectionWarning';

		return Installer::section('<span class="cactiInstallSectionWarningTitle">' . $title . '</span><span class=\'cactiInstallSectionWarningBody\'>' . $text . '</span>', $id, trim($class), '', 'p');
	}

	public static function sectionError($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionError';

		return Installer::section('<span class="cactiInstallSectionErrorTitle">' . __('ERROR:') . '</span><span class=\'cactiInstallSectionErrorBody\'>' . $text . '</span>', $id, trim($class), '', 'p');
	}

	public static function sectionCode($text = '', $id = '', $class = '', $elementType = 'p') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionCode';

		return Installer::section($text, $id, trim($class), '', $elementType);
	}

	public static function section($text = '', $id = '', $class = '', $baseClass = 'cactiInstallSection', $elementType = 'div') {
		if (empty($elementType)) {
			$elementType = 'div';
		}

		$output = '<' . $elementType;
		if (empty($baseClass)) {
			$baseClass = 'cactiInstallSection';
		}

		if (!empty($class)) {
			$baseClass = trim($baseClass . ' ' . $class);
		}

		if (!empty($baseClass)) {
			$output .= ' class=\'' . $baseClass . '\'';
		}

		if (!empty($id)) {
			$output .= ' id=\'' . $id . '\'';
		}

		$output .= '>' . $text . '</' . $elementType . '>';
		return $output;
	}
}

/* InstallerButton class is an internal class that handles the button status
   that appears in the GUI installer */
class InstallerButton implements JsonSerializable {
	public $Text = '';
	public $Step = 0;
	public $Visible = true;
	public $Enabled = true;

	public function __construct($params = array()) {
		if (empty($params) || !is_array($params)) {
			$params = array();
		}

		foreach ($params as $key => $value) {
			switch ($key) {
				case 'Text':
					$this->Text = $value;
					break;
				case 'Step':
					$this->setStep(intval($value));
					break;
				case 'Visible':
					$this->Visible = intval($value);
					break;
				case 'Enabled':
					$this->Enabled = intval($value);
					break;
			}
		}
	}

	public function setStep($step) {
		$this->Step = $step;
		$this->Enabled = !empty($this->Step);
	}

	public function jsonSerialize(): mixed {
		return array(
			'Text' => $this->Text,
			'Step' => $this->Step,
			'Enabled' => $this->Enabled,
			'Visible' => $this->Visible
		);
	}
}
