<?php

class Installer {
	const EXIT_DB_EMPTY = 1;
	const EXIT_DB_OLD = 2;

	const STEP_NONE = 0;
	const STEP_WELCOME = 1;
	const STEP_CHECK_DEPENDENCIES = 2;
	const STEP_INSTALL_TYPE = 3;
	const STEP_BINARY_LOCATION = 4;
	const STEP_PERMISSION_CHECK = 5;
	const STEP_TEMPLATE_INSTALL = 6;
	const STEP_INSTALL_CONFIRM = 7;
	const STEP_INSTALL = 8;
	const STEP_COMPLETE = 9;
	const STEP_INSTALL_OLDVERSION = 10;

	const INSTALL_TYPE_INSTALL = 1;
	const INSTALL_TYPE_POLLER = 2;
	const INSTALL_TYPE_UPGRADE = 3;
	const INSTALL_TYPE_DOWNGRADE = 4;

	private $old_cacti_version;
	private $install_type;
	private $default_install_button;
	private $defualt_install_type;

	public $buttonNext = null;
	public $buttonPrevious = null;
	public $buttonTest = null;
	public $stepData = null;

	public function __construct() {
		$this->stepData = null;
		$this->default_install_type = Installer::INSTALL_TYPE_INSTALL;

		$this->old_cacti_version = get_cacti_version();
		if ($this->old_cacti_version == 'new_install') {
			$this->default_install_type = Installer::INSTALL_TYPE_UPGRADE;
		}

		if (isset_request_var('install_type')) {
			$_SESSION['sess_install_type'] = get_filter_request_var('install_type');
		} elseif (isset($_SESSION['sess_install_type'])) {
			set_request_var('install_type', $_SESSION['sess_install_type']);
		} else {
	        	set_request_var('install_type', '0');
		}

		$this->install_type = get_request_var('install_type');
		$this->setDefaultStep();
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

	public function getOutput() {
		$exitReason = $this->shouldExitWithReason();
		if ($exitReason !== false) {
			$this->buttonNext->Enabled = false;
			$this->buttonPrevious->Enabled = false;
			$this->buttonTest->Enabled = false;
			return $this->outputExitReason($exitReason);
		}

		switch ($this->stepCurrent) {
			case Installer::STEP_WELCOME:
				return $this->outputStepWelcome();
			case Installer::STEP_CHECK_DEPENDENCIES:
				return $this->outputStepCheckDependancies();
		}

		return $this->outputExitReason((0 - $this->stepCurrent));
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
		return version_compare($old_cacti_version, '0.8.5a', '<=');
	}

	public function outputExitReason($reason) {
		global $config;

		switch ($reason) {
			case Installer::EXIT_DB_EMPTY:
				return $this->outputExitSqlNeeded();
			case Installer::EXIT_DB_OLD:
				return $this->outputExitDbTooOld();
			default:
				return $this->outputExitWithUnknownReason($reason);
		}
	}

	private function outputExitWithUnknownReason($reason) {
		$output  = $this->outputErrorTitle();
		$output .= $this->outputNormalSection(__('An unexpected reason was given for preventing this maintainence session.'));
		$output .= $this->outputNormalSection(__('Please report this to the Cacti Group.'));
		$output .= $this->outputCodeSection(__('Unknown Reason: %s', $reason));
		return $output;
	}

	private function outputExitDbTooOld() {
		$output  = $this->outputErrorTitle();
		$output .= $this->outputNormalSection(__('You are attempting to install Cacti %s onto a 0.6.x database. Unfortunately, this can not be performed.', CACTI_VERSION));
		$output .= $this->outputNormalSection(__('To be able continue, you <b>MUST</b> create a new database, import "cacti.sql" into it:', CACTI_VERSION));
		$output .= $this->outputCodeSection(__("mysql -u %s -p [new_database] < cacti.sql", $database_username, $database_default));
		$output .= $this->outputNormalSection(__('You <b>MUST</b> then update "include/config.php" to point to the new database.'));
		$output .= $this->outputNormalSection(__('NOTE: Your existing data will not be modified, nor will it or any history be available to to the new install'));
		return $output;
	}

	private function outputExitSqlNeeded() {
		global $config, $database_username, $database_default, $database_password;
		$output  = $this->outputErrorTitle();
		$output .= $this->outputNormalSection(__("You have created a new database, but have not yet imported the 'cacti.sql' file. At the command line, execute the following to continue:"));
		$output .= $this->outputCodeSection(__("mysql -u %s -p %s < cacti.sql", $database_username, $database_default));
		$output .= $this->outputNormalSection(__("This error may also be generated if the cacti database user does not have correct permissions on the Cacti database. Please ensure that the Cacti database user has the ability to SELECT, INSERT, DELETE, UPDATE, CREATE, ALTER, DROP, INDEX on the Cacti database."));
		$output .= $this->outputNormalSection(__("You <b>MUST</b> also import MySQL TimeZone information into MySQL and grant the Cacti user SELECT access to the mysql.time_zone_name table"));

		if ($config['cacti_server_os'] == 'unix') {
			$output .= $this->outputNormalSection(__("On Linux/UNIX, run the following as 'root' in a shell:"));
			$output .= $this->outputCodeSection(__("mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql"));
		} else {
			$output .= $this->outputNormalSection(__("On Windows, you must follow the instructions here <a target='_blank' href='https://dev.mysql.com/downloads/timezones.html'>Time zone description table</a>.  Once that is complete, you can issue the following command to grant the Cacti user access to the tables:"));
		}

		$output .= $this->outputNormalSection(__("Then run the following within MySQL as an administrator:"));
		$output .= $this->outputCodeSection(__("mysql &gt; GRANT SELECT ON mysql.time_zone_name to '%s'@'localhost' IDENTIFIED BY '%s'", $database_username, $database_password));
		return $output;
	}

	public function outputErrorTitle($title = '') {
		if (empty($title)) {
			$title = __('Error');
		}
		return $this->outputSectionTitle($title, null, 'cactiInstallSectionTitleError');
	}

	public function outputSectionTitle($title = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		return $this->outputSection($title, $id, $class, 'cactiInstallSectionTitle', 'h1');
	}

	public function outputSectionSubTitle($title = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		return $this->outputSection($title, $id, $class, 'cactiInstallSectionTitle', 'h3');
	}

	public function outputNormalSection($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionNormal';
		return $this->outputSection($text, $id, $class);
	}

	public function outputCodeSection($text = '', $id = '', $class = '') {
		if (empty($class)) {
			$class = '';
		}

		if (empty($id)) {
			$id = '';
		}

		$class .= ' cactiInstallSectionCode';
		return $this->outputSection($text, $id, trim($class), '', '');
	}

	public function outputSection($text = '', $id = '', $class = '', $baseClass = 'cactiInstallSection', $elementType = 'div') {
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

	public function getInstallerType() {
		if (empty($old_cacti_version)) {
			return Installer::INSTALL_TYPE_INSTALL;
		} elseif (cacti_version_compare($old_cacti_version, CACTI_VERSION, '<=')) {
			return Installer::INSTALL_TYPE_UPGRADE;
		}

		return Installer::INSTALL_TYPE_DOWNGRADE;
	}

	public function setDefaultStep($param_step = 0) {
		/* pre-processing that needs to be done for each step */
		$step = 0;
		if (empty($param_step)) {
			$param_step = 0;
		}

		if (intval($param_step) > 0 && intval($param_step) < 10) {
			$step = $param_step;
		}

		if ($step == Installer::STEP_NONE) {
			$step == Installer::STEP_WELCOME;
		}

		// Make current step the first if it is unknown
		$this->stepCurrent  = ($step == Installer::STEP_NONE ? Installer::STEP_WELCOME : $step);
		$this->stepPrevious = ($step <= Installer::STEP_WELCOME ? Installer::STEP_NONE : $step - 1);
		$this->stepNext     = ($step >= Installer::STEP_COMPLETE ? Installer::STEP_NONE : $step + 1);

		if (empty($this->buttonNext)) {
			$this->buttonNext = new InstallerButton();
		}

		if (empty($this->buttonPrevious)) {
			$this->buttonPrevious = new InstallerButton();
		}

		if (empty($this->buttonTest)) {
			$this->buttonTest = new InstallerButton();
		}

		$this->buttonNext->Text = __('Next');
		$this->buttonPrevious->Text = __('Previous');

		$this->buttonTest->Visible = false;
		$this->buttonTest->setStep(0);

		$install_type = $this->getInstallerType();

		switch($this->stepCurrent) {
			case Installer::STEP_WELCOME:
				$this->buttonNext->Text = __('Begin');
				break;

			case Installer::STEP_INSTALL_CONFIRM:
				/* checkdependencies - send to install/upgrade */
				if ($install_type == Installer::INSTALL_TYPE_UPGRADE) {
					$this->buttonNext->Text = __('Upgrade');
				} elseif ($install_type == Installer::INSTALL_TYPE_DOWNGRADE) {
					$this->buttonNext->Text = __('Downgrade');
				}
				break;

			case Installer::STEP_INSTALL_TYPE:
				$this->stepPrevious = Installer::STEP_CHECK_DEPENDENCIES;

				switch ($install_type) {
					case Installer::INSTALL_TYPE_INSTALL:
					case Installer::INSTALL_TYPE_POLLER:
						$this->stepNext = Installer::STEP_BINARY_LOCATIONS;
						break;

					case Installer::INSTALL_TYPE_UPGRADE:
					case Installer::INSTALL_TYPE_DOWNGRADE:
						$this->stepNext = Installer::STEP_PERMISSION_CHECK;
						break;
				}

				break;

			case Installer::STEP_PERMISSION_CHECK:
				switch ($install_type) {
					case Installer::INSTALL_TYPE_UPGRADE:
					case Installer::INSTALL_TYPE_DOWNGRADE:
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
					$this->stepNext = Installer::STPE_INSTALL;
				}
				break;

			case Installer::STEP_COMPLETE:
				$this->stepPrevious = Installer::STEP_NONE;
				break;
		}

		$this->buttonNext->setStep($this->stepNext);
		$this->buttonPrevious->setStep($this->stepPrevious);
	}

	public function outputStepWelcome() {
		$output  = $this->outputSectionTitle(__('Cacti Version') . ' '. CACTI_VERSION . ' - ' . __('License Agreement'));
		$output .= $this->outputNormalSection(__('Thanks for taking the time to download and install Cacti, the complete graphing solution for your network. Before you can start making cool graphs, there are a few pieces of data that Cacti needs to know.'));
		$output .= $this->outputNormalSection(__('Make sure you have read and followed the required steps needed to install Cacti before continuing. Install information can be found for <a href="%1$s">Unix</a> and <a href="%2$s">Win32</a>-based operating systems.', '../docs/html/install_unix.html', '../docs/html/install_windows.html'));
		if ($default_install_type == INSTALL_TYPE_UPGRADE || $default_install_type == INSTALL_TYPE_DOWNGRADE) {
			$output .= $this->outputNoteSection(__('Note: This process will guide you through the steps for upgrading from version \'%s\'. ',$old_cacti_version));
			$output .= $this->outputNormalSection(__('Also, if this is an upgrade, be sure to reading the <a href="%s">Upgrade</a> information file.', '../docs/html/upgrade.html'));
		}
		$output .= $this->outputNormalSection(__('Cacti is licensed under the GNU General Public License, you must agree to its provisions before continuing:'));

		$output .= $this->outputCodeSection(
			__('This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.') . '<br/><br/>' .
			__('This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.')
		);

		$output .= $this->outputNormalSection('<span><input type=\'checkbox\' id=\'accept\' name=\'accept\'></span><span><label for=\'accept\'>' . __('Accept GPL License Agreement') . '</label></span>');
		$this->buttonNext->Enabled = false;
		return $output;
	}

	public function outputStepCheckDependancies() {
		$enabled = array(
			'location' => 1,
			'php_timezone' => 1,
			'php_modules' => 1,
			'php_optional' => 1,
			'mysql_timezone' => 1,
			'mysql_performance' => 1
		);

		$output  = $this->outputSectionTitle(__('Pre-installation Checks'));
		$output .= $this->outputSectionSubTitle(__('Location checks'), 'location');

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
			$output .= $this->outputNormalSection('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Please update config.php with the correct relative URI location of Cacti (url_path).') . '</span>');
			$enabled['location'] = 0;
		} else {
			$output .= $this->outputNormalSection(__('Your Cacti configuration has the relative correct path (url_path) in config.php.'));
		}

		$output .= $this->outputSectionSubTitle(__('Required PHP Module Support'), 'php_modules');
		$output .= $this->outputNormalSection(__('Cacti requires several PHP Modules to be installed to work properly. If any of these are not installed, you will be unable to continue the installation until corrected. In addition, for optimal system performance Cacti should be run with certain MySQL system variables set.  Please follow the MySQL recommendations at your discretion.  Always seek the MySQL documentation if you have any questions.'));

		$output .= $this->outputNormalSection(__('The following PHP extensions are mandatory, and MUST be installed before continuing your Cacti install.'));

		ob_start();

		html_start_box('<strong> ' . __('Required PHP Modules') . '</strong>', '30', 0, '', '', false);
		html_header( array( __('Name'), __('Required'), __('Installed') ) );

		$enabled['php_modules'] = version_compare(PHP_VERSION, '5.4.0', '<') ? 0 : 1;
		form_alternate_row('phpline',true);
		form_selectable_cell(__('PHP Version'), '');
		form_selectable_cell('5.4.0+', '');
		form_selectable_cell((version_compare(PHP_VERSION, '5.4.0', '<') ? "<font color=red>" . PHP_VERSION . "</font>" : "<font color=green>" . PHP_VERSION . "</font>"), '');
		form_end_row();

		$output .= $this->outputNormalSection(ob_get_contents());
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

		$output .= $this->outputNormalSection(ob_get_contents());
		ob_clean();

		$output .= $this->outputSectionSubTitle(__('Optional PHP Module Support'), 'php_optional');

		$output .= $this->outputNormalSection(__('The following PHP extensions are recommended, and should be installed before continuing your Cacti install.'));
		$extensions = array(
			array('name' => 'snmp', 'installed' => false),
			array('name' => 'gmp', 'installed' => false)
		);

		$ext = verify_php_extensions($extensions);
		$ext[] = array('name' => 'TrueType Text', 'installed' => function_exists('imagettftext'));
		$ext[] = array('name' => 'TrueType Box', 'installed' => function_exists('imagettfbbox'));

		html_start_box('<strong> ' . __('Optional Modules') . '</strong>', '30', 0, '', '', false);
		html_header( array( __('Name'), __('Optional'), __('Installed') ) );

		foreach ($ext as $id => $e) {
			form_alternate_row('line' . $id, true);
			form_selectable_cell($e['name'], '');
			form_selectable_cell('<font color=green>' . __('Yes') . '</font>', '');
			form_selectable_cell(($e['installed'] ? '<font color=green>' . __('Yes') . '</font>' : '<font color=red>' . __('No') . '</font>'), '');
			form_end_row();

			if (!$e['installed']) $enabled['php_optional'] = 0;
		}

		html_end_box();

		$output .= $this->outputNormalSection(ob_get_contents());
		ob_clean();

		$output .= $this->outputSectionSubTitle(__('PHP Timezone Support'), 'php_timezone');
		if (ini_get('date.timezone') == '') {
			$output .= $this->outputNormalSection('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your Web Servers PHP Timezone settings have not been set.  Please edit php.ini and uncomment the \'date.timezone\' setting and set it to the Web Servers Timezone per the PHP installation instructions prior to installing Cacti.') . '</span>');
			$enabled['php_timezone'] = 0;
		} else {
			$output .= $this->outputNormalSection(__('Your Web Servers PHP is properly setup with a Timezone.'));
		}

		$output .= $this->outputSectionSubTitle(__('MySQL TimeZone Support'), 'mysql_timezone');
		$mysql_timezone_access = db_fetch_assoc('SHOW COLUMNS FROM mysql.time_zone_name', false);
		if (sizeof($mysql_timezone_access)) {
			$timezone_populated = db_fetch_cell('SELECT COUNT(*) FROM mysql.time_zone_name');
			if (!$timezone_populated) {
				$output .= $this->outputNormalSection('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your MySQL TimeZone database is not populated.  Please populate this database before proceeding.') . '</span>');
				$enabled['mysql_timezone'] = 0;
			}
		} else {
			$output .= $this->outputNormalSection('<span class="textError"><strong>' . __('ERROR:') . '</strong> ' .  __('Your Cacti database login account does not have access to the MySQL TimeZone database.  Please provide the Cacti database account "select" access to the "time_zone_name" table in the "mysql" database, and populate MySQL\'s TimeZone information before proceeding.') . '</span>');
			$enabled['mysql_timezone'] = 0;
		}

		if ($enabled['mysql_timezone'] == 1) {
			$output .= $this->outputNormalSection(__('Your Cacti database account has access to the MySQL TimeZone database and that database is populated with global TimeZone information.'));
		}

		$output .= $this->outputSectionSubTitle(__('MySQL Performance'), 'mysql_performance');
		$output .= $this->outputNormalSection(__('These MySQL performance tuning settings will help your Cacti system perform better without issues for a longer time.'));

		html_start_box('<strong> ' . __('Recommended MySQL System Variable Settings') . '</strong>', '30', 0, '', '', false);
		$output_temp = ob_get_contents();
		ob_clean();

		utilities_get_mysql_recommendations();
		$output_util = ob_get_contents();
		ob_clean();

		html_end_box(false);

		$output .= $this->outputNormalSection($output_temp . $output_util . ob_get_contents());
		ob_end_clean();

		$this->stepData = $enabled;
		return $output;
	}
}

class InstallerButton {
	public $Text = '';
	public $Step = 0;
	public $Visible = true;
	public $Enabled = true;

	public function setStep($step) {
		$this->Step = $step;
		$this->Enabled = !empty($this->Step);
	}
}
