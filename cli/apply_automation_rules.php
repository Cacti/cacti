#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2019 The Cacti Group                                      |
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
  FUTURE:
    1. Allow device selection through regex as well
    2. (maybe) Don't query host table directly
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
$passed_options = getopt("hyv", ['help','ids::','hostname::','description::']);
if ((array_key_exists('h', $passed_options) or
     array_key_exists('help', $passed_options)
   ) or !(
      array_key_exists('ids',         $passed_options) xor
      array_key_exists('hostname',    $passed_options) xor
      array_key_exists('description', $passed_options)
    )
  ) {
  _aar_usage(); exit(0);
}

# determine hosts
if (isset($passed_options['ids'])) {
  # FUTURE: Could/Should verify ids beforehand
  $ids=explode(' ', $passed_options['ids']);
} elseif (isset($passed_options['hostname'])) {
  $ids=_aar_likefetch('hostname');
} elseif (isset($passed_options['description'])) {
  $ids=_aar_likefetch('description');
}

# confirm if necessary
if (!isset($passed_options['y'])) {
  print "Got " . count($ids) . " hosts, continue?" . ' [y/n] ';
  $response = fgetc(STDIN);
  if (strcasecmp($response,'Y') != 0) {
    echo "Aborted.\n";
    exit(0);
  }
}

# run for all ids.
foreach($ids as $device_id) {
  cacti_log("CLI requesting to apply automation rules for Device id $device_id",
    true,'CLI TRACE');
  automation_update_device($device_id);
  cacti_log("automation_update_device() has finished for $device_id",
    true,'CLI TRACE');
}

# gets hosts by a like query from the DB, either by description or hostname
function _aar_likefetch($what) {
  global $passed_options;
  echo("Querying DB for devices by $what...\n");
  $hosts=db_fetch_assoc_prepared(
    "SELECT id,hostname,description FROM host WHERE $what LIKE ?",
    [$passed_options["$what"]]);
  if(isset($passed_options['v'])) {
    echo("Matched Hosts:\n");
    foreach ($hosts as $entry) { echo(implode(' / ',$entry) . "\n"); }
  }
  return array_map(function ($x) { return $x['id']; }, $hosts);
};

function _aar_usage() {
  print <<<EOM
  Usage:
    apply_automation_rules.php [-h] [-y] SELECTION_CRITERIA

  Where SELECTION_CRITERIA is one (not more) of:
    --ids="deviceid [deviceid...]"
    --hostname='LIKE-compatible hostname string'
    --description='LIKE-compatible host description string'

  Optional:
    -h|--help: Show this help
    -y:        Don't require to confirm matched hosts
    -v:        Verbose mode; list matched hosts

EOM;
}
?>
