#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2019 Swisscom Schweiz AG                                  |
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
*/
/*
  TODO:
  0. clean up, document
  1. allow device selection through regex instead of passing opts
  2. improve logging
*/

# run from CLI only.
if (php_sapi_name() != "cli") {
  die('This script is intended to be run on the CLI only.');
}

# load globals and stuff
$no_http_headers = true;
require(__DIR__ . '/../include/global.php');
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_automation.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/functions.php');
require_once($config['base_path'] . '/lib/reports.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');
# this would be way nicer, but cannot be done as of 1.1.x
#foreach(glob($config['base_path'] . '/lib/*.php') as $file){
#  require_once($file);
#}

# getopt
$passed_options = getopt("h", ['help','ids:']);
if (array_key_exists('h', $passed_options) ||
    array_key_exists('help', $passed_options) ||
    !array_key_exists('ids',$passed_options)) {
  usage(); exit(0);
}

foreach (explode(' ', $passed_options['ids']) as $device) {
  cacti_log("CLI requesting to apply automation rules for Device id $device",
    true,'CLI TRACE');
  automation_update_device($device);
  cacti_log("automation_update_device() has finished for $device",
    true,'CLI TRACE');
}

function usage() {
  print <<<EOM
  Usage: apply_automation_rules.php [-h] --ids="deviceid [deviceid...]"

EOM;
}
?>
