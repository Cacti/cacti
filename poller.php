#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

// Start Initialization Section
include(dirname(__FILE__) . "/include/config.php");
include_once($config["base_path"] . "/lib/functions.php");
include_once($config["base_path"] . "/lib/rrd.php");

// We are not talking to the browser
$no_http_headers = true;

// Record Start Time
list($micro,$seconds) = split(" ", microtime());
$start = $seconds + $micro;

// Let PHP Run Just as Long as It Has To
ini_set("max_execution_time", "0");

// Get number of polling items from the database
$polling_items = db_fetch_assoc("select distinct id from host where disabled = '' order by id");

// Retreive the number of concurrent process settings
$concurrent_processes = read_config_option("concurrent_processes");

// Initialize counters for script file handling
$host_count = 1;

// Initialize file creation flags
$change_files = False;

// Initialize file and host count pointers
$process_file_number = 0;
$first_host = 0;
$last_host = 0;

// Obtain some defaults from the database
$poller = read_config_option("poller_type");
$max_threads = read_config_option("max_threads");
// End Initialization Section

// Enter Mainline Processing
if ((sizeof($polling_items) > 0) and (read_config_option("poller_enabled") == "on"))
{
    /* Determine the number of hosts to process per file */
    $hosts_per_file = ceil(sizeof($polling_items) / $concurrent_processes );

    /* Empty the polling autoexecution directory */
    if (($config["cacti_server_os"] == "unix") or ($poller == "2"))
    {
        pclose(popen("rm -r -f " . read_config_option("path_scriptdir") . "/*", "r"));
    }
    else
    {
        pclose(popen("del " . stri_replace("/","\\",read_config_option("path_scriptdir")) . "\\*.* /Q", "rb"));
    }
    /* End Empty polling autoexecution Directory */

    /* Determine Command Name */
    if (($config["cacti_server_os"] == "unix") and ($poller == "2"))
    {
        $blast_string = "cactiplus";
        $command_string = "cactid";
    }
    else if ($config["cacti_server_os"] == "unix")
    {
        $blast_string = "cactiplus";
        $command_string = "cmd.php";
    }
    else if ($poller == "2")
    {
        $blast_string = "cactiplus.cmd";
        $command_string = "cactid.exe";
    }
    else
    {
        $blast_string = "cactiplus.cmd";
        $command_string = "cmd.php";
    }

    /* Initialize file for autoexecution */
    touch(read_config_option("path_scriptdir") . "/" . $blast_string);
    $process_exec_file = fopen(read_config_option("path_scriptdir") . "/" . $blast_string, "w");

    /* Populate each execution file with appropriate information */
    foreach ($polling_items as $item)
    {
        if ($host_count == 1)
        {
            $first_host = $item["id"];
        }
        if ($host_count == $hosts_per_file)
        {
            $last_host = $item["id"];
            $change_files = True;
        }
        $host_count ++;
        if ($change_files)
        {
            write_poller_file_item( $config["base_path"], $config["cacti_server_os"], $process_exec_file, $first_host, $last_host, $command_string );
            $host_count = 1;
            $change_files = False;
            $first_host = 0;
            $last_host = 0;
        } /* End change_files */
    } /* End For Each */

    if ($host_count > 1)
    {
        $last_host = $item["id"];
        write_poller_file_item( $config["base_path"], $config["cacti_server_os"], $process_exec_file, $first_host, $last_host, $command_string );
    }

    /* Close File - Ready to Blast Off!!! */
    fclose( $process_exec_file );

    /* Execute script file */
    if ($config["cacti_server_os"] == "unix")
    {
        chmod(read_config_option("path_scriptdir") . "/" . $blast_string, "777");
        exec(read_config_option("path_scriptdir") . "/" . $blast_string);
    }
    else
    {
        exec(read_config_option("path_scriptdir") . "\\" . $blast_string);
    }
    /* End if execute script file */

    /* insert the current date/time for graphs */
    db_execute("replace into settings (name,value) values ('date',NOW())");

    /* take time and log performance data */
    list($micro,$seconds) = split(" ", microtime());
    $end = $seconds + $micro;

	if ($poller == "1") {
	    $max_threads = "N/A";
	}

    if(read_config_option("log_pstats") == "on")
        log_data(sprintf("STATS: " .
                          "Execution Time: %01.4f s, " .
                          "Method: %s, " .
                          "Max Processes: %s, " .
                          "Max Threads/Process: %s, " .
                          "Polled Hosts: %s, " .
                          "Hosts/Process: %s",
                          round($end-$start,4),
                          $command_string,
                          $concurrent_processes,
                          $max_threads,
                          sizeof($polling_items),
                          $hosts_per_file));
}
else
{
    print "There are no items in your poller cache or polling is disabled. Make sure you have at least one data source created. If you do, go to 'Utilities', and select 'Clear Poller Cache'.\n";
}
// End Mainline Processing


function write_poller_file_item( $base_path, $cacti_server_os, $process_exec_file, $first_host, $last_host, $command_string )
{
    if (($cacti_server_os == "unix") and (read_config_option("poller_type") == "2"))
    {
        fputs($process_exec_file,
             read_config_option("path_cactid") . " " .
             $first_host . " " .
             $last_host . " &\r\n");
    }
    else if ($cacti_server_os == "unix")
    {
        fputs($process_exec_file,
             read_config_option("path_php_binary") . " " .
             $base_path . "/" .
             $command_string . " " .
             $first_host . " " .
             $last_host . " &\r\n");
    }
    else if (read_config_option("poller_type") != "2")
    {
        fputs($process_exec_file,
             "start \"Cactiplus\" /I /B " .
             read_config_option("path_php_binary") . " " .
             $base_path . "\\cmd.php " .
             $first_host . " " .
             $last_host . "\r\n");
    }
    else
    {
        fputs($process_exec_file,
             "start \"Cactiplus\" /I /B " .
             read_config_option("path_cactid") . " " .
             $first_host . " " .
             $last_host . "\r\n");
    }
}

?>
