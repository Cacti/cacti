#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_INSTALL . '/functions.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug   = false;
$options = array('Runtime' => 'Cli');

$should_install = false;
$force_install  = false;

display_version();

error_reporting(E_ALL);
db_execute("DELETE FROM settings WHERE name like 'log_install%' or name = 'install_eula'");
define('log_install_echo', 'on');

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			/* Standard parameters */
			case '-d':
			case '--debug':
				$logname  = 'log_install';
				$tmplevel = false;

				if (!empty($value)) {
					$pos = strpos($value,':');

					if ($pos !== false) {
						$tmplevel = substr($value,$pos + 1);
						$value    = substr($value,0,$pos);
					}

					if (!empty($value)) {
						$logname .= '_' . $value;
					}
				}

				if ($tmplevel !== false) {
					$level = $tmplevel;
				} else {
					$level = log_install_level($logname, POLLER_VERBOSITY_DEBUG) + 1;
				}
				$level = log_install_level_sanitize($level);
				set_config_option($logname, $level);

				break;
			case '--version':
			case '-V':
			case '-v':
				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);

				/* Script specific parameters */
			case '--accept-eula':
				set_install_option($options, 'Eula', 'End User License Agreement', 'Accepted');

				break;
			case '--automationmode':
			case '-am':
				set_install_option($options, 'AutomationMode', 'Automation Enabled', $value);

				break;
			case '--automationrange':
			case '-ar':
				set_install_option($options, 'AutomationRange', 'Automation Range', $value);

				break;
			case '--cron':
			case '-c':
				set_install_option($options, 'CronInterval', 'Cron Interval', $value);

				break;
			case '--force':
			case '-f':
				$force_install = true;

				break;
			case '--ini':
			case '-i':
				get_install_option($options, $value, false);

				break;
			case '--json':
			case '-j':
				get_install_option($options, $value, true);

				break;
			case '--install':
				$should_install = true;

				break;
			case '--language':
			case '--lang':
			case '-l':
				set_install_option($options, 'Language', 'Language', $value);

				break;
			case '--mode':
			case '-m':
				set_install_option($options, 'Mode', 'Mode', $value);

				break;
			case '--profile':
			case '-p':
				set_install_option($options, 'Profile', 'Collection Profile', $value);

				break;
			case '--path':
				set_install_multioption($options, 'Paths', 'Path Option', $value, 'path_');

				break;
			case '--rrdtool':
			case '-r':
				set_install_option($options, 'RRDVersion', 'RRDTool Version', $value);

				break;
			case '--snmp':
				set_install_multioption($options, 'SnmpOptions', 'Snmp Option', $value, 'Snmp');

				break;
			case '--table':
				set_install_multioption($options, 'Tables', 'Table', $value, '');

				break;
			case '--template':
				set_install_multioption($options, 'Templates', 'Template', $value, 'chk_template_', true);

				break;
			case '--theme':
			case '-t':
				set_install_option($options, 'Theme', 'Theme', $value);

				break;
				/* Bad or unexpected parameter! */
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;

				exit(1);
		}
	}
}

include_once(CACTI_PATH_LIBRARY . '/api_automation.php');
include_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
include_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
include_once(CACTI_PATH_LIBRARY . '/api_device.php');
include_once(CACTI_PATH_LIBRARY . '/data_query.php');
include_once(CACTI_PATH_LIBRARY . '/import.php');
include_once(CACTI_PATH_LIBRARY . '/installer.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
include_once(CACTI_PATH_LIBRARY . '/utility.php');

$options['Step'] = Installer::STEP_INSTALL_CONFIRM;

$results     = array('Step' => $options['Step']);
$update_char = 'o';

debug_install_array('Options', $options);
$installer = new Installer($options);
$results   = $installer->jsonSerialize();
debug_install_array('Result', $results);

process_install_errors($results);

$install_mode = 'no';

switch ($installer->getMode()) {
	case Installer::MODE_INSTALL:
		$install_mode = 'INSTALL CORE';

		break;
	case Installer::MODE_POLLER:
		$install_mode = 'INSTALL POLLER';

		break;
	case Installer::MODE_UPGRADE:
		$install_mode = 'UPGRADE';

		break;
	case Installer::MODE_DOWNGRADE:
		$install_mode = 'DOWNGRADE';

		break;
}
log_install_always('cli', 'Installer prepared for ' . $install_mode . ' action');

$message = '';

if ($installer->getStep() == Installer::STEP_INSTALL_CONFIRM && $should_install) {
	$time = '';

	if ($force_install) {
		$time = '-b';
	}
	log_install_always('cli', 'Starting installation...');
	Installer::beginInstall($time, $installer);
	log_install_always('cli', 'Finished installation...');
}

$step = $installer->getStep();
log_install_high('cli','getStep(): ' . $step);

switch ($installer->getStep()) {
	case Installer::STEP_INSTALL:
		log_install_always('cli', 'An Installation was already in progress');

		break;
	case Installer::STEP_INSTALL_CONFIRM:
		log_install_always('cli', 'No errors were detected.  Install not performed as --install not specified');

		break;
	case Installer::STEP_ERROR:
		log_install_always('cli', 'One or more errors occurred during install, please refer to log files');
		process_install_errors(array('Errors'=>$installer->getErrors()));

		break;
	case Installer::STEP_COMPLETE:
		log_install_always('cli', 'Installation has now completed, you may launch the web console');

		break;

	default:
		log_install_always('cli', 'Unexpected step (' . $installer->getStep() . ')');

		break;
}
print PHP_EOL;

/*  get_install_option - gets the install options from a json file */
function get_install_option(&$options, $file, $json = true) {
	if (empty($file)) {
		print 'ERROR: Invalid file specified, unable to import options';

		exit(1);
	}

	if ($json) {
		$contents = @file_get_contents($file);

		if (empty($contents)) {
			print 'ERROR: Unable to import options from file ' . $file;

			exit(1);
		}

		$options = @json_decode($contents, true);

		if (empty($options)) {
			print 'ERROR: Failed to decode options in file ' . $file;

			exit(1);
		}
	} else {
		$options = @parse_ini_file($file);

		if (empty($options)) {
			print 'ERROR: Unable to import options from file ' . $file;

			exit(1);
		}
	}
}

/*  set_install_option - sets and optional displays debug line of action */
function set_install_option(&$options, $key, $display_name, $value) {
	global $debug;

	$options[$key] = $value;
	log_install_high('cli',sprintf('Setting %s to \'%s\'', $display_name, $value));
}

/*  set_install_multioption - sets sub-options that have multiple key/value combinations with optional prefix */
function set_install_multioption(&$options, $key, $display_name, $value, $prefix, $replace_dots = false) {
	$option_pos = strpos($value, ':');

	if ($option_pos !== false) {
		$option_name = trim(substr($value, 0, $option_pos));

		if ($replace_dots) {
			$option_name = str_replace('.', '_', $option_name);
		}
		$prefix_len = strlen($prefix);

		if ($prefix_len > 0 && substr($option_name, 0, $prefix_len) == $prefix) {
			$option_key  = $option_name;
			$option_name = substr($option_key, $prefix_len);
		} else {
			$option_key = $prefix . $option_name;
		}
		$option_value = trim(substr($value, $option_pos + 1));
		set_install_option($options[$key], $option_key, $display_name . ' \'' . $option_name . '\'', $option_value);
	} else {
		print 'ERROR: Invalid ' . $display_name . ' value ' . $value . PHP_EOL . PHP_EOL;

		exit(1);
	}
}

function debug_install_array($parent, $contents, $indent = 0) {
	$hasContents = false;

	foreach ($contents as $key => $value) {
		if (is_array($value) || is_object($value)) {
			debug_install_array($parent . '.' . $key, $value, $indent + 1);
		} else {
			$hasContents = true;
			log_install_debug('cli',$parent . '.' . $key . ': ' . $value);
		}
	}

	if (!$hasContents) {
		log_install_debug('cli',$parent . ' (no items)');
	}
}

function process_install_errors($results) {
	if (isset($results['Errors']) && cacti_sizeof($results['Errors']) > 0) {
		$errors   = $results['Errors'];
		$count    = 0;
		$sections = 0;

		foreach ($errors as $error_section => $error_array) {
			$sections++;
			print $error_section . PHP_EOL;

			foreach ($error_array as $error_key => $error) {
				$count++;
				print $error_key . ' Error #' . $count . ' - ' . $error . PHP_EOL;
			}
		}

		print PHP_EOL . 'Unable to continue as ' . $count . ' issue' . ($count == 1?'':'s') . ' in ' . $sections . ' section' . ($sections == 1?'':'s') . ' were found.' . PHP_EOL;

		exit(1);
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Install Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help() {
	print PHP_EOL . 'usage: install_cacti.php [--debug] --accept-eula ' . PHP_EOL;
	print '                         [--automationmode=] [--automationrange=] [--cron=]' . PHP_EOL;
	print '                         [--language=] [--mode=] [--profile=] [--path=]' . PHP_EOL;
	print '                         [--rrdtool=] [--snmp=] [--table=] [--template=]' . PHP_EOL;
	print '                         [--theme=]' . PHP_EOL;
	print PHP_EOL . 'A utility to install/upgrade Cacti to the currently sourced version' . PHP_EOL;
	print PHP_EOL . 'Flags:' . PHP_EOL;
	print '  -d  | --debug           - Display verbose output during execution' . PHP_EOL;
	print '  -h  | --help            - Display this help' . PHP_EOL;
	print '  -v  | --version         - Display version' . PHP_EOL;
	print '  -f  | --force           - Override certain safety checks' . PHP_EOL;
	print PHP_EOL . 'Required:' . PHP_EOL;
	print '  --accept-eula           - Accept the End User License Agreement' . PHP_EOL;
	print '  --install               - Perform the installation' . PHP_EOL;
	print PHP_EOL . 'Optional:' . PHP_EOL;
	print '  -am | --automationmode  - Enable/Disable automatic network discovery' . PHP_EOL;
	print '  -ar | --automationrange - Set automatic network discovery subnet' . PHP_EOL;
	print '  -c  | --cron            - Set the cron interval' . PHP_EOL;
	print '  -l  | --lang[uage]      - Set system language' . PHP_EOL;
	print '  -m  | --mode            - Set the installation mode' . PHP_EOL;
	print '  -p  | --profile         - Set the default Data Collector profile' . PHP_EOL;
	print '  -r  | --rrdtool         - Set the RRD Tool version' . PHP_EOL;
	print '  -t  | --theme           - Set system theme' . PHP_EOL;
	print '  -i  | --ini             - Load settings from ini file' . PHP_EOL;
	print '  -j  | --json            - Load settings from json file' . PHP_EOL;
	print PHP_EOL . 'Multi-value optional:' . PHP_EOL;
	print '  These options may be used more than once to apply multiple values.  All' . PHP_EOL;
	print '  values should be in "option_key:option_value" format (see below). If an' . PHP_EOL;
	print '  option has a prefix, this is optional and is automatically added to the' . PHP_EOL;
	print '  the option_key specified if it does not start with that prefix' . PHP_EOL . PHP_EOL;
	print '  Note: reusing an option_key will replace its value with the last one' . PHP_EOL;
	print '        specified.' .PHP_EOL . PHP_EOL;
	print '       --path             - Sets path locations. Example: ' . PHP_EOL;
	print '                              --path=cactilog:/usr/share/cacti/log/cacti.log' . PHP_EOL;
	print '                              --path=cactilog:c:\cacti\log\cacti.log' . PHP_EOL;
	print '                            Prefix: path_' . PHP_EOL;
	print PHP_EOL;
	print '       --snmp             - Sets default snmp options.  Example:' . PHP_EOL;
	print '                              --snmp=SnmpCommunity:public' . PHP_EOL;
	print '                              --snmp=Community:public' . PHP_EOL;
	print '                            Prefix: Snmp' . PHP_EOL;
	print PHP_EOL;
	print '  Note: the following two options expect a value of either 1 (Action) or' . PHP_EOL;
	print '        0 (Skip)' . PHP_EOL;
	print PHP_EOL;
	print '       --template         - Sets templates to be installed.  Example:' . PHP_EOL;
	print '                              --template=Cisco_Router.xml.gz:1' . PHP_EOL;
	print PHP_EOL;
	print '       --table            - Selects a table to be converted to UTF8.  Example:' . PHP_EOL;
	print '                              --table=plugin_config:1' . PHP_EOL;
	print PHP_EOL;
}
