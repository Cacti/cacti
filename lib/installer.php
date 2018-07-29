<?php
include(dirname(__FILE__) . '/../lib/poller.php');

function log_install_and_cacti($string) {
	cacti_log($string, false, 'INSTALL:');
	log_install('', $string);
}

function log_install($filename, $data, $flags = FILE_APPEND) {
	global $config;
	$can_log = read_config_option('log_install','');
	if (!empty($can_log)) {
		$logname = 'install';
		if (!empty($filename)) {
			$logname .= "-$filename";
		}
		file_put_contents($config['base_path'] . "/log/$logname.log", $data . PHP_EOL, $flags);
	}
}

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

	private $templates;
	private $rrdVersion;
	private $paths;
	private $theme;
	private $locales;
	private $language;
	private $tables;

	private $buttonNext = null;
	private $buttonPrevious = null;
	private $buttonTest = null;

	public function __construct($installData = array()) {
		$this->old_cacti_version = get_cacti_version();

		$step = read_config_option('install_step', true);
		log_install('step', 'Initial: ' . var_export($step, true));
		if ($step === false || $step === null) {
			log_install('step', "Resetting to STEP_WELCOME as not found");
			$step = Installer::STEP_WELCOME;
			set_config_option('install_step', $step);
			$installData = array();
		} elseif ($step == Installer::STEP_INSTALL) {
			$install_version = read_config_option('install_version',true);
			log_install('step', 'Previously complete: ' . var_export($install_version, true));
			if ($install_version === false || $install_version === null) {
				$install_version = $this->old_cacti_version;
			}

			$install_error = read_config_option('install_error', true);
			if (!empty($install_error)) {
				$step = Installer::STEP_ERROR;
				$installData = array();
			} elseif (cacti_version_compare(CACTI_VERSION, $install_version, '==')) {
				log_install('step', 'Does match: ' . var_export($this->old_cacti_version, true));
				$step = Installer::STEP_COMPLETE;
				$installData = array();
			}
		} elseif ($step >= Installer::STEP_COMPLETE) {
			$install_version = read_config_option('install_version',true);
			log_install('step', 'Previously complete: ' . var_export($install_version, true));
			if ($install_version === false || $install_version === null) {
				$install_version = CACTI_VERSION;
			}

			if (!cacti_version_compare($this->old_cacti_version, $install_version, '==')) {
				log_install('step', 'Does not match: ' . var_export($this->old_cacti_version, true));
				$step = Installer::STEP_WELCOME;
				db_execute('DELETE FROM settings WHERE name LIKE \'install_%\'');
				$installData = array();
			}
		}
		log_install('step', 'After: ' . var_export($step, true) . PHP_EOL);

		$this->eula            = read_config_option('install_eula', true);
		$this->templates       = $this->getTemplates();
		$this->tables          = $this->getTables();
		$this->locales         = get_installed_locales();
		$this->language        = read_user_setting('user_language', get_new_user_default_language(), true);
		$this->paths           = install_file_paths();
		$this->errors          = array();
		$this->theme           = (isset($_SESSION['install_theme']) ? $_SESSION['install_theme']:read_config_option('selected_theme', true));
		$this->profile         = read_config_option('install_profile', true);

		$this->automationMode  = read_config_option('install_automation_mode', true);
		$this->cronInterval    = read_config_option('cron_interval', true);

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

		if ($this->automationMode === false || $this->automationMode === null) {
			$this->setAutomationMode($this->getAutomationNetworkMode());
		}

		$this->automationRange = read_config_option('install_automation_range', true);
		if (empty($this->automationRange)) {
			$this->setAutomationRange($this->getAutomationNetworkRange());
		}

		if ($this->theme === false || $this->theme === null) {
			$this->setTheme('modern');
		}

		set_config_option('selected_theme', $this->theme);

		$this->rrdVersion = read_config_option('rrdtool_version', true);

		$mode = read_config_option('install_mode', true);
		if ($mode === false || $mode === null) {
			$mode = Installer::MODE_INSTALL;
			if ($this->old_cacti_version != 'new_install') {
				if (cacti_version_compare($this->old_cacti_version, CACTI_VERSION, '<')) {
					$mode = Installer::MODE_UPGRADE;
				} else {
					$mode = Installer::MODE_DOWNGRADE;
				}
			} elseif ($this->hasRemoteDatabaseInfo()) {
				$mode = Installer::MODE_POLLER;
			}
		}

		$this->stepData = null;

		$this->setMode($mode);
		$this->setStep($step);

		if (!empty($installData)) {
			$this->processData($installData);
		}
		log_install('step', 'Done: ' . var_export($this->stepCurrent, true) . PHP_EOL);
	}

	protected function processData($initialData = array()) {
		if (empty($initialData) || !is_array($initialData)) {
			$initialData = array();
		}

		// Always set the current step first
		if (isset($initialData['Step'])) {
			$this->setStep($initialData['Step']);
			unset($initialData['Step']);
		}

		foreach ($initialData as $key => $value) {
			switch ($key) {
				case 'Mode':
					$this->setMode($value);
					break;
				case 'Eula':
					$this->eula = intval($value);
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
				case 'RRDVer':
					$this->setRRDVersion($value);
					break;
				case 'Theme':
					$this->setTheme($value);
					break;
				default:
					log_install('badkey',"$key => $value");
			}
		}
	}

	public function jsonSerialize() {
		$output = $this->processCurrentStep();

		if (isset($this->stepData)) {
			if (!isset($this->stepData['Theme'])) {
				$this->stepData['Theme'] = $this->theme;
			}
		}

		return array(
			'Mode'     => $this->mode,
			'Step'     => $this->stepCurrent,
			'Eula'     => $this->eula,
			'Prev'     => $this->buttonPrevious,
			'Next'     => $this->buttonNext,
			'Test'     => $this->buttonTest,
			'Html'     => $output,
			'StepData' => $this->stepData,
			'RRDVer'   => $this->rrdVersion,
			'Theme'    => $this->theme,
			'Language' => $this->language
		);
	}

	public function getData() {
		return $this->jsonSerialize();
	}

	private function setProgress($param_process) {
		log_install('', "Progress: $param_process");
		set_config_option('install_progress', $param_process);
	}

	private function setLanguage($param_language = '') {
		if (isset($param_language) && strlen($param_language)) {
			$this->language = $param_language;
			set_user_setting('user_language', $param_language);
		}
	}

	private function setRRDVersion($param_rrdver = '') {
		global $config;
		if (isset($param_rrdver) && strlen($param_rrdver)) {
			$this->rrdver = $param_rrdver;
			set_config_option('rrdtool_version', $this->rrdver);
		}
	}

	private function setTheme($param_theme = '') {
		global $config;
		if (isset($param_theme) && strlen($param_theme)) {
			$themePath = $config['base_path'] . '/include/themes/' . $param_theme . '/main.css';
			if (file_exists($themePath)) {
				$this->theme = $param_theme;
				set_config_option('selected_theme', $this->theme);
			}
		}
	}

	private function setPaths($param_paths = array()) {
		global $config;
		if (is_array($param_paths)) {
			$input = install_file_paths();
			log_install('paths', "setPaths($this->stepCurrent)");

			$this->errors = array();
			/* get all items on the form and write values for them  */
			foreach ($input as $name => $array) {
				if (isset($param_paths[$name])) {
					if ($name == 'path_php_binary') {
						$input = mt_rand(1,64);
						$output = shell_exec(
							$param_paths[$name] . ' -q ' . $config['base_path'] .
							'/install/cli_test.php ' . $input);

						if ($output != $input * $input) {
							$this->errors[$name] = 'PHP did not return expected result';
						}
					}

					$should_set = !isset($this->errors[$name]);
					if ($should_set && $name != 'path_cactilog' && $name != 'path_spine') {
						$should_set = file_exists($param_paths[$name]);
						if (!$should_set) {
							$this->errors[$name] = 'File not found';
						}
					}

					log_install('paths', "$name => $param_paths[$name], $should_set");
					set_config_option($name, $param_paths[$name]);
				}
			}

			if (sizeof($this->errors) && $this->stepCurrent > Installer::STEP_BINARY_LOCATIONS) {
				$this->setStep(Installer::STEP_BINARY_LOCATIONS);
			}
		}
	}

	private function setProfile($param_profile = null) {
		if (!empty($param_profile)) {
			$this->profile = $param_profile;
			set_config_option('install_profile', $param_profile);
		}
	}

	private function setAutomationMode($param_mode = null) {
		if ($param_mode != null) {
			$this->automationMode = $param_mode;
			set_config_option('install_automation_mode', $param_mode);
		}
		log_install('automation',"setAutomationMode($param_mode) returns with $this->automationMode");
	}

	private function setCronInterval($param_mode = null) {
		if ($param_mode != null) {
			$this->cronInterval = $param_mode;
			set_config_option('cron_interval', $param_mode);
		}
		log_install('automation',"setCronInterval($param_mode) returns with $this->cronInterval");
	}

	private function setAutomationRange($param_range = null) {
		if (!empty($param_range)) {
			$this->automationRange = $param_range;
			set_config_option('install_automation_range', $param_range);
		}
		log_install('automation',"setAutomationRange($param_range) returns with $this->automationRange");
	}

	private function getTemplates() {
		$known_templates = install_setup_get_templates();
		$db_templates = db_fetch_assoc('SELECT name, value FROM settings where name like \'install_template_%\'');
		$hasTemplates = read_config_option('install_has_templates', true);
		$selected = array();
		$select_count = 0;
		foreach ($known_templates as $known) {
			$filename = $known['filename'];
			$key = str_replace(".", "_", $filename);
			$isSelected = !empty($hasTemplates) && (read_config_option('install_template_' . $key) == $filename);
			$selected['chk_template_' . $key] = $isSelected;
			if ($isSelected) {
				$select_count++;
			}
		}
		$selected['all'] = ($select_count == count($selected) || empty($hasTemplates));

		return $selected;
	}

	private function setTemplates($param_templates = array()) {
		if (is_array($param_templates)) {
			db_execute('DELETE FROM settings WHERE name like \'install_template_%\'');
			$known_templates = install_setup_get_templates();
			log_install('templates',"Updating templates");
			log_install('templates',"Parameter data:" . PHP_EOL . var_export($param_templates, true) . PHP_EOL);
			foreach ($known_templates as $known) {
				$filename = $known['filename'];
				$key = 'chk_template_' . str_replace(".", "_", $filename);
				log_install('templates',"Checking template file $filename against key $key ...");
				log_install('templates',"Template data: ".str_replace("\n"," ", var_export($known, true)));
				if (!empty($param_templates[$key])) {
					$template = $param_templates[$key];
					log_install('templates',"Template enabled:" . var_export($template, true));
					log_install('templates',"Set template: install_template_$key = $filename");
					$key = str_replace(".", "_", $filename);
					set_config_option("install_template_$key", $filename);
				}
			}

			set_config_option('install_has_templates', true);
		}
	}

	private function getTables() {
		$known_tables = install_setup_get_tables();
		$db_tables = db_fetch_assoc('SELECT name, value FROM settings where name like \'install_table_%\'');
		$hasTables = read_config_option('install_has_tables', true);
		$selected = array();
		$select_count = 0;
		foreach ($known_tables as $known) {
			$table = $known['Name'];
			$key = $known['Name'];
			$option = read_config_option('install_table_' . $key);
			$isSelected = !empty($hasTables) && (!empty($option));
			$selected['chk_table_' . $key] = $isSelected;
			if ($isSelected) {
				$select_count++;
			}
		}
		$selected['all'] = ($select_count == count($selected) || empty($hasTables));

		return $selected;
	}

	private function setTables($param_tables = array()) {
		if (is_array($param_tables)) {
			db_execute('DELETE FROM settings WHERE name like \'install_table_%\'');
			$known_tables = install_setup_get_tables();
			log_install('tables',"Updating Tables");
			log_install('tables',"Parameter data:" . PHP_EOL . var_export($param_tables, true) . PHP_EOL);
			foreach ($known_tables as $known) {
				$name = $known['Name'];
				$key = 'chk_table_' . $name;
				log_install('tables',"Checking table '$name' against key $key ...");
				log_install('tables',"Table: ".str_replace("\n"," ", var_export($known, true)));
				if (!empty($param_tables[$key])) {
					$table = $param_tables[$key];
					log_install('tables',"Set table: install_table_$name = $name");
					set_config_option("install_table_$name", $name);
				}
			}

			set_config_option('install_has_tables', true);
		}
	}

	private function setMode($param_mode = -1) {
		if (intval($param_mode) > Installer::MODE_NONE && intval($param_mode) <= Installer::MODE_DOWNGRADE) {
			set_config_option('install_mode', $param_mode);
			$this->mode = $param_mode;
			$this->updateButtons();
		}
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
			$step = $param_step;
		}

		if ($step == Installer::STEP_NONE) {
			$step == Installer::STEP_WELCOME;
		}

		log_install('step', 'setStep: ' . var_export($step, true));
		log_install('step', "setStep:" . PHP_EOL . var_export(debug_backtrace(0), true));

//		$install_version = read_config_option('install_version', true);
//		if ($install_version !== false) {
//			if ($install_version == CACTI_VERSION && $step == Installer::STEP_INSTALL) {
//				$step = Installer::STEP_COMPLETE;
//			}
//		}

		// Make current step the first if it is unknown
		$this->stepCurrent  = ($step == Installer::STEP_NONE ? Installer::STEP_WELCOME : $step);
		$this->stepPrevious = Installer::STEP_NONE;
		$this->steNext      = Installer::STEP_NONE;
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
		return (is_writable($config['base_path'] . '/include/config.php') ? true : false);
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
		$output .= Installer::sectionNormal(__('An unexpected reason was given for preventing this maintainence session.'));
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
		$output .= Installer::sectionNormal(__('NOTE: Your existing data will not be modified, nor will it or any history be available to to the new install'));
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

		return Installer::section('<span class="cactiInstallSectionNoteTitle">' . __('NOTE:') . '</span> ' . $text, $id, trim($class), '', 'p');
	}

	public static function sectionWarning($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionWarning';

		return Installer::section('<span class="cactiInstallSectionWarningTitle">' . __('WARNING:') . '</span> ' . $text, $id, trim($class), '', 'p');
	}

	public static function sectionError($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionError';

		return Installer::section('<span class="cactiInstallSectionErrorTitle">' . __('ERROR:') . '</span> ' . $text, $id, trim($class), '', 'p');
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
			if (count($flags) > 1) {
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
		$output .= Installer::sectionNormal('<span>' . __('Select default theme: ') . $themeOutput . '</span><span style=\'float: right\'><input type=\'checkbox\' id=\'accept\' name=\'accept\'><label for=\'accept\'>' . __('Accept GPL License Agreement') . '</label></span><span>'.$langOutput.'</span>');

		$this->stepData = array('Eula' => $this->eula);
		$this->buttonNext->Enabled = ($this->eula == 1);

		return $output;
	}

	public function processStepCheckDependancies() {
		global $config;
		global $database_default, $database_username, $database_port;
		global $rdatabase_default, $rdatabase_username, $rdatabase_port;

		$enabled = array(
			'location' => 1,
			'php_timezone' => 1,
			'php_modules' => 1,
			'php_optional' => 1,
			'mysql_timezone' => 1,
			'mysql_performance' => 1
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
			$enabled['location'] = 0;
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

		$enabled['php_modules'] = version_compare(PHP_VERSION, '5.4.0', '<') ? 0 : 1;
		form_alternate_row('phpline',true);
		form_selectable_cell(__('PHP Version'), '');
		form_selectable_cell('5.4.0+', '');
		form_selectable_cell((version_compare(PHP_VERSION, '5.4.0', '<') ? "<font color=red>" . PHP_VERSION . "</font>" : "<font color=green>" . PHP_VERSION . "</font>"), '');
		form_end_row();

		$output .= Installer::sectionNormal(ob_get_contents());
		ob_clean();

		if ($config['cacti_server_os'] == 'unix') {
			$extensions = array(
				array('name' => 'posix',     'installed' => false),
				array('name' => 'session',   'installed' => false),
				array('name' => 'sockets',   'installed' => false),
				array('name' => 'PDO',       'installed' => false),
				array('name' => 'pdo_mysql', 'installed' => false),
				array('name' => 'xml',       'installed' => false),
				array('name' => 'ldap',      'installed' => false),
				array('name' => 'mbstring',  'installed' => false),
				array('name' => 'pcre',      'installed' => false),
				array('name' => 'json',      'installed' => false),
				array('name' => 'openssl',   'installed' => false),
				array('name' => 'gd',        'installed' => false),
				array('name' => 'zlib',      'installed' => false)
			);
		} elseif (version_compare(PHP_VERSION, '5.4.5') < 0) {
			$extensions = array(
				array('name' => 'session',   'installed' => false),
				array('name' => 'sockets',   'installed' => false),
				array('name' => 'PDO',       'installed' => false),
				array('name' => 'pdo_mysql', 'installed' => false),
				array('name' => 'xml',       'installed' => false),
				array('name' => 'ldap',      'installed' => false),
				array('name' => 'mbstring',  'installed' => false),
				array('name' => 'pcre',      'installed' => false),
				array('name' => 'json',      'installed' => false),
				array('name' => 'openssl',   'installed' => false),
				array('name' => 'gd',        'installed' => false),
				array('name' => 'zlib',      'installed' => false)
			);
		} else {
			$extensions = array(
				array('name' => 'com_dotnet','installed' => false),
				array('name' => 'session',   'installed' => false),
				array('name' => 'sockets',   'installed' => false),
				array('name' => 'PDO',       'installed' => false),
				array('name' => 'pdo_mysql', 'installed' => false),
				array('name' => 'xml',       'installed' => false),
				array('name' => 'ldap',      'installed' => false),
				array('name' => 'mbstring',  'installed' => false),
				array('name' => 'pcre',      'installed' => false),
				array('name' => 'json',      'installed' => false),
				array('name' => 'openssl',   'installed' => false),
				array('name' => 'gd',        'installed' => false),
				array('name' => 'zlib',      'installed' => false)
			);
		}

		$ext = verify_php_extensions($extensions);
		foreach ($ext as $id =>$e) {
			form_alternate_row('line' . $id);
			form_selectable_cell($e['name'], '');
			form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
			form_selectable_cell(($e['installed'] ? '<font color=green>' . __('Yes') . '</font>' : '<font color=red>' . __('No') . '</font>'), '');
			form_end_row();

			if (!$e['installed']) $enabled['php_modules'] = 0;
		}

		html_end_box(false);

		$output .= Installer::sectionNormal(ob_get_contents());
		ob_clean();

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('PHP - Module Support (Optional)'), 'php_optional');

		$output .= Installer::sectionNormal(__('The following PHP extensions are recommended, and should be installed before continuing your Cacti install.'));
		$extensions = array(
			array('name' => 'snmp', 'installed' => false),
			array('name' => 'gmp', 'installed' => false)
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
			form_selectable_cell(($e['installed'] ? '<font color=green>' . __('Yes') . '</font>' : '<font color=red>' . __('No') . '</font>'), '');
			form_end_row();

			if (!$e['installed']) $enabled['php_optional'] = 0;
		}

		html_end_box();

		$output .= Installer::sectionNormal(ob_get_contents());
		ob_clean();

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('PHP - Timezone Support'), 'php_timezone');
		if (ini_get('date.timezone') == '') {
			$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your Web Servers PHP Timezone settings have not been set.  Please edit php.ini and uncomment the \'date.timezone\' setting and set it to the Web Servers Timezone per the PHP installation instructions prior to installing Cacti.') . '</span>');
			$enabled['php_timezone'] = 0;
		} else {
			$output .= Installer::sectionNormal(__('Your Web Servers PHP is properly setup with a Timezone.'));
		}

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('MySQL - TimeZone Support'), 'mysql_timezone');
		$mysql_timezone_access = db_fetch_assoc('SHOW COLUMNS FROM mysql.time_zone_name', false);
		if (sizeof($mysql_timezone_access)) {
			$timezone_populated = db_fetch_cell('SELECT COUNT(*) FROM mysql.time_zone_name');
			if (!$timezone_populated) {
				$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your MySQL TimeZone database is not populated.  Please populate this database before proceeding.') . '</span>');
				$enabled['mysql_timezone'] = 0;
			}
		} else {
			$output .= Installer::sectionNormal('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your Cacti database login account does not have access to the MySQL TimeZone database.  Please provide the Cacti database account "select" access to the "time_zone_name" table in the "mysql" database, and populate MySQL\'s TimeZone information before proceeding.') . '</span>');
			$enabled['mysql_timezone'] = 0;
		}

		if ($enabled['mysql_timezone'] == 1) {
			$output .= Installer::sectionNormal(__('Your Cacti database account has access to the MySQL TimeZone database and that database is populated with global TimeZone information.'));
		}

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('MySQL - Settings'), 'mysql_performance');
		$output .= Installer::sectionNormal(__('These MySQL performance tuning settings will help your Cacti system perform better without issues for a longer time.'));

		html_start_box(__('Recommended MySQL System Variable Settings'), '100%', false, '3', '', false);
		$output_temp = ob_get_contents();
		ob_clean();

		utilities_get_mysql_recommendations();
		$output_util = ob_get_contents();
		ob_clean();

		html_end_box(false);

		$output .= Installer::sectionNormal($output_temp . $output_util . ob_get_contents());
		ob_end_clean();

		$output .= Installer::sectionSubTitleEnd();

		$this->stepData = array('Sections' => $enabled);
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
				$output .= Installer::sectionWarning(__('If you are upgrading from a previous version please close all Cacti browser sessions and clear cache before continuing.  Additionally, after this script is complete, you will also have to refresh your page to load updated JavaScript so that the Cacti pages render properly.  In Firefox and IE, you simply press F5.  In Chrome, you may have to clear your browser cache for the Cacti web site.'));
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
		print '<table width="100%"><tr><td colspan="2"><center><hr></center></td></tr>';
		$input = install_file_paths();

		/* find the appropriate value for each 'config name' above by config.php, database,
		 * or a default for fall back */
		foreach ($input as $name => $array) {
			if (isset($array)) {
				$current_value = $array['default'];

				/* run a check on the path specified only if specified above, then fill a string with
				the results ('FOUND' or 'NOT FOUND') so they can be displayed on the form */
				$form_check_string = '';

				/* draw the acual header and textbox on the form */
				print '<tr><td><strong>' . $array['friendly_name'] . '</strong></td><td>';

				switch ($array['method']) {
					case 'textbox':
						form_text_box($name, $current_value, '', '', '40', 'text');
						break;
					case 'filepath':
						form_filepath_box($name, $current_value, '', '', '40', 'text');
						break;
					case 'drop_array':
						form_dropdown($name, $array['array'], '', '', $current_value, '', '');
						break;
				}

				print '</td></tr>';
				if (!empty($array['description'])) {
					print '<tr><td>&nbsp;</td><td>' . $array['description'] . '</td></tr>';
				}

				if (isset($this->errors[$name])) {
					print '<tr><td>&nbsp;</td><td>' . Installer::sectionError(__($this->errors[$name])) . '</td></tr>';
				}
				print '<tr><td colspan="2"><center><hr></center></td></tr>';
			}

			$i++;
		}
		$this->stepData = array('Errors' => $this->errors);
		print '</table>';
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
			$install_paths = array(
				$config['base_path'] . '/resource/snmp_queries',
				$config['base_path'] . '/resource/script_server',
				$config['base_path'] . '/resource/script_queries',
				$config['base_path'] . '/scripts',
			);

			$output .= Installer::sectionSubTitle(__('Required Writable at Install Time Only'), 'writable_install');

			$sections['writable_install'] = 1;
			foreach($install_paths as $path) {
				if (is_writable($path)) {
					$output .= Installer::sectionNormal(__('<p>%s is <font color="#008000">Writable</font></p>', $path));
				} else {
					$output .= Installer::sectionNormal(__('<p>%s is <font color="#FF0000">Not Writable</font></p>', $path));
					$writable = false;
					$sections['writable_install'] = 0;
				}
			}
		}

		if ($this->mode == Installer::MODE_POLLER) {
			$always_paths = array(
				sys_get_temp_dir(),
				$config['base_path'] . '/resource/snmp_queries',
				$config['base_path'] . '/resource/script_server',
				$config['base_path'] . '/resource/script_queries',
				$config['base_path'] . '/scripts',
				$config['base_path'] . '/log',
				$config['base_path'] . '/cache/boost',
				$config['base_path'] . '/cache/mibcache',
				$config['base_path'] . '/cache/realtime',
				$config['base_path'] . '/cache/spikekill'
			);
		} else {
			$always_paths = array(
				sys_get_temp_dir(),
				$config['base_path'] . '/log',
				$config['base_path'] . '/cache/boost',
				$config['base_path'] . '/cache/mibcache',
				$config['base_path'] . '/cache/realtime',
				$config['base_path'] . '/cache/spikekill'
			);
		}

		$output .= Installer::sectionSubTitleEnd();

		$output .= Installer::sectionSubTitle(__('Required Writable after Install Complete'),'writable_always');
		$sections['writable_always'] = 1;
		foreach($always_paths as $path) {
			if (is_writable($path)) {
				$output .= Installer::sectionNormal(__('<p>%s is <font color="#008000">Writable</font></p>', $path));
			} else {
				$output .= Installer::sectionNormal(__('<p>%s is <font color="#FF0000">Not Writable</font></p>', $path));
				$sections['writable_always'] = 0;
				$writable = false;
			}
		}

		/* Print help message for unix and windows if directory is not writable */
		if (($config['cacti_server_os'] == 'unix') && isset($writable)) {
			$output .= Installer::sectionSubTitleEnd();

			$output .= Installer::sectionSubTitle(__('Ensure Host Process Has Access'));
			$output .= Installer::sectionNormal(__('Make sure your webserver has read and write access to the entire folder structure.'));
			$output .= Installer::sectionNormal(__('Example:'));
			$output .= Installer::sectionCode(__('chown -R apache.apache %s/resource/', $config['base_path']));
			$output .= Installer::sectionNormal(__('For SELINUX-users make sure that you have the correct permissions or set \'setenforce 0\' temporarily.'));
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

		if (sizeof($profiles)) {
			$output  = Installer::sectionTitle(__('Default Profile'));
			$output .= Installer::sectionNormal(__('Please select the default Data Source Profile to be used for polling sources.  This is the maximum amount of time between scanning devices for information so the lower the polling interval, the more work is placed on the Cacti Server host.  Also, select the intended, or configured Cron interval that you wish to use for Data Collection.'));

			$html = '<p>' . __('Default Profile:') . '<br/><select id="default_profile" name="default_profile">';
			foreach ($profiles as $profile) {
				$selectedProfile = '';
				$suffix = '';

				if ($profile['default'] == 'on') {
					if ($this->profile === false || $this->profile === null) {
						$this->setProfile($profile['id']);
					}
					$suffix = ' (default)';
				}

				if ($profile['id'] == $this->profile) {
					$selectedProfile = ' selected';
				}

				$html .= '<option value="' . $profile['id'] .'"' . $selectedProfile . '>';
				$html .= $profile['name'] . $suffix;
				$html .= '</option>';
			}
			$html .= '</select></p>';

			ob_start();
			$current_value = $this->cronInterval;
			print '<p>' . __('Cron Interval:') . '<br />';
			form_dropdown('cron_interval', $cron_intervals, '', '', $current_value, '', '');
			print '</p>';

			$html .= ob_get_contents();
			ob_end_clean();

			$output .= Installer::sectionNormal($html);

			$output .= Installer::sectionTitle(__('Default Automation Network'));
			$output .= Installer::sectionNormal(__('Please enter the default automation network range to be scanned when enabled. To enable scanning on installation, please select \'Enabled\' below'));
			$output .= Installer::sectionNote(__('When enabled, scanning will be set to daily by default'));

			ob_start();
			$current_value = $this->automationMode;
			print '<p>' . __('Mode:') . '<br />';
			form_dropdown('automation_mode', array(__('Disabled'), __('Enabled')), '', '', $current_value, '', '');
			print '</p>';

			$current_value = $this->automationRange;
			print '<p>' . __('Network Range:') . '<br />';
			form_text_box('automation_range', $current_value, '', '', '40', 'text');
			print '</p>';

			$html = ob_get_contents();
			ob_end_clean();

			$output .= Installer::sectionNormal($html);
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
		if (sizeof($collation_vars)) {
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

		if (sizeof($tables)) {
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
		$output  = Installer::sectionTitle(__('Installing Cacti Server v%s', CACTI_VERSION));
		$output .= Installer::sectionNormal(__('Your Cacti Server is now installing'));
		$output .= Installer::sectionNormal(
			'<table width="100%"><tr>' .
				'<td class="cactiInstallProgressLeft">Refresh in</td>' .
				'<td class="cactiInstallProgressCenter">&nbsp;</td>' .
				'<td class="cactiInstallProgressRight">Progress</td>' .
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

		log_install('', 'backgroundTime = ' . $backgroundTime, 0);
		log_install('', 'backgroundNeeded = ' . $backgroundNeeded);

		// Check if background started too long ago
		if (!$backgroundNeeded) {
			log_install('', PHP_EOL . '----------------' . PHP_EOL . 'Check Expire' . PHP_EOL . '----------------');

			$backgroundDateStarted = DateTime::createFromFormat('U.u', $backgroundTime);
			$backgroundLast = read_config_option('install_updated', true);

			log_install('', 'backgroundDateStarted = ' . $backgroundDateStarted->format('Y-m-d H:i:s') . PHP_EOL);
			log_install('', 'backgroundLast = ' . $backgroundTime);
			if ($backgroundLast === false || $backgroundLast < $backgroundTime) {
				log_install('', 'backgroundLast = ' . $backgroundTime . " (Replaced)");
				$backgroundLast = $backgroundTime;
			}

			$backgroundExpire = time() - 300;
			log_install('', 'backgroundExpire = ' . $backgroundExpire);

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

				log_install('', PHP_EOL . '=======' . PHP_EOL . 'Expired' . PHP_EOL . '=======' . PHP_EOL);
				log_install('', '         newTime = ' . $newTime);
				log_install('', '  backgroundTime = ' . $backgroundTime);
				log_install('', '  backgroundLast = ' . $backgroundLast);
				log_install('', 'backgroundNeeded = ' . $backgroundNeeded);
			}
		}

		if ($backgroundNeeded) {
			$php = read_config_option('path_php_binary', true);
			$php_file = $config['base_path'] . '/install/background.php ' . $backgroundTime;

			log_install_and_cacti('Spawning background process: ' . $php . ' ' . $php_file);
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
		global $cacti_version_codes;

		//db_execute_prepared("UPDATE version SET cacti = ?", array(CACTI_VERSION));
		//set_config_option('install_version', CACTI_VERSION);

		if ($this->stepCurrent == Installer::STEP_COMPLETE) {
			$output = Installer::sectionTitle(__('Complete'));
			$output .= Installer::sectionNormal(__('Your Cacti Server v%s has been installed/updated.  You may now start using the software.', CACTI_VERSION));
		} elseif ($this->stepCurrent == Installer::STEP_ERROR) {
			$output = Installer::sectionTitleError();
			$output .= Installer::sectionNormal(__('Your Cacti Server v%s has been installed/updated with errors', CACTI_VERSION));
		}

		$output .= Installer::sectionSubTitleEnd();

		$sections = array();
		$cacheFile = read_config_option('install_cache_db');
		if (!empty($cacheFile)) {
			$cacti_versions = array_keys($cacti_version_codes);

			$sqltext = array(
				0 => __('[Fail]'),
				1 => __('[Success]'),
				2 => __('[Skipped]'),
			);

			$sqlclass = array(
				0 => 'cactiInstallSqlFailure',
				1 => 'cactiInstallSqlSuccess',
				2 => 'cactiInstallSqlSkipped',
			);

			$file = fopen($cacheFile, "r");
			$version_last = '';
			while (!feof($file)) {
				$change = fgets($file);
				$action = preg_split('~[ ]*<\[(version|status|sql)\]>[ ]*~i', $change);
				if (empty($action) || !sizeof($action)) {
					log_install('upgrade', "$cacheFile: Read unexpect change - '" . var_export($change, false) . "'");
				} else {
					$version = $action[1];
					if (!empty($version)) {
						if ($version != $version_last) {
							$version_last = $version;
							if (!empty($sectionId)) {
								$stepData[$sectionId] = $sectionStatus;
								$output .= '</table>';
							}

							$sectionId = str_replace(".", "_", $version);
							$output .= $this->sectionSubTitle('Database Upgrade - Version ' . $version, $sectionId);
							$output .= $this->sectionNormal('The following table lists the status of each upgrade performed on the database');
							$output .= '<table class=\'cactiInstallSqlResults\'>';

							$sectionStatus = 2;
						}

						// show results from version upgrade
						$output .= '<tr class=\'cactiInstallSqlRow\'>';
						$output .= '<td class=\'cactiInstallSqlLeft\'>' . $action[3] . '</td>';
						$output .= '<td class=\'cactiInstallSqlRight ' . $sqlclass[$action[2]] . '\'>' . $sqltext[$action[2]] . '</td>';
						$output .= '</td></tr>';

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

		$output .= $this->sectionSubTitle('Process Log');
		$output .= $this->getInstallLog();

		$this->buttonPrevious->Visible = false;
		$this->buttonNext->Enabled = true;

		if (isset($stepData)) {
			$this->stepData = array('Sections' => $stepData);
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

	public function processStepError() {
		$output = Installer::sectionTitleError(__('Failed'));
		$output .= Installer::sectionNormal(__('There was a problem during this process.  Please check the log below for more information'));
		$output .= $this->getInstallLog();
	}

	public function getAutomationNetworkMode() {
		$row = db_fetch_row('SELECT id, enabled FROM automation_networks LIMIT 1');
		$enabled = 0;
		if (!empty($row)) {
			if ($row['enabled'] == 'on') {
				$enabled = 1;
			}
		}
		log_install('automation',"getAutomationNetworkMode() returns '$enabled'");
		return $enabled;
	}

	public function getAutomationNetworkRange() {
		$row = db_fetch_row('SELECT id, subnet_range FROM automation_networks LIMIT 1');
		$enabled = 0;
		$network = '';
			log_install('automation', "getAutomationNetworkRange(): found '" .
				clean_up_lines(var_export($row, true)));
		if (!empty($row)) {
			$network = $row['subnet_range'];
		}
		$result = empty($network) ? '192.168.0.1/24' : $network;
		log_install('automation',"getAutomationNetworkRange() returns '$result'");
		return $result;
	}

	public function getInstallLog() {
		global $config;
		$logcontents = tail_file($config['base_path'] . '/log/cacti.log', 100, -1, 'INSTALL:' , 1, $total_rows);

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

	public function processBackgroundInstall() {
		global $config;
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

		log_install_and_cacti(sprintf('Starting %s Process for v%s', $which, CACTI_VERSION));

		$this->setProgress(Installer::PROGRESS_START);

		$this->convertDatabase();

		if (!$this->hasRemoteDatabaseInfo()) {
			$this->installTemplate();
		}

		$this->setProgress(Installer::PROGRESS_TEMPLATES_END);
		$failure = '';

		if ($this->mode == Installer::MODE_POLLER) {
			$failure = $this->installPoller();
		} else {
			if ($this->mode == Installer::MODE_INSTALL) {
				$failure = $this->installServer();
			} else if ($this->mode == Installer::MODE_UPGRADE) {
				$failure = $this->upgradeDatabase();
			}
			$this->disablePluginsNowIntegrated();
		}

		log_install_and_cacti(sprintf('Setting Cacti Version to %s', CACTI_VERSION));
		log_install_and_cacti(sprintf('Finished %s Process for v%s', $which, CACTI_VERSION));

		set_config_option('install_error',$failure);
		$this->setProgress(Installer::PROGRESS_VERSION_BEGIN);
		db_execute_prepared('UPDATE version SET cacti = ?', array(CACTI_VERSION));
		set_config_option('install_version', CACTI_VERSION);
		$this->setProgress(Installer::PROGRESS_VERSION_END);

		if (empty($failure)) {
			// No failures so lets update the version
			$this->setProgress(Installer::PROGRESS_COMPLETE);
			$this->setStep(Installer::STEP_COMPLETE);
		} else {
			log_install_and_cacti($failure);
			$this->setProgress(Installer::PROGRESS_COMPLETE);
			$this->setStep(Installer::STEP_ERROR);
		}
	}

	private function installTemplate() {
		global $config;
		$templates = db_fetch_assoc("SELECT value FROM settings WHERE name like 'install_template_%'");
		if (sizeof($templates)) {
			log_install_and_cacti(sprintf('Found %s templates to install', sizeof($templates)));
			$path = $config['base_path'] . '/install/templates/';

			$this->setProgress(Installer::PROGRESS_TEMPLATES_BEGIN);
			$i = 0;
			foreach ($templates as $template) {
				$i++;
				$package = $template['value'];
				log_install_and_cacti(sprintf('Attempting to import package #%s \'%s\'', $i, $package));
				import_package($path . $package, 1, false);
				$this->setProgress(Installer::PROGRESS_TEMPLATES_BEGIN + $i);
			}

			db_execute('TRUNCATE TABLE automation_templates');

			foreach($this->defaultAutomation as $item) {
				$host_template_id = db_fetch_cell_prepared('SELECT id
					FROM host_template
					WHERE hash = ?',
					array($item['hash']));

				if (!empty($host_template_id)) {
					log_install_and_cacti(sprintf('Mapping Automation Template for Device Template \'%s\'', $item['name']));

					db_execute_prepared('INSERT INTO automation_templates
						(host_template, availability_method, sysDescr, sysName, sysOid, sequence)
						VALUES (?, ?, ?, ?, ?, ?)',
						array($host_template_id, $item['availMethod'], $item['sysDescrMatch'],
						$item['sysNameMatch'], $item['sysOidMatch'], $item['sequence']));
				}
			}
		} else {
			log_install_and_cacti(sprintf('No templates were selected for import'));
		}
	}

	private function installPoller() {
		log_install_and_cacti('Updating remote configuration file');
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
		$profile    = db_fetch_row_prepared('SELECT id, name, step, heartbeat
			FROM data_source_profiles
			WHERE id = ?',
			array($profile_id));

		log_install('', "Profile ID: $profile_id" . PHP_EOL . 'Profile: ' . var_export($profile, true));

		if ($profile['id'] == $this->profile) {
			log_install_and_cacti(sprintf('Setting default data source profile to %s (%s)', $profile['name'], $profile['id']));
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
			log_install_and_cacti(sprintf('Failed to find selected profile (%s), no changes were made', $profile_id));
		}

		$this->setProgress(Installer::PROGRESS_PROFILE_END);

		$this->setProgress(Installer::PROGRESS_AUTOMATION_START);
		$automation_row = db_fetch_row('SELECT id, enabled, subnet_range FROM automation_networks ORDER BY id LIMIT 1');
		log_install('automation','Automation Row:' . PHP_EOL . var_export($automation_row, false) . PHP_EOL);
		if (!empty($automation_row)) {
			log_install_and_cacti(sprintf('Updating automation network (%s), mode "%s" => "%s", subnet "%s" => %s"'
				, $automation_row['id'], $automation_row['mode'], $this->automationMode ? 'on' : ''
				, $automation_row['subnet_range'], $this->automationRange));

			db_execute_prepared('UPDATE automation_networks SET
				subnet_range = ?,
				enabled = ?
				WHERE id = ?',
				array($this->automationRange, ($this->automationMode ? 'on' : ''), $automation_row['id']));
		} else {
			log_install_and_cacti('Failed to find automation network, no changes were made');
		}
		$this->setProgress(Installer::PROGRESS_AUTOMATION_START);

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
			log_install_and_cacti('Device Template for First Cacti Device is ' . $host_template_id);

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

				if (sizeof($templates)) {
					log_install_and_cacti('Creating Graphs for Default Device');
					foreach($templates as $template) {
						automation_execute_graph_template($host_id, $template['graph_template_id']);
					}

					$this->setProgress(Installer::PROGRESS_DEVICE_TREE);
					log_install_and_cacti('Adding Device to Default Tree');
					shell_exec(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/cli/add_tree.php' .
						' --type=node' .
						' --node-type=host' .
						' --tree-id=1' .
						' --host-id=' . $host_id);
				} else {
					log_install_and_cacti('No templated graphs for Default Device were found');
				}
			}
		} else {
			log_install_and_cacti('WARNING: Device Template for your Operating System Not Found.  You will need to import Device Templates or Cacti Packages to monitor your Cacti server.', 'INSTALL:');
		}

		/* just in case we have hard drive graphs to deal with */
		$host_id = db_fetch_cell("SELECT id FROM host WHERE hostname='127.0.0.1'");

		if (!empty($host_id)) {
			log_install_and_cacti('Running first-time data query for local host');
		        run_data_query($host_id, 6);
		}

		/* it's always a good idea to re-populate
		 * the poller cache to make sure everything
		 * is refreshed and up-to-date */
		log_install_and_cacti('Repopulating poller cache');
		repopulate_poller_cache();

		/* fill up the snmpcache */
		log_install_and_cacti('Repopulating SNMP Agent cache');
		snmpagent_cache_rebuilt();

		/* generate RSA key pair */
		log_install_and_cacti('Generating RSA Key Pair');
		rsa_check_keypair();

		$this->setProgress(Installer::PROGRESS_DEVICE_END);
		return '';
	}

	private function convertDatabase() {
		global $config;

		$tables = db_fetch_assoc("SELECT value FROM settings WHERE name like 'install_table_%'");
		if (sizeof($tables)) {
			log_install_and_cacti(sprintf('Found %s tables to convert', sizeof($tables)));
			$this->setProgress(Installer::PROGRESS_TABLES_BEGIN);
			$i = 0;
			foreach ($tables as $table) {
				$i++;
				$name = $table['value'];
				log_install_and_cacti(sprintf('Attempting to convert table #%s \'%s\'', $i, $name));
				$results = shell_exec(read_config_option('path_php_binary') . ' -q ' . $config['base_path'] . '/cli/convert_tables.php' .
					' --table=' . cacti_escapeshellarg($name) .
					' --utf8 --innodb');

				log_install_and_cacti(sprintf('Convert table #%s \'%s\' results: %s', $i, $name, $results));
			}
		} else {
			log_install_and_cacti(sprintf('No tables where found or selected for conversion'));
		}
	}

	private function upgradeDatabase() {
		global $cacti_version_codes, $config, $cacti_upgrade_version;
		$failure = '';

		set_config_option('install_cache_db', tempnam(sys_get_temp_dir(), 'cdu'));
		$temp = read_config_option('install_cache_db');
		log_install_and_cacti('NOTE: Using temporary file: ' . $temp);

		// loop through versions from old version to the current, performing updates for each version in the chain
		foreach ($cacti_version_codes as $cacti_upgrade_version => $hash_code)  {
			// skip versions old than the database version
			if (cacti_version_compare($this->old_cacti_version, $cacti_upgrade_version, '>=')) {
				//log_install_and_cacti('Skipping v' . $cacti_upgrade_version . ' upgrade');
				continue;
			}

			//log_install_and_cacti('Checking v' . $cacti_upgrade_version . ' upgrade routines');

			// construct version upgrade include path
			$upgrade_file = $config['base_path'] . '/install/upgrades/' . str_replace('.', '_', $cacti_upgrade_version) . '.php';
			$upgrade_function = 'upgrade_to_' . str_replace('.', '_', $cacti_upgrade_version);

			// check for upgrade version file, then include, check for function and execute
			if (file_exists($upgrade_file)) {
				include_once($upgrade_file);
				if (function_exists($upgrade_function)) {
					log_install_and_cacti('Applying v' . $cacti_upgrade_version . ' upgrade');
					call_user_func($upgrade_function);
				} else {
					log_install_and_cacti('WARNING: Failed to find upgrade function for v' . $cacti_upgrade_version);
				}
			} else {
				log_install('','INFO: Failed to find ' . $upgrade_file . ' upgrade file for v' . $cacti_upgrade_version);
			}
		}

		if (empty($failure)) {
			$failure = $this->checkDatabaseUpgrade();
		}

		return $failure;;
	}

	private function checkDatabaseUpgrade() {
		$failure = '';

		if (isset($_SESSION['cacti_db_install_cache']) && is_array($_SESSION['cacti_db_install_cache'])) {
			foreach ($_SESSION['cacti_db_install_cache'] as $cacti_upgrade_version => $actions) {
				foreach ($actions as $action) {
					// set sql failure if status set to zero on any action
					if ($action['status'] == 0) {
						$failure = 'WARNING: One or more database actions failed';
					}
				}
			}
		}

		return $failure;
	}

	private function disablePluginsNowIntegrated() {
		global $plugins_integrated;
		foreach ($plugins_integrated as $plugin) {
			if (api_plugin_is_enabled ($plugin)) {
				api_plugin_remove_hooks ($plugin);
				api_plugin_remove_realms ($plugin);
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

	public function __construct($initialData = array()) {
		if (empty($initialData) || !is_array($initialData)) {
			$initialData = array();
		}

		foreach ($initialData as $key => $value) {
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
