<?php
include(dirname(__FILE__) . '/../lib/poller.php');

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
	const STEP_PROFILE_AND_AUTOMATION = 6;
	const STEP_TEMPLATE_INSTALL = 7;
	const STEP_CHECK_TABLES = 8;
	const STEP_INSTALL_CONFIRM = 9;
	const STEP_INSTALL_OLDVERSION = 11;
	const STEP_INSTALL = 97;
	const STEP_COMPLETE = 98;
	const STEP_ERROR = 99;

	const MODE_NONE = 0;
	const MODE_INSTALL = 1;
	const MODE_POLLER = 2;
	const MODE_UPGRADE = 3;
	const MODE_DOWNGRADE = 4;

	const PROGRESS_NONE = 0;
	const PROGRESS_START = 1;
	const PROGRESS_UPGRADES_BEGIN = 2;
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
	const PROGRESS_VERSION_BEGIN = 80;
	const PROGRESS_VERSION_END = 85;
	const PROGRESS_COMPLETE = 100;

	private $old_cacti_version;

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

	private $automationMode = null;
	private $automationOverride = null;
	private $buttonNext = null;
	private $buttonPrevious = null;
	private $buttonTest = null;

	public function __construct($install_params = array()) {
		log_install_high('step', 'Install Parameters: ' . clean_up_lines(var_export($install_params, true)));

		$this->old_cacti_version = get_cacti_version();
		$this->setRuntime(isset($install_params['Runtime']) ? $install_params['Runtime'] : 'unknown');

		$step = read_config_option('install_step', true);
		log_install_high('step', 'Initial: ' . clean_up_lines(var_export($step, true)));

		if ($step === false || $step === null) {
			$step = $this->getStepDefault();
		}
		if ($step == Installer::STEP_INSTALL) {
			$install_version = read_config_option('install_version',true);
			log_install_high('step', 'Previously complete: ' . clean_up_lines(var_export($install_version, true)));
			if ($install_version === false || $install_version === null) {
				$install_version = $this->old_cacti_version;
			}

			$install_params = array();
			$install_error = read_config_option('install_error', true);
			if (!empty($install_error)) {
				$step = Installer::STEP_ERROR;
			} elseif (cacti_version_compare(CACTI_VERSION, $install_version, '==')) {
				log_install_debug('step', 'Does match: ' . clean_up_lines(var_export($this->old_cacti_version, true)));
				$step = Installer::STEP_COMPLETE;
			}
		} else if ($step >= Installer::STEP_COMPLETE) {
			$install_version = read_config_option('install_version',true);
			log_install_high('step', 'Previously complete: ' . clean_up_lines(var_export($install_version, true)));
			if ($install_version === false || $install_version === null) {
				$install_version = CACTI_VERSION;
			}

			if (!cacti_version_compare($this->old_cacti_version, $install_version, '==')) {
				log_install_debug('step', 'Does not match: ' . clean_up_lines(var_export($this->old_cacti_version, true)));
				$step = Installer::STEP_WELCOME;
				db_execute('DELETE FROM settings WHERE name LIKE \'install_%\'');
			} else {
				$install_params = array();
			}
		}
		log_install_high('step', 'After: ' . clean_up_lines(var_export($step, true)));

		$this->setStep($step);
		$this->stepError = false;

		$this->iconClass = array(
			DB_STATUS_ERROR   => 'fa fa-thumbs-down',
			DB_STATUS_WARNING => 'fa fa-exclamation-triangle',
			DB_STATUS_SUCCESS => 'fa fa-thumbs-up',
			DB_STATUS_SKIPPED => 'fa fa-check-circle'
		);

		$this->errors        = array();
		$this->eula          = read_config_option('install_eula', true);
		$this->cronInterval  = read_config_option('cron_interval', true);
		$this->locales       = get_installed_locales();
		$this->language      = read_user_setting('user_language', get_new_user_default_language(), true);
		$this->stepData      = null;
		$this->theme         = (isset($_SESSION['install_theme']) ? $_SESSION['install_theme']:read_config_option('selected_theme', true));

		if ($this->theme === false || $this->theme === null) {
			$this->setTheme('modern');
		}

		if ($step < Installer::STEP_INSTALL) {
			$this->setDefaults($install_params);
		}
		log_install_debug('step', 'Error: ' . clean_up_lines(var_export($this->stepError, true)));
		log_install_debug('step', 'Done: ' . clean_up_lines(var_export($this->stepCurrent, true)));
	}

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

		$this->templates          = $this->getTemplates();
		$this->tables             = $this->getTables();
		$this->paths              = install_file_paths();
		$this->permissions        = $this->getPermissions();
		$this->modules            = $this->getModules();
		$this->setProfile($this->getProfile());
		$this->setAutomationMode($this->getAutomationMode());
		$this->setAutomationOverride($this->getAutomationOverride());
		$this->setAutomationRange($this->getAutomationRange());
		$this->setPaths($this->getPaths());
		$this->setRRDVersion($this->getRRDVersion(), 'default ');
		$this->snmpOptions = $this->getSnmpOptions();
		$this->setMode($this->getMode());

		log_install_high('','Installer::processParameters(' . clean_up_lines(json_encode($install_params)) . ')');
		if (!empty($install_params)) {
			$this->processParameters($install_params);
		}

		if ($this->stepError !== false && $this->stepCurrent > $this->stepError) {
			$this->setStep($this->stepError);
		}
	}

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
					log_install_always('badkey',"$key => $value");
			}
		}
	}

	public function jsonSerialize() {
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

	public function getData() {
		return $this->jsonSerialize();
	}

	public function getErrors() {
		return (isset($this->errors) && !empty($this->errors)) ? $this->errors : array();
	}

	function setTrueFalse($param, &$field, $option) {
		$value = null;
		if ($param === true || $param === 'true' || $param === 'on' || $param === 1 || $param === '1') {
			$value = true;
		} else if ($param === false || $param === 'false' || $param === '' || $param === 0 || $param === '0') {
			$value = false;
		}

		if ($value !== null) {
			set_config_option('install_' . $option, $param);
			$field = $value;
		}

		$result = $value !== null;
		log_install_medium('', "setTrueFalse($option, " . var_export($param, true) . " sets $value, returns $result");
		return $result;
	}

	public function addError($step, $section, $item, $text = false) {
		if (!isset($this->errors[$section])) {
			$this->errors[$section] = array();
		}

		if ($text === false) {
			$this->errors[$section][] = $item;
			log_install_medium('errors',"addError($section, $item)");
		} else {
			$this->errors[$section][$item] = $text;
			log_install_medium('errors',"addError($section, $item, $text)");
		}

		log_install_debug('errors','stepError = ' . $step . ' -> ' . clean_up_lines(var_export($this->stepError, true)));
		if ($this->stepError === false || $this->stepError > $step) {
			$this->stepError = $step;
		}
		log_install_debug('errors-json', clean_up_lines(var_export($this->errors, true)));
	}

	private function setProgress($param_process) {
		log_install_always('', "Progress: $param_process");
		set_config_option('install_progress', $param_process);
		set_config_option('install_updated', microtime(true));
	}

	public function setRuntime($param_runtime = 'unknown') {
		if ($param_runtime == 'Web' || $param_runtime == 'Cli' || $param_runtime == 'Json') {
			$this->runtime = $param_runtime;
		} else {
			$this->addError(Installer::STEP_WELCOME, '', 'Failed to apply specified runtime');
		}
	}

	private function setLanguage($param_language = '') {
		if (isset($param_language) && strlen($param_language)) {
			$language = apply_locale($param_language);
			if (empty($language)) {
				$this->addError(Installer::STEP_WELCOME, 'Language', 'Failed to apply specified language');
			} else {
				$this->language = $param_language;
				set_user_setting('user_language', $param_language);
			}
		}
	}

	private function setEula($param_eula = '') {
		if ($param_eula == 'Accepted' || $param_eula === 'true') {
			$param_eula = 1;
		} else	if (!is_numeric($param_eula)) {
			$param_eula = 0;
		}

		$this->eula = ($param_eula > 0);
		if (!$this->eula) {
			$this->addError(Installer::STEP_WELCOME, 'Eula','Eula not accepted');
		}
		set_config_option('install_eula', $this->eula);
	}

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
		} else if (!empty($default_version)) {
			$rrdver = $this->sanitizeRRDVersion($default_version,'1.3');
		}

		log_install_medium('rrdversion', 'sanitizeRRDVersion() - returning ' . $rrdver);
		return $rrdver;
	}

	private function getRRDVersion() {
		$rrdver = read_config_option('install_rrdtool_vrsion');
		if (empty($rrdver)) {
			log_install_high('rrdversion', 'getRRDVersion(): Getting tool version');
			$rrdver = get_rrdtool_version();
			if (empty($rrdver)) {
				log_install_high('rrdversion', 'getRRDVersion(): Getting installed tool version');
				$rrdver = get_installed_rrdtool_version();
			}
		}
		log_install_medium('rrdversion', 'getRRDVersion(): ' . $rrdver);
		return $rrdver;
	}

	private function setRRDVersion($param_rrdver = '', $prefix = '') {
		global $config;
		if (isset($param_rrdver) && strlen($param_rrdver)) {
			$rrdver = $this->sanitizeRRDVersion($param_rrdver,'');
			if (empty($rrdver)) {
				$this->addError(Installer::STEP_BINARY_LOCATIONS, 'RRDVersion', 'setRRDVersion()', __('Failed to set specified %sRRDTool version: %s', $prefix, $param_rrdver));
			} else {
				$this->paths['rrdtool_version']['default'] = $param_rrdver;
				set_config_option('install_rrdtool_version', $param_rrdver);
				set_config_option('rrdtool_version', $param_rrdver);
			}
		}
	}

	private function setTheme($param_theme = '') {
		global $config;
		if (isset($param_theme) && strlen($param_theme)) {
			log_install_medium('theme','Checking theme: ' . $param_theme);
			$themePath = $config['base_path'] . '/include/themes/' . $param_theme . '/main.css';
			if (file_exists($themePath)) {
				log_install_debug('theme','Setting theme: ' . $param_theme);
				$this->theme = $param_theme;
				set_config_option('selected_theme', $this->theme);
			} else {
				$this->addError(Installer::STEP_WELCOME, 'Theme', __('Invalid Theme Specified'));
			}
		}
	}

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

		$install_key = 'always';
		if ($this->mode != Installer::MODE_POLLER) {
			$install_key = 'install';
		}

		foreach ($install_paths as $path) {
			$valid = (is_resource_writable($path . '/'));
			$permissions[$install_key][$path . '/'] = $valid;
			log_install_debug('permission',"$path = $valid ($install_key)");
			if (!$valid) {
				$this->addError(Installer::STEP_PERMISSION_CHECK, 'Permission', $path, __('Path was not writable'));
			}
		}

		foreach ($always_paths as $path) {
			$valid = (is_resource_writable($path . '/'));
			$permissions['always'][$path . '/'] = $valid;
			log_install_debug('permission',"$path = $valid");
			if (!$valid) {
				$this->addError(Installer::STEP_PERMISSION_CHECK, 'Permission', $path, __('Path is not writable'));
			}
		}

		return $permissions;
	}

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

	private function setPaths($param_paths = array()) {
		global $config;
		log_install_debug('paths', "setPaths(): BACKTRACE: " . cacti_debug_backtrace('', false, false));
		if (is_array($param_paths)) {
			log_install_debug('paths', 'setPaths(' . $this->stepCurrent . ', ' . cacti_count($param_paths) . ')');

			/* get all items on the form and write values for them  */
			foreach ($param_paths as $name => $path) {
				$key_exists = array_key_exists($name, $this->paths);
				$check = isset($this->paths[$name]['install_check']) ? $this->paths[$name]['install_check'] : 'file_exists';
				$optional = isset($this->paths[$name]['install_optional']) ? $this->paths[$name]['install_optional'] : false;
				log_install_high('paths', sprintf('setPaths(): name: %-25s, key_exists: %-5s, optional: %-5s, check: %s, path: %s', $name, $key_exists, $optional, $check, $path));
				if ($key_exists) {
					$should_set = true;
					if ($check == 'writable') {
						$should_set = is_resource_writable(dirname($path) . '/') || $optional;
						if ($should_set) {
							$should_set = is_resource_writable($path) || $optional;
						}
						if (!$should_set && !$optional) {
							$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('Resource is not writable'));
						}
					} else if ($check == 'file_exists') {
						$should_set = file_exists($path) || $optional;
						if (!$should_set) {
							$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('File not found'));
						}
					}

					if ($should_set && $name == 'path_php_binary') {
						$input = mt_rand(2,64);
						$output = shell_exec(
							$path . ' -q ' . $config['base_path'] .
							'/install/cli_test.php ' . $input);

						if ($output != $input * $input) {
							$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('PHP did not return expected result'));
							$should_set = false;
						}
					}

					if ($should_set) {
						unset($this->errors['Paths'][$name]);
						set_config_option($name, empty($path)?'':$path);
					}

					$this->paths[$name]['default'] = $path;
					log_install_debug('paths', sprintf('setPaths(): name: %-25s, key_exists: %-5s, optional: %-5s, should_set: %3s, check: %s', $name, $key_exists, $optional, $should_set?'Yes':'No',$check));
					log_install_debug('paths', sprintf('setPaths(): name: %-25s, data: %s', $name, clean_up_lines(var_export($this->paths[$name], true))));
				} else {
					$this->addError(Installer::STEP_BINARY_LOCATIONS, 'Paths', $name, __('Unexpected path parameter'));
				}
			}
		}
	}

	private function getProfile() {
		$db_profile = read_config_option('install_profile', true);
		if (empty($db_profile)) {
			$db_profile = db_fetch_cell('SELECT id FROM data_source_profiles WHERE `default` = \'on\' LIMIT 1');
			if ($db_profile === false) {
				$db_profile = db_fetch_cell('SELECT id FROM data_source_profiles ORDER BY id LIMIT 1');
			}
		}
		log_install_medium('automation', 'getProfile() returns with ' . $db_profile);
		log_install_medium('automation', 'getProfile() called from ' . cacti_debug_backtrace('', false, false, 1));

		return $db_profile;
	}

	private function setProfile($param_profile = null) {
		if (!empty($param_profile)) {
			$valid = db_fetch_cell_prepared('SELECT id FROM data_source_profiles WHERE id = ?', array($param_profile));
			if ($valid === false || $valid != $param_profile) {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Profile', __('Failed to apply specified profile %s != %s', $valid, $param_profile));
			} else {
				$this->profile = $valid;
				set_config_option('install_profile', $valid);
			}
		}
		log_install_medium('automation',"setProfile($param_profile) returns with $this->profile");
	}

	private function setAutomationMode($param_mode = null) {
		if ($param_mode != null) {
			if (!$this->setTrueFalse($param_mode, $this->automationMode, 'automation_mode')) {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Automation','Mode', __('Failed to apply specified mode: %s', $param_mode));
			}
		}
		log_install_medium('automation',"setAutomationMode($param_mode) returns with $this->automationMode");
	}

	private function setAutomationOverride($param_override = null) {
		if ($param_override != null) {
			if (!$this->setTrueFalse($param_override, $this->automationOverride, 'automation_override')) {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Automation','Override', __('Failed to apply specified automation override: %s', $param_override));
			}
		}
		log_install_medium('automation',"setAutomationOverride($param_override) returns with $this->automationOverride");
	}

	private function setCronInterval($param_mode = null) {
		global $cron_intervals;
		if ($param_mode != null) {
			if (array_key_exists($param_mode, $cron_intervals)) {
				$this->cronInterval = $param_mode;
				set_config_option('cron_interval', $param_mode);
			} else {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Poller','Cron', __('Failed to apply specified cron interval'));
			}
		}
		log_install_medium('automation',"setCronInterval($param_mode) returns with $this->cronInterval");
	}

	public function getAutomationRange() {
		$range = read_config_option('install_automation_range', true);
		if (empty($range)) {
			$row = db_fetch_row('SELECT id, subnet_range FROM automation_networks LIMIT 1');
			$enabled = 0;
			$network = '';
			log_install_debug('automation', "getAutomationRange(): found '" . clean_up_lines(var_export($row, true)));
			if (!empty($row)) {
				$range = $row['subnet_range'];
			}
		}
		$result = empty($range) ? '192.168.0.1/24' : $range;
		log_install_medium('automation',"getAutomationRange() returns '$result'");
		return $result;
	}

	private function setAutomationRange($param_range = null) {
		if (!empty($param_range)) {
			$ip_details = cacti_pton($param_range);
			if ($ip_details === false) {
				$this->addError(Installer::STEP_PROFILE_AND_AUTOMATION, 'Automation', 'Range', __('Failed to apply specified Automation Range'));
			} else {
				$this->automationRange = $param_range;
				set_config_option('install_automation_range', $param_range);
			}
		}
		log_install_medium('automation',"setAutomationRange($param_range) returns with $this->automationRange");
	}

	private function getSnmpOptions() {
		global $fields_snmp_item_with_retry;
		$db_snmp_options = db_fetch_assoc('SELECT name, value FROM settings where name like \'install_snmp_option_%\'');
		$options = array();
		foreach ($db_snmp_options as $row) {
			$key = str_replace('install_snmp_option_', '', $row['name']);
			$options[$key] = $row['value'];
		}

		log_install_debug('snmp_options','Option array: ' . clean_up_lines(var_export($options, true)));
		return $options;
	}

	private function setSnmpOptions($param_snmp_options = array()) {
		global $fields_snmp_item_with_retry;
		$known_snmp_options = $fields_snmp_item_with_retry;

		if (is_array($param_snmp_options)) {
			db_execute('DELETE FROM settings WHERE name like \'install_snmp_option_%\'');
			log_install_medium('snmp_options',"Updating snmp_options");
			log_install_debug('snmp_options',"Parameter data:" . clean_up_lines(var_export($param_snmp_options, true)));
			$known_map = array(
				'SnmpVersion'         => 'snmp_version'         ,
				'SnmpCommunity'       => 'snmp_community'       ,
				'SnmpSecurityLevel'   => 'snmp_security_level'  ,
				'SnmpUsername'        => 'snmp_username'        ,
				'SnmpAuthProtocol'    => 'snmp_auth_protocol'   ,
				'SnmpPassword'        => 'snmp_password'        ,
				'SnmpPrivProtocol'    => 'snmp_priv_protocol'   ,
				'SnmpPrivPassphrase'  => 'snmp_priv_passphrase' ,
				'SnmpContext'         => 'snmp_context'         ,
				'SnmpEngineId'        => 'snmp_engine_id'       ,
				'SnmpPort'            => 'snmp_port'            ,
				'SnmpTimeout'         => 'snmp_timeout'         ,
				'SnmpMaxOids'         => 'max_oids'             ,
				'SnmpRetries'         => 'snmp_retries'         ,
			);

			foreach ($param_snmp_options as $option_name => $option_value) {
				log_install_high('snmp_options',"snmp_option: ". clean_up_lines(var_export($option_name, true)));
				$bad_option = true;
				if (array_key_exists($option_name, $known_map)) {
					$key = $known_map[$option_name];
					log_install_debug('snmp_options',"snmp_option found:" . clean_up_lines(var_export($key, true)));

					if (array_key_exists($key, $known_snmp_options)) {
						$bad_option = false;
						log_install_high('snmp_options',"snmp_option set:" . clean_up_lines(var_export($option_value, true)));
						log_install_debug('snmp_options',"Set snmp_option: install_snmp_option_$key = $option_value");
						set_config_option("install_snmp_option_$key", $option_value);
					}
				}
				if ($bad_option) {
					$this->addError(Installer::STEP_TEMPLATE_INSTALL, 'SnmpOptions', $option_name, __('No matching snmp option exists'));
				}
			}
		}
	}

	private function getModules() {
		global $config;
		if ($config['cacti_server_os'] == 'unix') {
			$extensions = array(
				array('name' => 'ctype',     'installed' => false),
				array('name' => 'date',      'installed' => false),
				array('name' => 'filter',    'installed' => false),
				array('name' => 'gettext',   'installed' => false),
				array('name' => 'gd',        'installed' => false),
				array('name' => 'gmp',       'installed' => false),
				array('name' => 'hash',      'installed' => false),
				array('name' => 'json',      'installed' => false),
				array('name' => 'ldap',      'installed' => false),
				array('name' => 'mbstring',  'installed' => false),
				array('name' => 'openssl',   'installed' => false),
				array('name' => 'pcre',      'installed' => false),
				array('name' => 'PDO',       'installed' => false),
				array('name' => 'pdo_mysql', 'installed' => false),
				array('name' => 'posix',     'installed' => false),
				array('name' => 'session',   'installed' => false),
				array('name' => 'simplexml', 'installed' => false),
				array('name' => 'sockets',   'installed' => false),
				array('name' => 'spl',       'installed' => false),
				array('name' => 'standard',  'installed' => false),
				array('name' => 'xml',       'installed' => false),
				array('name' => 'zlib',      'installed' => false)
			);
		} elseif (version_compare(PHP_VERSION, '5.4.5') < 0) {
			$extensions = array(
				array('name' => 'ctype',     'installed' => false),
				array('name' => 'date',      'installed' => false),
				array('name' => 'filter',    'installed' => false),
				array('name' => 'gettext',   'installed' => false),
				array('name' => 'gd',        'installed' => false),
				array('name' => 'gmp',       'installed' => false),
				array('name' => 'hash',      'installed' => false),
				array('name' => 'json',      'installed' => false),
				array('name' => 'ldap',      'installed' => false),
				array('name' => 'mbstring',  'installed' => false),
				array('name' => 'openssl',   'installed' => false),
				array('name' => 'pcre',      'installed' => false),
				array('name' => 'PDO',       'installed' => false),
				array('name' => 'pdo_mysql', 'installed' => false),
				array('name' => 'session',   'installed' => false),
				array('name' => 'simplexml', 'installed' => false),
				array('name' => 'sockets',   'installed' => false),
				array('name' => 'spl',       'installed' => false),
				array('name' => 'standard',  'installed' => false),
				array('name' => 'xml',       'installed' => false),
				array('name' => 'zlib',      'installed' => false)
			);
		} else {
			$extensions = array(
				array('name' => 'com_dotnet','installed' => false),
				array('name' => 'ctype',     'installed' => false),
				array('name' => 'date',      'installed' => false),
				array('name' => 'filter',    'installed' => false),
				array('name' => 'gettext',   'installed' => false),
				array('name' => 'gd',        'installed' => false),
				array('name' => 'gmp',       'installed' => false),
				array('name' => 'hash',      'installed' => false),
				array('name' => 'json',      'installed' => false),
				array('name' => 'mbstring',  'installed' => false),
				array('name' => 'openssl',   'installed' => false),
				array('name' => 'pcre',      'installed' => false),
				array('name' => 'PDO',       'installed' => false),
				array('name' => 'pdo_mysql', 'installed' => false),
				array('name' => 'ldap',      'installed' => false),
				array('name' => 'session',   'installed' => false),
				array('name' => 'simplexml', 'installed' => false),
				array('name' => 'sockets',   'installed' => false),
				array('name' => 'spl',       'installed' => false),
				array('name' => 'standard',  'installed' => false),
				array('name' => 'xml',       'installed' => false),
				array('name' => 'zlib',      'installed' => false)
			);
		}

		$ext = verify_php_extensions($extensions);
		foreach ($ext as $e) {
			if (!$e['installed']) {
				$this->addError(Installer::STEP_CHECK_DEPENDENCIES, 'Modules', $e['name'] . ' is missing');
			}
		}
		return $ext;
	}

	private function getTemplates() {
		$known_templates = install_setup_get_templates();
		$db_templates = array_rekey(
			db_fetch_assoc('SELECT name, value FROM settings where name like \'install_template_%\''),
			'name', 'value');

		$hasTemplates = read_config_option('install_has_templates', true);
		$selected = array();
		$select_count = 0;
		log_install_debug('templates','getTemplates(): First: ' . (empty($hasTemplates) ? 'Yes' : 'No') . ', Templates - ' . clean_up_lines(var_export($known_templates, true)));
		log_install_debug('templates','getTemplates(): DB: ' . clean_up_lines(var_export($db_templates, true)));

		foreach ($known_templates as $known) {
			$filename    = $known['filename'];
			$key_base    = str_replace(".", "_", $filename);
			$key_install = 'install_template_' . $key_base;
			$key_check   = 'chk_template_' . $key_base;

			log_install_high('templates','getTemplates(): Checking template ' . $known['name'] . ' using base: ' . $key_base);
			log_install_debug('templates','getTemplates(): Checking template ' . $known['name'] . ' using key.: ' . $key_install);
			log_install_debug('templates','getTemplates(): Checking template ' . $known['name'] . ' filename..: ' . $filename);

			$value = '';
			if (array_key_exists($key_install, $db_templates)) {
				$value = $db_templates[$key_install];
			}

			log_install_debug('templates','getTemplates(): Checking template ' . $known['name'] . ' against...: ' . $value);

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

	private function setTemplates($param_templates = array()) {
		if (is_array($param_templates)) {
			db_execute('DELETE FROM settings WHERE name like \'install_template_%\'');
			$known_templates = install_setup_get_templates();

			log_install_medium('templates',"setTemplates(): Updating templates");
			log_install_debug('templates',"setTemplates(): Parameter data:" . clean_up_lines(var_export($param_templates, true)));
			log_install_debug('templates',"setTemplates(): Template data:" . clean_up_lines(var_export($known_templates, true)));
			foreach ($param_templates as $name => $enabled) {
				$template = false;

				log_install_high('templates','setTemplates(): ' . $name . ' => ' . ($enabled ? 'true' : 'false'));
				foreach ($known_templates as $known) {
					$filename = $known['filename'];
					$key = 'chk_template_' . str_replace(".", "_", $filename);
					if ($name == $key || $name == $filename) {
						$template = $known;
						break;
					}
				}

				if ($template === false) {
					$this->addError(Installer::STEP_TEMPLATE_INSTALL, 'Templates', $name, __('No matching template exists'));
				} else {
					$key = str_replace(".", "_", $template['filename']);
					$value = ($enabled ? $template['filename'] : '');
					log_install_high('templates',"setTemplates(): Using key: install_template_$key = " . $value);
					if ($enabled) {
						set_config_option("install_template_$key", $value);
					}
				}
			}

			set_config_option('install_has_templates', true);
		}
	}

	private function getTables() {
		$known_tables = install_setup_get_tables();
		$db_tables = array_rekey(
			db_fetch_assoc('SELECT name, value FROM settings where name like \'install_table_%\''),
			'name', 'value');
		$hasTables = read_config_option('install_has_tables', true);
		$selected = array();
		$select_count = 0;
		foreach ($known_tables as $known) {
			$table = $known['Name'];
			$key = $known['Name'];
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

	private function setTables($param_tables = array()) {
		if (is_array($param_tables)) {
			db_execute('DELETE FROM settings WHERE name like \'install_table_%\'');
			$known_tables = install_setup_get_tables();
			log_install_medium('tables',"setTables(): Updating Tables");
			log_install_debug('tables',"setTables(): Parameter data:" . clean_up_lines(var_export($param_tables, true)));
			foreach ($known_tables as $known) {
				$name = $known['Name'];
				$key = 'chk_table_' . $name;
				log_install_high('tables',"setTables(): Checking table '$name' against key $key ...");
				log_install_debug('tables',"setTables(): Table: ". clean_up_lines(var_export($known, true)));
				if (!empty($param_tables[$key]) || !empty($param_tables["all"])) {
					$table = $param_tables[$key];
					log_install_high('tables',"setTables(): install_table_$name = $name");
					set_config_option("install_table_$name", $name);
				}
			}

			set_config_option('install_has_tables', true);
		}
	}

	public function getMode() {
		if (isset($this->mode)) {
			$mode = $this->mode;
		} else {
			$mode = Installer::MODE_INSTALL;
			if ($this->old_cacti_version != 'new_install') {
				if (cacti_version_compare($this->old_cacti_version, CACTI_VERSION, '=')) {
					$equal = '=';
					$mode = Installer::MODE_NONE;
				} else if (cacti_version_compare($this->old_cacti_version, CACTI_VERSION, '<')) {
					$equal = '<';
					$mode = Installer::MODE_UPGRADE;
				} else {
					$equal = '>=';
					$mode = Installer::MODE_DOWNGRADE;
				}

				log_install_high('mode','getMode(): Version Mode - ' . clean_up_lines(var_export($this->old_cacti_version, true))
					. $equal . clean_up_lines(var_export(CACTI_VERSION, true)));

			} elseif ($this->hasRemoteDatabaseInfo()) {
				$mode = Installer::MODE_POLLER;
			}

			if ($mode == Installer::MODE_POLLER || $mode == Installer::MODE_INSTALL) {
				$db_mode = read_config_option('install_mode', true);
				log_install_high('mode','getMode(): DB Install mode ' . clean_up_lines(var_export($db_mode, true)));
				if ($db_mode !== false) {
					$mode = $db_mode;
				}
			}
		}
		log_install_high('mode','getMode(): ' . clean_up_lines(var_export($mode, true)));

		return $mode;
	}

	private function setMode($param_mode = 0) {
		if (intval($param_mode) > Installer::MODE_NONE && intval($param_mode) <= Installer::MODE_DOWNGRADE) {
			log_install_high('mode','setMode(' . $param_mode . ')');
			set_config_option('install_mode', $param_mode);
			$this->mode = $param_mode;
			$this->updateButtons();
		} else if ($param_mode != 0) {
			$this->addError(Installer::STEP_INSTALL_TYPE, 'mode', 'Failed to apply mode: ' . $param_mode);
		}
	}

	private function getStepDefault() {
		$mode = $this->getMode();
		$step = $mode == Installer::MODE_NONE ? Installer::STEP_COMPLETE : Installer::STEP_WELCOME;
		log_install_medium('step', "getStepDefault(): Resetting to $step as not found ($mode)");
		return $step;
	}

	public function getStep() {
		return $this->stepCurrent;
	}

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
		log_install_debug('step', "setStep():" . cacti_debug_backtrace('', false, false, 1));

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

	public function shouldRedirectToHome() {
		return ($this->old_cacti_version == CACTI_VERSION);
	}

	public function shouldExitWithReason() {
		if ($this->isDatabaseEmpty()) {
			return Installer::EXIT_DB_EMPTY;
		} elseif ($this->isDatabaseTooOld()) {
			return Installer::EXIT_DB_OLD;
		}

		return false;
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
		$output .= Installer::sectionNormal(__('An unexpected reason was given for preventing this maintenance session.'));
		$output .= Installer::sectionNormal(__('Please report this to the Cacti Group.'));
		$output .= Installer::sectionCode(__('Unknown Reason: %s', $reason));
		return $output;
	}

	private function exitDbTooOld() {
		$output  = Installer::sectionTitleError();
		$output .= Installer::sectionNormal(__('You are attempting to install Cacti %s onto a 0.6.x database. Unfortunately, this can not be performed.', CACTI_VERSION));
		$output .= Installer::sectionNormal(__('To be able continue, you <b>MUST</b> create a new database, import "cacti.sql" into it:', CACTI_VERSION));
		$output .= Installer::sectionCode(__("mysql -u %s -p [new_database] < cacti.sql", $database_username, $database_default));
		$output .= Installer::sectionNormal(__('You <b>MUST</b> then update "include/config.php" to point to the new database.'));
		$output .= Installer::sectionNormal(__('NOTE: Your existing data will not be modified, nor will it or any history be available to the new install'));
		return $output;
	}

	private function exitSqlNeeded() {
		global $config, $database_username, $database_default, $database_password;
		$output  = Installer::sectionTitleError();
		$output .= Installer::sectionNormal(__("You have created a new database, but have not yet imported the 'cacti.sql' file. At the command line, execute the following to continue:"));
		$output .= Installer::sectionCode(__("mysql -u %s -p %s < cacti.sql", $database_username, $database_default));
		$output .= Installer::sectionNormal(__("This error may also be generated if the cacti database user does not have correct permissions on the Cacti database. Please ensure that the Cacti database user has the ability to SELECT, INSERT, DELETE, UPDATE, CREATE, ALTER, DROP, INDEX on the Cacti database."));
		$output .= Installer::sectionNormal(__("You <b>MUST</b> also import MySQL TimeZone information into MySQL and grant the Cacti user SELECT access to the mysql.time_zone_name table"));

		if ($config['cacti_server_os'] == 'unix') {
			$output .= Installer::sectionNormal(__("On Linux/UNIX, run the following as 'root' in a shell:"));
			$output .= Installer::sectionCode(__("mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql"));
		} else {
			$output .= Installer::sectionNormal(__("On Windows, you must follow the instructions here <a target='_blank' href='https://dev.mysql.com/downloads/timezones.html'>Time zone description table</a>.  Once that is complete, you can issue the following command to grant the Cacti user access to the tables:"));
		}

		$output .= Installer::sectionNormal(__("Then run the following within MySQL as an administrator:"));
		$output .= Installer::sectionCode(__("mysql &gt; GRANT SELECT ON mysql.time_zone_name to '%s'@'localhost' IDENTIFIED BY '%s'", $database_username, $database_password));
		return $output;
	}

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

	public static function sectionNote($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionNote';

		return Installer::section('<span class="cactiInstallSectionNoteTitle">' . __('NOTE:') . '</span><span class=\'cactiInstallSectionNoteBody\'>' . $text . '</span>', $id, trim($class), '', 'p');
	}

	public static function sectionWarning($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionWarning';

		return Installer::section('<span class="cactiInstallSectionWarningTitle">' . __('WARNING:') . '</span><span class=\'cactiInstallSectionWarningBody\'>' . $text . '</span>', $id, trim($class), '', 'p');
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
			$this->buttonTest->Visible = true;
			$this->buttonTest->Text    = __('Test Connection');
		}

		$this->buttonNext->Text     = __('Next');
		$this->buttonPrevious->Text = __('Previous');

		$this->buttonTest->Visible = false;
		$this->buttonTest->setStep(0);

		switch($this->stepCurrent) {
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
					case Installer::MODE_INSTALL:
					case Installer::MODE_POLLER:
						$this->stepNext = Installer::STEP_BINARY_LOCATIONS;
						break;

					case Installer::MODE_UPGRADE:
					case Installer::MODE_DOWNGRADE:
						$this->stepNext = Installer::STEP_CHECK_TABLES;
						break;
				}

				break;

			case Installer::STEP_PERMISSION_CHECK:
				switch ($this->mode) {
					case Installer::MODE_UPGRADE:
					case Installer::MODE_DOWNGRADE:
						$this->stepPrevious = Installer::STEP_INSTALL_TYPE;
						break;
					}
					break;

			case Installer::STEP_INSTALL_CONFIRM:
				/* upgrade - if user upgrades send to settings check */
				if ($this->isPre_v0_8_UpgradeNeeded()) {
					/* upgrade - if user runs old version send to upgrade-oldversion */
					$this->stepNext = Installer::STEP_INSTALL_OLD;
				} else {
					$this->stepNext = Installer::STEP_INSTALL;
				}
				break;

			case Installer::STEP_CHECK_TABLES:
				if ($this->mode == Installer::MODE_UPGRADE ||
				    $this->mode == Installer::MODE_DOWNGRADE) {
					$this->stepPrevious = Installer::STEP_PERMISSION_CHECK;
				}
				break;

			case Installer::STEP_COMPLETE:
				$this->stepPrevious = Installer::STEP_NONE;
				break;
		}

		$this->buttonNext->setStep($this->stepNext);
		$this->buttonPrevious->setStep($this->stepPrevious);
	}

	/****************************************
	 * The following functions process the  *
	 * various steps of the install process *
	 ****************************************/
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
				return $this->processStepCheckDependancies();
			case Installer::STEP_INSTALL_TYPE:
				return $this->processStepMode();
			case Installer::STEP_BINARY_LOCATIONS:
				return $this->processStepBinaryLocations();
			case Installer::STEP_PERMISSION_CHECK:
				return $this->processStepPermissionCheck();
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
		global $config;

		$output  = Installer::sectionTitle(__('Cacti Version') . ' ' . CACTI_VERSION . ' - ' . __('License Agreement'));
		$output .= Installer::sectionNormal(__('Thanks for taking the time to download and install Cacti, the complete graphing solution for your network. Before you can start making cool graphs, there are a few pieces of data that Cacti needs to know.'));
		$output .= Installer::sectionNormal(__('Make sure you have read and followed the required steps needed to install Cacti before continuing. Install information can be found for <a href="%1$s">Unix</a> and <a href="%2$s">Win32</a>-based operating systems.', '../docs/html/install_unix.html', '../docs/html/install_windows.html'));

		if ($this->mode == Installer::MODE_UPGRADE) {
			$output .= Installer::sectionNote(__('This process will guide you through the steps for upgrading from version \'%s\'. ',$this->old_cacti_version));
			$output .= Installer::sectionNormal(__('Also, if this is an upgrade, be sure to reading the <a href="%s">Upgrade</a> information file.', '../docs/html/upgrade.html'));
		}

		if ($this->mode == Installer::MODE_DOWNGRADE) {
			$output .= Installer::sectionNote(__('It is NOT recommended to downgrade as the database structure may be inconsistent'));
		}

		$output .= Installer::sectionNormal(__('Cacti is licensed under the GNU General Public License, you must agree to its provisions before continuing:'));

		$output .= Installer::sectionCode(
			__('This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.') . '<br/><br/>' .
			__('This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.')
		);

		$langOutput = '<select id=\'language\' name=\'theme\'>';
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
			$langOutput .= '<option value=\'' . $key . '\'' . $selected . ' data-class=\'flag-icon-' . $flagName . '\'><span class="flag-icon flag-icon-squared flag-icon-'.$flagName.'"></span>' . $value . '</option>';
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
		$output .= Installer::sectionNormal('<span>' . __('Select default theme: ') . $themeOutput . '</span><span style=\'float: right\'><input type=\'checkbox\' id=\'accept\' name=\'accept\'' . $eula .'><label for=\'accept\'>' . __('Accept GPL License Agreement') . '</label></span><span>'.$langOutput.'</span>');

		$this->stepData = array('Eula' => $this->eula, 'Theme' => $this->theme, 'Language' => $this->language);
		$this->buttonNext->Enabled = ($this->eula == 1);

		return $output;
	}

	public function processStepCheckDependancies() {
		global $config;
		global $database_default, $database_username, $database_port;
		global $rdatabase_default, $rdatabase_username, $rdatabase_port;

		$enabled = array(
			'location'          => DB_STATUS_SUCCESS,
			'php_timezone'      => DB_STATUS_SUCCESS,
			'php_modules'       => DB_STATUS_SUCCESS,
			'php_optional'      => DB_STATUS_SUCCESS,
			'mysql_timezone'    => DB_STATUS_SUCCESS,
			'mysql_performance' => DB_STATUS_SUCCESS
		);

		$output  = Installer::sectionTitle(__('Pre-installation Checks'));
		$output .= Installer::sectionSubTitle(__('Location checks'), 'location');

		// Get request URI and break into parts
		$test_request_uri = $_SERVER['REQUEST_URI'];
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
			$test_final_path = substr($test_final_path,0,strlen($test_final_path) - $test_script_len);
		}

		// Add the install subfolder to defined path location
		// and check if it matches, if not there will likely be problems
		$test_config_path = $config['url_path'] . 'install/';
		$test_compare_result = strcmp($test_config_path,$test_final_path);

		// The path was not what we expected so print an error
		if ($test_compare_result !== 0) {
			$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Please update config.php with the correct relative URI location of Cacti (url_path).') . '</span>');
			$enabled['location'] = DB_STATUS_ERROR;
		} else {
			$output .= Installer::sectionNormal(__('Your Cacti configuration has the relative correct path (url_path) in config.php.'));
		}

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('PHP - Module Support (Required)'), 'php_modules');
		$output .= Installer::sectionNormal(__('Cacti requires several PHP Modules to be installed to work properly. If any of these are not installed, you will be unable to continue the installation until corrected. In addition, for optimal system performance Cacti should be run with certain MySQL system variables set.  Please follow the MySQL recommendations at your discretion.  Always seek the MySQL documentation if you have any questions.'));

		$output .= Installer::sectionNormal(__('The following PHP extensions are mandatory, and MUST be installed before continuing your Cacti install.'));

		ob_start();

		html_start_box(__('Required PHP Modules'), '100%', false, '3', '', false);
		html_header(array(__('Name'), __('Required'), __('Installed')));

		$enabled['php_modules'] = version_compare(PHP_VERSION, '5.4.0', '<') ? DB_STATUS_ERROR : DB_STATUS_SUCCESS;
		form_alternate_row('phpline',true);
		form_selectable_cell(__('PHP Version'), '');
		form_selectable_cell('5.4.0+', '');
		form_selectable_cell((version_compare(PHP_VERSION, '5.4.0', '<') ? "<font color=red>" . PHP_VERSION . "</font>" : "<font color=green>" . PHP_VERSION . "</font>"), '');
		form_end_row();

		$output .= Installer::sectionNormal(ob_get_contents());
		ob_clean();

		foreach ($this->modules as $id =>$e) {
			form_alternate_row('line' . $id);
			form_selectable_cell($e['name'], '');
			form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
			form_selectable_cell(($e['installed'] ? '<font color=green>' . __('Yes') . '</font>' : '<font color=red>' . __('No') . '</font>'), '');
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

		$extensions = array(
			array('name' => 'snmp', 'installed' => false),
		);

		$ext = verify_php_extensions($extensions);
		$ext[] = array('name' => 'TrueType Text', 'installed' => function_exists('imagettftext'));
		$ext[] = array('name' => 'TrueType Box', 'installed' => function_exists('imagettfbbox'));

		html_start_box(__('Optional Modules'), '100%', false, '3', '', false);
		html_header(array(__('Name'), __('Optional'), __('Installed')));

		foreach ($ext as $id => $e) {
			form_alternate_row('line' . $id, true);
			form_selectable_cell($e['name'], '');
			form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
			form_selectable_cell(($e['installed'] ? '<font color=green>' . __('Yes') . '</font>' : '<font color=orange>' . __('No') . '</font>'), '');
			form_end_row();

			if (!$e['installed']) $enabled['php_optional'] = DB_STATUS_WARNING;
		}

		html_end_box();

		$output .= Installer::sectionNormal(ob_get_contents());
		ob_clean();

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('PHP - Timezone Support'), 'php_timezone');
		if (ini_get('date.timezone') == '') {
			$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your Web Servers PHP Timezone settings have not been set.  Please edit php.ini and uncomment the \'date.timezone\' setting and set it to the Web Servers Timezone per the PHP installation instructions prior to installing Cacti.') . '</span>');
			$enabled['php_timezone'] = DB_STATUS_ERROR;
		} else {
			$output .= Installer::sectionNormal(__('Your Web Servers PHP is properly setup with a Timezone.'));
		}

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
		$this->buttonNext->Enabled = $enabled['php_modules'] != DB_STATUS_ERROR;
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
				$output .= Installer::sectionNormal(__('Upgrade from <strong>%s</strong> to <strong>%s</strong>', $this->old_cacti_version, CACTI_VERSION));

				$output .= Installer::sectionWarning(__('In the event of issues, It is highly recommended that you clear your browser cache, closing then reopening your browser (not just the tab Cacti is on) and retrying, before raising an issue with The Cacti Group'));
				$output .= Installer::sectionNormal(__('On rare occasions, we have had reports from users who experience some minor issues due to changes in the code.  These issues are caused by the browser retaining pre-upgrade code and whilst we have taken steps to minimise the chances of this, it may still occur.  If you need instructions on how to clear your browser cache, <a href=\'https://www.refreshyourcache.com\' target=\'_blank\'>https://www.refreshyourcache.com/</a> is a good starting point.'));
				$output .= Installer::sectionNormal(__('If after clearing your cache and restarting your browser, you still experience issues, please raise the issue with us and we will try to identify the cause of it.'));

				$output .= Installer::sectionSubTitleEnd();
				break;
			case Installer::MODE_DOWNGRADE:
				$output .= Installer::sectionSubTitle(__('Upgrade'));
				$output .= Installer::sectionNormal(__('Downgrade from <strong>%s</strong> to <strong>%s</strong>', $this->old_cacti_version, CACTI_VERSION));
				$output .= Installer::sectionWarning(__('You appears to be downgrading to a previous version.  Database changes made for the newer version will not be reversed and <i>could</i> cause issues.'));
				$output .= Installer::sectionSubTitleEnd();
				break;
			default:
				// new install
				$output .= Installer::sectionSubTitle(__('Please select the type of installation'));
				$output .= Installer::sectionNormal(__('Installation options:'));

				$output .= Installer::sectionNormal(
					'<ul>'.
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
				);

				$this->buttonNext->Enabled = true;
				switch ($this->mode) {
					case Installer::MODE_POLLER:
						$selectedPoller = ' selected';
						$sections['poller_vars'] = 1;
						$sections['connection_remote'] = 1;
						$sections['error_file'] = !$this->isConfigurationWritable();
						$sections['error_poller'] = !$this->isRemoteDatabaseGood();
						$this->buttonNext->Enabled = !($sections['error_file'] || $sections['error_poller']);
						$this->buttonTest->Enabled = true;
						$this->buttonTest->Visible = true;
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

				$output .= Installer::sectionSubTitle(__('Database Connection Information'),'connection_local');

				$output .= Installer::sectionCode(
					__('Database: <b>%s</b>', $database_default) . '<br>' .
					__('Database User: <b>%s</b>', $database_username) . '<br>' .
					__('Database Hostname: <b>%s</b>', $database_hostname) . '<br>' .
					__('Port: <b>%s</b>', $database_port) . '<br>' .
					__('Server Operating System Type: <b>%s</b>', $config['cacti_server_os']) . '<br>'
				);

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Database Connection Information'),'connection_remote');

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
				$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' . __('Your Remote Cacti Poller information has not been included in your config.php file.  Please review the config.php.dist, and set the variables: <i>$rdatabase_default, $rdatabase_username</i>, etc.  These variables must be set and point back to your Primary Cacti database server.  Correct this and try again.') . '</span>','config_remote');

				$output .= Installer::sectionSubTitleEnd();

				$output .= Installer::sectionSubTitle(__('Remote Poller Variables'), 'poller_vars');
				$output .= Installer::sectionNormal(__('The variables that must be set include the following:'));
				$output .= Installer::sectionCode(
					'$rdatabase_type     = \'mysql\';<br>' .
					'$rdatabase_default  = \'cacti\';<br>' .
					'$rdatabase_hostname = \'localhost\';<br>' .
					'$rdatabase_username = \'cactiuser\';<br>' .
					'$rdatabase_password = \'cactiuser\';<br>' .
					'$rdatabase_port     = \'3306\';<br>' .
					'$rdatabase_ssl      = false;<br>'
				);

				$output .= Installer::sectionNormal(__('You must also set the $poller_id variable in the config.php.'), 'config_remote_poller');
				$output .= Installer::sectionNormal(__('Once you have the variables set in the config.php file, you must also grant the $rdatabase_username access to the Cacti database.  Follow the same procedure you would with any other Cacti install.  You may then press the \'Test Connection\' button.  If the test is successful you will be able to proceed and complete the install.'), 'config_remote_var');

				$this->stepData = array('Sections' => $sections);
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
			$class = ($class == 'even' ? 'odd':'even');

			$current_value = '';
			if (isset($array['default'])) {
				$current_value = $array['default'];
			}

			log_install_debug('paths','processStepBinaryLocations(): Displaying ' . $array['friendly_name'] . ' (' . $name . ' - ' . $class . '): ' . $current_value);

			/* run a check on the path specified only if specified above, then fill a string with
			the results ('FOUND' or 'NOT FOUND') so they can be displayed on the form */
			$form_check_string = '';

			/* draw the acual header and textbox on the form */
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

			/*** Disable ouput of error for now, pending QA ***
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

		$sections = array();
		if ($this->mode != Installer::MODE_POLLER) {
			$output .= Installer::sectionSubTitle(__('Required Writable at Install Time Only'), 'writable_install');

			$sections['writable_install'] = DB_STATUS_SUCCESS;
			$class = 'even';
			foreach($this->permissions['install'] as $path => $valid) {
				$class = ($class == 'even' ? 'odd':'even');

				/* draw the acual header and textbox on the form */
				$output .= "<div class='formRow $class'><div class='formColumnLeft'><div class='formFieldName'>" . $path . "</div></div>";

				$output .= "<div class='formColumnRight'><div class='formData' width='100%'>";

				if ($valid) {
					$output .=
						'<i class="' . $this->iconClass[DB_STATUS_SUCCESS] . '"></i> ' .
						'<font color="#008000">' . __('Writable') . '</font>';
				} else {
					$output .=
						'<i class="' . $this->iconClass[DB_STATUS_ERROR] . '"></i> ' .
						'<font color="#FF0000">' . __('Not Writable') . '</font>';

					$writable = false;
					$sections['writable_install'] = DB_STATUS_ERROR;
				}

				$output .= "</div></div></div>";
			}
		}

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('Required Writable after Install Complete'),'writable_always');
		$sections['writable_always'] = DB_STATUS_SUCCESS;

		$class = 'even';
		foreach($this->permissions['always'] as $path => $valid) {
			$class = ($class == 'even' ? 'odd':'even');

			/* draw the acual header and textbox on the form */
			$output .= "<div class='formRow $class'><div class='formColumnLeft'><div class='formFieldName'>" . $path . "</div></div>";

			$output .= "<div class='formColumnRight'><div class='formData' width='100%'>";

			if ($valid) {
				$output .=
					'<i class="' . $this->iconClass[DB_STATUS_SUCCESS] . '"></i> ' .
					'<font color="#008000">' . __('Writable') . '</font>';
			} else {
				$output .=
					'<i class="' . $this->iconClass[DB_STATUS_ERROR] . '"></i> ' .
					'<font color="#FF0000">' . __('Not Writable') . '</font>';
				$sections['writable_always'] = DB_STATUS_ERROR;
				$writable = false;
			}

			$output .= '</div></div></div>';
		}

		/* Print help message for unix and windows if directory is not writable */
		if (($config['cacti_server_os'] == 'unix') && isset($writable)) {
			$output .= Installer::sectionSubTitleEnd();

			$running_user = get_running_user();

			$sections['host_access'] = DB_STATUS_WARNING;
			$output .= Installer::sectionSubTitle(__('Ensure Host Process Has Access'),'host_access');
			$output .= Installer::sectionNormal(__('Make sure your webserver has read and write access to the entire folder structure.'));
			$output .= Installer::sectionNormal(__('Example:'));
			if ($config['cacti_server_os'] == 'win32') {
				$output .= Installer::sectionCode(__('%s should have MODIFY permission to the above directories', $running_user));
			} else {
				$output .= Installer::sectionCode(__('chown -R %s.%s %s/resource/', $running_user, $running_user, $config['base_path']));
				$output .= Installer::sectionNormal(__('For SELINUX-users make sure that you have the correct permissions or set \'setenforce 0\' temporarily.'));
			}
		} elseif (($config['cacti_server_os'] == 'win32') && isset($writable)){
			$output .= Installer::sectionNormal(__('Check Permissions'));
		}else {
			$output .= Installer::sectionNormal('<font color="#008000">' . __('All folders are writable') . '</font>');
		}

		if ($this->mode != Installer::MODE_POLLER) {
			$output .= Installer::sectionNote(__('If you are installing packages, once the packages are installed, you should change the scripts directory back to read only as this presents some exposure to the web site.'));
		} else {
			$output .= Installer::sectionNote(__('For remote pollers, it is critical that the paths that you will be updating frequently, including the plugins, scripts, and resources paths have read/write access as the data collector will have to update these paths from the main web server content.'));
		}

		$this->buttonNext->Enabled = !isset($writable);
		$this->stepData = array('Sections' => $sections);
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
			$ouptut .= Installer::sectionNormal(__('The installation cannot continue because no profiles could be found.'));
			$output .= Installer::sectionNote(__('This may occur if you have a blank database and have not yet imported the cacti.sql file'));
			$output .= Installer::sectionCode('mysql> source cacti.sql');
		}

		return $output;
	}

	public function processStepTemplateInstall() {
		$output = Installer::sectionTitle(__('Template Setup'));

		$output .= Installer::sectionNormal(__('Please select the Device Templates that you wish to use after the Install.  If you Operating System is Windows, you need to ensure that you select the \'Windows Device\' Template.  If your Operating System is Linux/UNIX, make sure you select the \'Local Linux Machine\' Device Template.'));

		$templates = install_setup_get_templates();
		ob_start();
		html_start_box(__('Templates'), '100%', false, '3', 'center', '', '');
		html_header_checkbox(array(__('Name'), __('Description'), __('Author'), __('Homepage')));
		foreach ($templates as $id => $p) {
			form_alternate_row('line' . $id, true);
			form_selectable_cell($p['name'], $id);
			form_selectable_cell($p['description'], $id);
			form_selectable_cell($p['author'], $id);
			if ($p['homepage'] != '') {
				form_selectable_cell('<a href="'. $p['homepage'] . '" target=_new>' . $p['homepage'] . '</a>', $id);
			} else {
				form_selectable_cell('', $id);
			}
			form_checkbox_cell($p['name'], 'template_'  . str_replace(".", "_",  $p['filename']));
			form_end_row();
		}
		html_end_box(false);
		$output .= Installer::sectionNormal(ob_get_contents());
		ob_end_clean();
		$output .= Installer::sectionNormal(__('Device Templates allow you to monitor and graph a vast assortment of data within Cacti.  After you select the desired Device Templates, press \'Finish\' and the installation will complete.  Please be patient on this step, as the importation of the Device Templates can take a few minutes.'));

		$this->stepData = array('Templates' => $this->templates);
		return $output;
	}

	public function processStepCheckTables() {
		$output = Installer::sectionTitle(__('Database Collation'));

		$collation_vars = db_fetch_assoc('SHOW VARIABLES LIKE "collation_database";');

		$collation_value = '';
		$collation_valid = false;
		if (cacti_sizeof($collation_vars)) {
			$collation_value = $collation_vars[0]['Value'];
			$collation_valid = ($collation_value == 'utf8mb4_unicode_ci');
		}

		if ($collation_valid) {
			$output .= Installer::sectionNormal(__('Your databse default collation appears to be UTF8 compliant'));
		} else {
			$output .= Installer::sectionNormal(__('Your database default collaction does NOT appear to be UTF8 compliant'));
			$output .= Installer::sectionWarning(__('Any tables created by plugins may have issues linked against Cacti Core tables if the collation is not matched'));
			$output .= Installer::sectionNormal(__('Please ensure your database is changed from \'%s\' to \'utf8mb4_unicode_ci\'', $collation_value));
		}

		$output .= Installer::sectionTitle(__('Table Setup'));

		$tables = install_setup_get_tables();

		if (cacti_sizeof($tables)) {
			$output .= Installer::sectionNormal(__('The following tables should be converted to UTF8 and InnoDB.  Please select the tables that you wish to convert during the installation process.'));
			$output .= Installer::sectionWarning(__('Conversion of tables may take some time especially on larger tables.  The conversion of these tables will occur in the background but will not prevent the installer from completing.  This may slow down some servers if there are not enough resources for MySQL to handle the conversion.'));

			$show_warning=false;
			ob_start();
			html_start_box(__('Tables'), '100%', false, '3', 'center', '', '');
			html_header_checkbox(array(__('Name'), __('Collation'), __('Engine'), __('Rows')));
			foreach ($tables as $id => $p) {
				$enabled = ($p['Rows'] < 1000000 ? true : false);
				$style = ($enabled ? '' : 'text-decoration: line-through;');
				form_alternate_row('line' . $id, true, $enabled);
				form_selectable_cell($p['Name'], $id, '', $style);
				form_selectable_cell($p['Collation'], $id, '', $style);
				form_selectable_cell($p['Engine'], $id, '', $style);
				form_selectable_cell($p['Rows'], $id, '', $style);

				if ($enabled) {
					form_checkbox_cell($p['Name'], 'table_'  . $p['Name']);
				} else {
					$show_warning=true;
					form_selectable_cell('', $id, '', $style);
				}
				form_end_row();
			}
			html_end_box(false);
			$output .= Installer::sectionNormal(ob_get_contents());

			if ($show_warning) {
				$output .= Installer::sectionTitleError(__('WARNING'));
				$output .= Installer::sectionNormal(__('One or more tables are too large to convert during the installation.  You should use the cli/convert_tables.php script to perform the conversion.'));
				$output .= Installer::sectionCode(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . 'cli/convert_tables.php -u -i');
			}

			ob_end_clean();
		} else {
			$output .= Installer::sectionNormal(__('All your tables appear to be UTF8 compliant'));
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
			$output .= Installer::sectionCode(__('YOU MUST MANAUALLY CHANGE THE CACTI DATABASE TO REVERT ANY UPGRADE CHANGES THAT HAVE BEEN MADE.<br/>THE INSTALLER HAS NO METHOD TO DO THIS AUTOMATICALLY FOR YOU'));
			$output .= Installer::sectionNormal(__('Downgrading should only be performed when absolutely necessary and doing so may break your installlation'));
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
		$time = read_config_option('install_updated', true);

		$output  = Installer::sectionTitle(__('Installing Cacti Server v%s', CACTI_VERSION));
		$output .= Installer::sectionNormal(__('Your Cacti Server is now installing'));
		$output .= Installer::sectionNormal(
			'<table width="100%"><tr>' .
				'<td class="cactiInstallProgressLeft">Refresh in</td>' .
				'<td class="cactiInstallProgressCenter">&nbsp;</td>' .
				'<td class="cactiInstallProgressRight">Progress<span style=\'float:right\'>Last updated: ' . date('H:i:s', $time) . '</span></td>' .
			'</tr><tr>' .
				'<td class="cactiInstallProgressLeft">'.
					'<div id="cactiInstallProgressCountdown"><div></div></div>' .
				'</td>' .
				'<td class="cactiInstallProgressCenter">&nbsp;</td>' .
				'<td class="cactiInstallProgressRight">' .
					'<div id="cactiInstallProgressBar"><div></div></div>' .
				'</td>' .
			'</tr></table>'
		);

		$backgroundTime = read_config_option('install_started', true);
		if ($backgroundTime === null) {
			$backgroundTime = false;
		}

		$backgroundLast = read_config_option('install_updated', true);
		if ($backgroundLast === null) {
			$backgroundLast = false;
		}

		$backgroundNeeded = $backgroundTime === false;
		if ($backgroundTime === false) {
			$backgroundTime = microtime(true);
			set_config_option("install_started", $backgroundTime);
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
			if ($backgroundLast === false || $backgroundLast < $backgroundTime) {
				log_install_high('background', 'backgroundLast = ' . $backgroundTime . " (Replaced)");
				$backgroundLast = $backgroundTime;
			}

			$backgroundExpire = time() - 1500;
			log_install_debug('background', 'backgroundExpire = ' . $backgroundExpire);

			if ($backgroundLast < $backgroundExpire) {
				$newTime = microtime(true);

				set_config_option("install_started", $newTime);
				set_config_option("install_updated", $newTime);

				$backgroundTime = read_config_option("install_started", true);
				if ($backgroundTime === null) {
					$backgroundTime = false;
				}
				$backgroundLast = read_config_option("install_updated", true);
				if ($backgroundLast === null) {
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
			$php = read_config_option('path_php_binary', true);
			$php_file = $config['base_path'] . '/install/background.php ' . $backgroundTime;

			log_install_always('', 'Spawning background process: ' . $php . ' ' . $php_file);
			exec_background($php, $php_file);
		}

		$output .= $this->getInstallLog();

		$this->buttonNext->Visible = false;
		$this->buttonPrevious->Visible = false;

		$progressCurrent = read_config_option('install_progress', true);
		if ($progressCurrent === false || $progressCurrent === null) {
			$progressCurrent = Installer::PROGRESS_NONE;
		}

		$stepData = array( 'Current' => $progressCurrent, 'Total' => Installer::PROGRESS_COMPLETE );
		$this->stepData = $stepData;

		return $output;
	}

	public function processStepComplete() {
		global $cacti_version_codes, $database_statuses;

		$cacheFile = read_config_option('install_cache_db', true);

		if ($this->stepCurrent == Installer::STEP_COMPLETE) {
			$output = Installer::sectionTitle(__('Complete'));
			$output .= Installer::sectionNormal(__('Your Cacti Server v%s has been installed/updated.  You may now start using the software.', CACTI_VERSION));
		} elseif ($this->stepCurrent == Installer::STEP_ERROR) {
			$output = Installer::sectionTitleError();
			$output .= Installer::sectionNormal(__('Your Cacti Server v%s has been installed/updated with errors', CACTI_VERSION));
		}

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

//						if (!empty($action[4])) {
//							$sql .= "<br>Error: " . $action[4];
//						}

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
		$output .= $this->getInstallLog();

		$this->buttonPrevious->Visible = false;
		$this->buttonNext->Enabled = true;

		if (isset($sections)) {
			$this->stepData = array('Sections' => $sections);
		}

		iF ($this->stepCurrent == Installer::STEP_ERROR) {
			$this->buttonPrevious->Text = __('Get Help');
			$this->buttonPrevious->Step = -2;
			$this->buttonPrevious->Visible = true;
			$this->buttonPrevious->Enabled = true;

			$this->buttonNext->Text = __('Report Issue');
			$this->buttonNext->Step = -3;
		} else {
			$this->buttonNext->Text = __('Get Started');
			$this->buttonNext->Step = -1;
		}

		return $output;
	}

	public function getAutomationOverride() {
		return read_config_option('install_automation_override', true);
	}

	public function getAutomationMode() {
		$enabled = read_config_option('install_automation_mode', true);
		log_install_debug('automation', 'automation_mode: ' . clean_up_lines($enabled));
		if ($enabled == NULL) {
			$row = db_fetch_row('SELECT id, enabled FROM automation_networks LIMIT 1');
			log_install_debug('automation', 'Network data: ' . clean_up_lines(var_export($row, true)));
			$enabled = 0;
			if (!empty($row)) {
				if ($row['enabled'] == 'on') {
					$enabled = 1;
				}
			}
		}
		log_install_medium('automation',"getAutomationMode() returns '$enabled'");
		return $enabled;
	}

	public function getInstallLog() {
		global $config;
		$logcontents = tail_file($config['base_path'] . '/log/cacti.log', 100, -1, ' INSTALL:' , 1, $total_rows);

		$output_log = '';
		foreach ($logcontents as $logline) {
			$output_log = $logline.'<br/>' . $output_log;
		}

		if (empty($output_log)) {
			$output_log = '--- NO LOG FOUND ---';
		}

		$output = Installer::sectionCode($output_log);
		return $output;
	}

	public static function processInstall($backgroundArg, $installer = null) {
		$eula = read_config_option('install_eula', true);
		if (empty($eula)) {
			log_install_always('', 'Install aborted due to no EULA acceptance');
			return false;
		}

		$backgroundTime = read_config_option('install_started', true);
		if ($backgroundTime === null) {
			$backgroundTime = false;
		}

		log_install_high('', "processInstall(): '$backgroundTime' (time) != '$backgroundArg' (arg) && '-b' != '$backgroundArg' (arg)");
		if ("$backgroundTime" != "$backgroundArg" && "-b" != "$backgroundArg") {
			$dateTime = DateTime::createFromFormat('U.u', $backgroundTime);
			if ($dateTime === false) {
				$dateTime = new DateTime();
			}

			$dateArg = DateTime::createFromFormat('U.u', $backgroundArg);
			if ($dateArg === false) {
				$dateArg = new DateTime();
			}
			$background_error = sprintf('Background was already started at %s, this attempt at %s was skipped',
				$dateTime->format('Y-m-d H:i:s.u'),
				$dateArg->format('Y-m-d H:i:s.u'));
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
			$installer->processBackgroundInstall();
		} catch (Exception $e) {
			log_install_always('', __('Exception occurred during installation:  #' . $e->getErrorCode() . ' - ' . $e->getErrorText()), false, 'INSTALL:');
		}

		$backgroundDone = microtime(true);
		set_config_option('install_complete', $backgroundDone);

		$dateBack = DateTime::createFromFormat('U.u', $backgroundTime);
		$dateTime = DateTime::createFromFormat('U.u', $backgroundDone);

		log_install_always('', __('Installation was started at %s, completed at %s', $dateBack->format('Y-m-d H:i:s'), $dateTime->format('Y-m-d H:i:s')), false, 'INSTALL:');
		return true;
	}

	public static function setPhpOption($option_name, $option_value) {
		log_install_always('', 'Setting PHP Option ' . $option_name . ' = ' . $option_value);
		$value = ini_get($option_name);
		if ($value != $option_value) {
			ini_set($option_name, $option_value);
			$value = ini_get($option_name);
			if ($value != $option_value) {
				log_install_always('', 'Failed to set PHP option ' . $option_name . ', is ' . $value . ' (should be ' . $option_value . ')');
			}
		}
	}

	private function processBackgroundInstall() {
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

		log_install_always('', sprintf('Starting %s Process for v%s', $which, CACTI_VERSION));

		$this->setProgress(Installer::PROGRESS_START);

		$this->convertDatabase();

		if (!$this->hasRemoteDatabaseInfo()) {
			$this->installTemplate();
		}

		$this->setProgress(Installer::PROGRESS_TEMPLATES_END);

		if ($this->mode == Installer::MODE_POLLER) {
			$failure = $this->installPoller();
		} else {
			if ($this->mode == Installer::MODE_INSTALL) {
				$failure = $this->installServer();
			} else if ($this->mode == Installer::MODE_UPGRADE) {
				$failure = $this->upgradeDatabase();
			}
			$this->disableInvalidPlugins();
		}

		log_install_always('', sprintf('Finished %s Process for v%s', $which, CACTI_VERSION));

		set_config_option('install_error',$failure);
		$this->setProgress(Installer::PROGRESS_VERSION_BEGIN);
		set_config_option('install_version', CACTI_VERSION);
		$this->setProgress(Installer::PROGRESS_VERSION_END);

		if (empty($failure)) {
			db_execute('UPDATE version SET cacti = \'' . CACTI_VERSION . '\'');

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
		$templates = db_fetch_assoc("SELECT value FROM settings WHERE name like 'install_template_%'");
		if (cacti_sizeof($templates)) {
			log_install_always('', sprintf('Found %s templates to install', cacti_sizeof($templates)));
			$path = $config['base_path'] . '/install/templates/';

			$this->setProgress(Installer::PROGRESS_TEMPLATES_BEGIN);
			$i = 0;
			foreach ($templates as $template) {
				$i++;
				$package = $template['value'];
				set_config_option('install_updated', microtime(true));
				log_install_always('', sprintf('Importing Package #%s \'%s\' under Profile \'%s\'', $i, $package, $this->profile));
				import_package($path . $package, $this->profile, false, false, false);
				$this->setProgress(Installer::PROGRESS_TEMPLATES_BEGIN + $i);
			}

			db_execute('TRUNCATE TABLE automation_templates');

			foreach($this->defaultAutomation as $item) {
				$host_template_id = db_fetch_cell_prepared('SELECT id
					FROM host_template
					WHERE hash = ?',
					array($item['hash']));

				if (!empty($host_template_id)) {
					log_install_always('', sprintf('Mapping Automation Template for Device Template \'%s\'', $item['name']));


					db_execute_prepared('INSERT INTO automation_templates
						(host_template, availability_method, sysDescr, sysName, sysOid, sequence)
						VALUES (?, ?, ?, ?, ?, ?)',
						array($host_template_id, $item['availMethod'], $item['sysDescrMatch'],
						$item['sysNameMatch'], $item['sysOidMatch'], $item['sequence']));
				}
			}
		} else {
			log_install_always('', sprintf('No templates were selected for import'));
		}
	}

	private function installPoller() {
		log_install_always('', 'Updating remote configuration file');
		global $local_db_cnn_id;

		$failure = remote_update_config_file();
		if (empty($failure)) {

			/* change cacti version */
			db_execute('DELETE FROM version', true, $local_db_cnn_id);
			db_execute("INSERT INTO version (cacti) VALUES ('" . CACTI_VERSION . "')", true, $local_db_cnn_id);

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
		$profile = db_fetch_row_prepared('SELECT id, name, step, heartbeat
			FROM data_source_profiles
			WHERE id = ?',
			array($profile_id));

		log_install_high('automation', "Profile ID: $profile_id (" . $this->profile . ") returned " . clean_up_lines(var_export($profile, true)));

		if ($profile['id'] == $this->profile) {
			log_install_always('automation', sprintf('Setting default data source profile to %s (%s)', $profile['name'], $profile['id']));
			$this->setProgress(Installer::PROGRESS_PROFILE_DEFAULT);

			db_execute('UPDATE data_source_profiles
				SET `default` = ""');

			db_execute_prepared('UPDATE data_source_profiles
				SET `default` = \'on\'
				WHERE `id` = ?',
				array($profile['id']));

			db_execute_prepared('UPDATE data_template_data
				SET rrd_step = ?, data_source_profile_id = ?',
				array($profile['step'], $profile['id']));

			db_execute_prepared('UPDATE data_template_rrd
				SET rrd_heartbeat = ?',
				array($profile['heartbeat']));

			$this->setProgress(Installer::PROGRESS_PROFILE_POLLER);
			set_config_option('poller_interval', $profile['step']);
		} else {
			log_install_always('', sprintf('Failed to find selected profile (%s), no changes were made', $profile_id));
		}

		$this->setProgress(Installer::PROGRESS_PROFILE_END);

		$this->setProgress(Installer::PROGRESS_AUTOMATION_START);
		$automation_row = db_fetch_row('SELECT id, enabled, subnet_range FROM automation_networks ORDER BY id LIMIT 1');
		log_install_debug('automation','Automation Row:' . clean_up_lines(var_export($automation_row, true)));
		if (!empty($automation_row)) {
			log_install_always('', sprintf('Updating automation network (%s), mode "%s" => "%s", subnet "%s" => %s"'
				, $automation_row['id'], $automation_row['enabled'], $this->automationMode ? 'on' : ''
				, $automation_row['subnet_range'], $this->automationRange));

			db_execute_prepared('UPDATE automation_networks SET
				subnet_range = ?,
				enabled = ?
				WHERE id = ?',
				array($this->automationRange, ($this->automationMode ? 'on' : ''), $automation_row['id']));
		} else {
			log_install_always('', 'Failed to find automation network, no changes were made');
		}

		$override = read_config_option('install_automation_override', true);
		if (!empty($override)) {
			log_install_always('', 'Adding extra snmp settings for automation');

			$snmp_options = db_fetch_assoc('select name, value from settings where name like \'install_snmp_option_%\'');
			$snmp_id = db_fetch_cell('select id from automation_snmp limit 1');

			if ($snmp_id) {
				log_install_always('', 'Selecting Automation Option Set ' . $snmp_id);

				$save = array('id' => '', 'snmp_id' => $snmp_id);
				foreach($snmp_options as $snmp_option) {
					$snmp_name = str_replace('install_snmp_option_','',$snmp_option['name']);
					$snmp_value = $snmp_option['value'];

					if ($snmp_name != 'snmp_security_level') {
						$save[$snmp_name] = $snmp_value;
						set_config_option($snmp_name, $snmp_value);
					}
				}

				log_install_always('', 'Updating Automation Option Set ' . $snmp_id);
				$item_id = sql_save($save, 'automation_snmp_items');
				if ($item_id) {
					log_install_always('', 'Successfully updated Automation Option Set ' . $snmp_id);

					log_install_always('', 'Resequencing Automation Option Set ' . $snmp_id);
					db_execute_prepared('UPDATE automation_snmp_items
							     SET sequence = sequence + 1
							     WHERE snmp_id = ?',
							     array($snmp_id));
				} else {
					log_install_always('', 'Failed to updated Automation Option Set ' . $snmp_id);
				}
			} else {
				log_install_always('', 'Failed to find any automation option set');
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
			$description  = "Local Windows Machine";
		} else {
			$hash = '2d3e47f416738c2d22c87c40218cc55e';
			$version      = 0;
			$community    = 'public';
			$avail        = 'none';
			$ip           = 'localhost';
			$description  = "Local Linux Machine";
		}

		$host_template_id = db_fetch_cell_prepared('SELECT id FROM host_template WHERE hash = ?', array($hash));

		// Add the host
		if (!empty($host_template_id)) {
			$this->setProgress(Installer::PROGRESS_DEVICE_TEMPLATE);
			log_install_always('', 'Device Template for First Cacti Device is ' . $host_template_id);

			$results = shell_exec(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/cli/add_device.php' .
				' --description=' . cacti_escapeshellarg($description) .
				' --ip=' . cacti_escapeshellarg($ip) .
				' --template=' . $host_template_id .
				' --notes=' . cacti_escapeshellarg('Initial Cacti Device') .
				' --poller=1 --site=0 --avail=' . cacti_escapeshellarg($avail) .
				' --version=' . $version .
				' --community=' . cacti_escapeshellarg($community));

			$host_id = db_fetch_cell_prepared('SELECT id
				FROM host
				WHERE host_template_id = ?
				LIMIT 1',
				array($host_template_id));

			if (!empty($host_id)) {
				$this->setProgress(Installer::PROGRESS_DEVICE_GRAPH);
				$templates = db_fetch_assoc_prepared('SELECT *
					FROM host_graph
					WHERE host_id = ?',
					array($host_id));

				if (cacti_sizeof($templates)) {
					log_install_always('', 'Creating Graphs for Default Device');
					foreach($templates as $template) {
						set_config_option('install_updated', microtime(true));
						automation_execute_graph_template($host_id, $template['graph_template_id']);
					}

					$this->setProgress(Installer::PROGRESS_DEVICE_TREE);
					log_install_always('', 'Adding Device to Default Tree');
					shell_exec(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/cli/add_tree.php' .
						' --type=node' .
						' --node-type=host' .
						' --tree-id=1' .
						' --host-id=' . $host_id);
				} else {
					log_install_always('', 'No templated graphs for Default Device were found');
				}
			}
		} else {
			log_install_always('', 'WARNING: Device Template for your Operating System Not Found.  You will need to import Device Templates or Cacti Packages to monitor your Cacti server.', 'INSTALL:');
		}

		/* just in case we have hard drive graphs to deal with */
		$host_id = db_fetch_cell("SELECT id FROM host WHERE hostname='127.0.0.1'");

		if (!empty($host_id)) {
			log_install_always('', 'Running first-time data query for local host');
		        run_data_query($host_id, 6);
		}

		/* it's always a good idea to re-populate
		 * the poller cache to make sure everything
		 * is refreshed and up-to-date */
		set_config_option('install_updated', microtime(true));
		log_install_always('', 'Repopulating poller cache');
		repopulate_poller_cache();

		/* fill up the snmpcache */
		set_config_option('install_updated', microtime(true));
		log_install_always('', 'Repopulating SNMP Agent cache');
		snmpagent_cache_rebuilt();

		/* generate RSA key pair */
		set_config_option('install_updated', microtime(true));
		log_install_always('', 'Generating RSA Key Pair');
		rsa_check_keypair();

		$this->setProgress(Installer::PROGRESS_DEVICE_END);
		return '';
	}

	private function convertDatabase() {
		global $config;

		$tables = db_fetch_assoc("SELECT value FROM settings WHERE name like 'install_table_%'");
		if (cacti_sizeof($tables)) {
			log_install_always('', sprintf('Found %s tables to convert', cacti_sizeof($tables)));
			$this->setProgress(Installer::PROGRESS_TABLES_BEGIN);
			$i = 0;
			foreach ($tables as $key => $table) {
				$i++;
				$name = $table['value'];
				if (!empty($name)) {
					log_install_always('', sprintf('Converting Table #%s \'%s\'', $i, $name));
					$results = shell_exec(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/cli/convert_tables.php' .
						' --table=' . cacti_escapeshellarg($name) .
						' --utf8 --innodb');

					set_config_option('install_updated', microtime(true));
					log_install_debug('convert', sprintf('Convert table #%s \'%s\' results: %s', $i, $name, $results));
					if ((stripos($results, 'Converting table') !== false && stripos($results, 'Successful') !== false) ||
					    stripos($results, 'Skipped table') !== false) {
						set_config_option($key, '');
					}
				}
			}
		} else {
			log_install_always('', sprintf('No tables where found or selected for conversion'));
		}
	}

	private function upgradeDatabase() {
		global $cacti_version_codes, $config, $cacti_upgrade_version, $database_statuses, $database_upgrade_status;
		$failure = DB_STATUS_SKIPPED;

		$cachePrev = read_config_option('install_cache_db', true);
		$cacheFile = tempnam(sys_get_temp_dir(), 'cdu');

		log_install_always('', 'Switched from ' . $cachePrev . ' to ' . $cacheFile);
		set_config_option('install_cache_db', $cacheFile);

		$database_upgrade_status = array('file' => $cacheFile);
		log_install_always('', 'NOTE: Using temporary file for db cache: ' . $cacheFile);

		$prev_cacti_version = $this->old_cacti_version;
		$orig_cacti_version = get_cacti_cli_version();

		// loop through versions from old version to the current, performing updates for each version in the chain
		foreach ($cacti_version_codes as $cacti_upgrade_version => $hash_code)  {
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
				log_install_always('', 'Upgrading from v' . $prev_cacti_version .' (DB ' . $orig_cacti_version . ') to v' . $cacti_upgrade_version);

				include_once($upgrade_file);
				if (function_exists($upgrade_function)) {
					call_user_func($upgrade_function);
					echo PHP_EOL;
					$ver_status = $this->checkDatabaseUpgrade($cacti_upgrade_version);
				} else {
					log_install_always('', 'WARNING: Failed to find upgrade function for v' . $cacti_upgrade_version);
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

		if (cacti_version_compare($orig_cacti_version, $cacti_upgrade_version, '<')) {
			db_execute("UPDATE version SET cacti = '" . $cacti_upgrade_version . "'");
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
						$this->addError(Installer::STEP_ERROR, 'DB:'.$cacti_upgrade_version, 'FAIL: ' . $cache_item['sql']);
					}
				}
			}
		}

		return $failure;
	}

	private function disableInvalidPlugins() {
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
			'plugin', 'version'
		);

		if (cacti_sizeof($plugins)) {
			foreach ($plugins as $plugin => $version) {
				$disable = true;
				$integrated = in_array($plugin, $plugins_integrated);

				set_config_option('install_updated', microtime(true));

				if (is_dir($config['base_path'] . '/plugins/' . $plugin)
					&& file_exists($config['base_path'] . "/plugins/$plugin/setup.php")
					&& file_exists($config['base_path'] . "/plugins/$plugin/INFO")
					&& !$integrated) {
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
				db_execute_prepared('DELETE FROM plugin_config
					WHERE directory = ?',
					array($plugin));
			}
		}
	}
}

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

	public function jsonSerialize() {
		return array(
			'Text' => $this->Text,
			'Step' => $this->Step,
			'Enabled' => $this->Enabled,
			'Visible' => $this->Visible
		);
	}
}
