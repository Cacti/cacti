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

function graph_export() {
	/* take time to log performance data */
	list($micro,$seconds) = split(" ", microtime());
	$start = $seconds + $micro;

	if (read_config_option("export_timing") != "disabled") {
		switch (read_config_option("export_timing")) {
			case "classic":
				if (read_config_option("path_html_export_ctr") >= read_config_option("path_html_export_skip")) {
					db_execute("update settings set value='1' where name='path_html_export_ctr'");
					$total_graphs_created = config_graph_export();
					config_export_stats($start, $total_graphs_created);
				} elseif (read_config_option("path_html_export_ctr") == "") {
					db_execute("delete from settings where name='path_html_export_ctr' or name='path_html_export_skip'");
					db_execute("insert into settings (name,value) values ('path_html_export_ctr','1')");
					db_execute("insert into settings (name,value) values ('path_html_export_skip','1')");
				} else {
					db_execute("update settings set value='" . (read_config_option("path_html_export_ctr") + 1) . "' where name='path_html_export_ctr'");
				}
				break;
			case "export_hourly":
				$export_minute = read_config_option('export_hourly');
				if (empty($export_minute)) {
					db_execute("insert into settings (name,value) values ('export_hourly','0')");
				} elseif (floor((date('i') / 5)) == floor((read_config_option('export_hourly') / 5))) {
					$total_graphs_created = config_graph_export();
					config_export_stats($start, $total_graphs_created);
				}
				break;
			case "export_daily":
				if (strstr(read_config_option('export_daily'), ':')) {
					$export_daily_time = explode(':', read_config_option('export_daily'));
					if (date('G') == $export_daily_time[0]) {
						if (floor((date('i') / 5)) == floor(($export_daily_time[1] / 5))) {
							$total_graphs_created = config_graph_export();
							config_export_stats($start, $total_graphs_created);
						}
					}
				} else {
					db_execute("insert into settings (name,value) values ('export_daily','00:00')");
				}
				break;
			default:
				export_log("Export timing not specified. Updated config to disable exporting.");
				db_execute("insert into settings (name,value) values ('export_timing','disabled')");
		}
	}
}

function config_graph_export() {
	$total_graphs_created = 0;

	switch (read_config_option("export_type")) {
		case "local":
			$total_graphs_created = export();
			break;
		case "sftp_php":
			if (!function_exists("ftp_ssl_connect")) {
				export_fatal("Secure FTP Function does not exist.  Export can not continue.");
			}
		case "ftp_php":
			/* set the temp directory */
			if (strlen(read_config_option("export_temporary_directory")) == 0) {
				$stExportDir = $_ENV["TMP"] . '/cacti-ftp-temp';
			}else{
				$stExportDir = read_config_option("export_temporary_directory") . '/cacti-ftp-temp';
			}

			$total_graphs_created = export_pre_ftp_upload($stExportDir);
			export_log("Using PHP built-in FTP functions.");
			export_ftp_php_execute($stExportDir, read_config_option("export_type"));
			export_post_ftp_upload($stExportDir);
			break;
		case "ftp_ncftpput":
			if (strstr(PHP_OS, "WIN")) export_fatal("ncftpput only available in unix environment!  Export can not continue.");

			/* set the temp directory */
			if (strlen(read_config_option("export_temporary_directory")) == 0) {
				$stExportDir = $_ENV["TMP"] . '/cacti-ftp-temp';
			}else{
				$stExportDir = read_config_option("export_temporary_directory") . '/cacti-ftp-temp';
			}

			$total_graphs_created = export_pre_ftp_upload($stExportDir);
			export_log("Using ncftpput.");
			export_ftp_ncftpput_execute($stExportDir);
			export_post_ftp_upload($stExportDir);
			break;
		case "disabled":
			break;
		default:
			export_fatal("Export method not specified. Exporting can not continue.  Please set method properly in Cacti configuration.");
	}

	return $total_graphs_created;
}

function config_export_stats($start, $total_graphs_created) {
	/* take time to log performance data */
	list($micro,$seconds) = split(" ", microtime());
	$end = $seconds + $micro;

	$export_stats = sprintf(
		"ExportTime:%01.4f TotalGraphs:%s",
		round($end - $start,4), $total_graphs_created);

	cacti_log("STATS: " . $export_stats, true, "EXPORT");

	/* insert poller stats into the settings table */
	db_execute("replace into settings (name,value) values ('stats_export','$export_stats')");
}

function export_fatal($stMessage) {
	cacti_log("FATAL ERROR: " . $stMessage, true, "EXPORT");
	exit;
}

function export_log($stMessage) {
	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
		cacti_log($stMessage, true, "EXPORT");
	}
}

function export_pre_ftp_upload($stExportDir) {
	global $aFtpExport;

	/* export variable as global */
	$_SESSION["sess_config_array"]["path_html_export"] = $stExportDir;

	/* clean-up after last cacti instance */
	if (is_dir($stExportDir)) {
		del_directory($stExportDir, FALSE);
	}else {
		@mkdir($stExportDir);
	}

	/* go export */
	$total_graphs_created = export();

	/* force reaing of the variable from the database */
	unset($_SESSION["sess_config_array"]["path_html_export"]);

	$aFtpExport['server'] = read_config_option('export_ftp_host');
	if (empty($aFtpExport['server'])) {
		export_fatal("FTP Hostname is not expected to be blank!");
	}

	$aFtpExport['remotedir'] = read_config_option('path_html_export');
	if (empty($aFtpExport['remotedir'])) {
		export_fatal("FTP Remote export path is not expected to be blank!");
	}

	$aFtpExport['port'] = read_config_option('export_ftp_port');
	$aFtpExport['port'] = empty($aFtpExport['port']) ? '21' : $aFtpExport['port'];

	$aFtpExport['username'] = read_config_option('export_ftp_user');
	$aFtpExport['password'] = read_config_option('export_ftp_password');

	if (empty($aFtpExport['username'])) {
		$aFtpExport['username'] = 'Anonymous';
		$aFtpExport['password'] = '';
		export_log("Using Anonymous transfer method.");
	}

	if (read_config_option('export_ftp_passive') == 'on') {
		$aFtpExport['passive'] = TRUE;
		export_log("Using passive transfer method.");
	}else {
		$aFtpExport['passive'] = FALSE;
		export_log("Using active transfer method.");
	}

	return $total_graphs_created;
}

function export_ftp_php_execute($stExportDir, $stFtpType = "ftp_php") {
	global $aFtpExport;

	/* connect to foreign system */
	switch($stFtpType) {
	case "ftp_php":
		$oFtpConnection = ftp_connect($aFtpExport['server'], $aFtpExport['port']);

		if (!$oFtpConnection) {
			export_fatal("FTP Connection failed! Check hostname and port.  Export can not continue.");
		}else {
			export_log("Conection to remote server was successful.");
		}
		break;
	case "sftp_php":
		$oFtpConnection = ftp_ssl_connect($aFtpExport['server'], $aFtpExport['port']);

		if (!$oFtpConnection) {
			export_fatal("SFTP Connection failed! Check hostname and port.  Export can not continue.");
		}else {
			export_log("Conection to remote server was successful.");
		}
		break;
	}

	/* login to foreign system */
	if (!ftp_login($oFtpConnection, $aFtpExport['username'], $aFtpExport['password'])) {
		ftp_close($oFtpConnection);
		export_fatal("FTP Login failed! Check username and password.  Export can not continue.");
	}else {
		export_log("Remote login was successful.");
	}

	/* set connection type */
	if ($aFtpExport['passive']) {
		ftp_pasv($oFtpConnection, TRUE);
	}else {
		ftp_pasv($oFtpConnection, FALSE);
	}

	/* change directories into the remote upload directory */
	if (!@ftp_chdir($oFtpConnection, $aFtpExport['remotedir'])) {
		ftp_close($oFtpConnection);
		export_fatal("FTP Remote directory '" . $aFtpExport['remotedir'] . "' does not exist!.  Export can not continue.");
	}

	/* sanitize the remote location if the user has asked so */
	if (read_config_option('export_ftp_sanitize') == 'on') {
		export_log("Deleting remote files.");

		/* get rid of the files first */
		$aFtpRemoteFiles = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);

		if (is_array($aFtpRemoteFiles)) {
			foreach ($aFtpRemoteFiles as $stFile) {
				export_log("Deleting remote file '" . $stFile . "'");
				@ftp_delete($oFtpConnection, $stFile);
			}
		}

		/* if the presentation is tree, you will have some directories too */
		if (read_config_option("export_presentation") == "tree") {
			$aFtpRemoteDirs = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);

			foreach ($aFtpRemoteDirs as $remote_dir) {
				if (ftp_chdir($oFtpConnection, addslashes($remote_dir))) {
					$aFtpRemoteFiles = ftp_nlist($oFtpConnection, ".");
					if (is_array($aFtpRemoteFiles)) {
						foreach ($aFtpRemoteFiles as $stFile) {
							export_log("Deleting Remote File '" . $stFile . "'");
							ftp_delete($oFtpConnection, $stFile);
						}
					}
					ftp_chdir($oFtpConnection, "..");

					export_log("Removing Remote Directory '" . $remote_dir . "'");
					ftp_rmdir($oFtpConnection, $remote_dir);
				}else{
					ftp_close($oFtpConnection);
					export_fatal("Unable to cd on remote system");
				}
			}
		}

		$aFtpRemoteFiles = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);
		if (sizeof($aFtpRemoteFiles) > 0) {
			ftp_close($oFtpConnection);
			export_fatal("Problem sanitizing remote ftp location, must exit.");
		}
	}

	/* upload files to remote system */
	export_log("Uploading files to remote location.");
	ftp_chdir($oFtpConnection, $aFtpExport['remotedir']);
	export_ftp_php_uploaddir($stExportDir,$oFtpConnection);

	/* end connection */
	export_log("Closing ftp connection.");
	ftp_close($oFtpConnection);
}

function export_ftp_php_uploaddir($dir,$oFtpConnection) {
	global $aFtpExport;

	export_log("Uploading directory: '$dir' to remote location.");
	if($dh = opendir($dir)) {
		export_log("Uploading files to remote location.");
		while(($file = readdir($dh)) !== false) {
			$filePath = $dir . "/" . $file;
			if($file != "." && $file != ".." && !is_dir($filePath)) {
				if(!ftp_put($oFtpConnection, $file, $filePath, FTP_BINARY)) {
					export_log("Failed to upload '$file'.");
				}
			}

			if (($file != ".") &&
				($file != "..") &&
				(is_dir($filePath))) {

				export_log("Create remote directory: '$file'.");
				ftp_mkdir($oFtpConnection,$file);

				export_log("Change remote directory to: '$file'.");
				ftp_chdir($oFtpConnection,$file);
				export_ftp_php_uploaddir($filePath,$oFtpConnection);

				export_log("Change remote directory: one up.");
				ftp_cdup($oFtpConnection);
			}
		}
		closedir($dh);
	}
}

function export_ftp_ncftpput_execute($stExportDir) {
	global $aFtpExport;

	chdir($stExportDir);

	/* set the initial command structure */
	$stExecute = 'ncftpput -R -V -r 1 -u '.$aFtpExport['username'].' -p '.$aFtpExport['password'];

	/* if the user requested passive mode, use it */
	if ($aFtpExport['passive']) {
		$stExecute .= ' -F ';
	}

	/* setup the port, server, remote directory and all files */
	$stExecute .= ' -P ' . $aFtpExport['port'] . ' ' . $aFtpExport['server'] . ' ' . $aFtpExport['remotedir'] . ".";

	/* run the command */
	system($stExecute, $iExecuteReturns);

	$aNcftpputStatusCodes = array (
		'Success.',
		'Could not connect to remote host.',
		'Could not connect to remote host - timed out.',
		'Transfer failed.',
		'Transfer failed - timed out.',
		'Directory change failed.',
		'Directory change failed - timed out.',
		'Malformed URL.',
		'Usage error.',
		'Error in login configuration file.',
		'Library initialization failed.',
		'Session initialization failed.');

	export_log('Ncftpput returned: ' . $aNcftpputStatusCodes[$iExecuteReturns]);
}

function export_post_ftp_upload($stExportDir) {
	/* clean-up after ftp-put */
	if ($dh = opendir($stExportDir)) {
		while (($file = readdir($dh)) !== false) {
			$filePath = $stExportDir . "/" . $file;
			if ($file != "." && $file != ".." && !is_dir($filePath)) {
				export_log("Removing Local File '" . $file . "'");
				unlink($filePath);
			}

			/* if the directory turns out to be a sub-directory, delete it too */
			if ($file != "." && $file != ".." && is_dir($filePath)) {
				export_log("Removing Local Directory '" . $filePath . "'");
				export_post_ftp_upload($filePath);
			}
		}
		closedir($dh);

		/* don't delete the root of the temporary export directory */
		if (read_config_option("export_temporary_directory") != $stExportDir) {
			rmdir($stExportDir);
		}
	}
}

function export() {
	global $config;

	/* count how many graphs are created */
	$total_graphs_created = 0;

	$cacti_root_path = $config["base_path"];
	$cacti_export_path = read_config_option("path_html_export");

	/* if the path is not a directory, don't continue */
	if (!is_dir($cacti_export_path)) {
		export_fatal("Export path '" . $cacti_export_path . "' does not exist!  Export can not continue.");
	}

	/* blank paths are not good */
	if (strlen($cacti_export_path) == 0) {
		export_fatal("Export path is null!  Export can not continue.");
	}

	/* can not be the web root */
	if ((strcasecmp($cacti_root_path, $cacti_export_path) == 0) &&
		(read_config_option("export_type") == "local")) {
		export_fatal("Export path '" . $cacti_export_path . "' is the Cacti web root.  Can not continue.");
	}

	/* can not be a parent of the Cacti web root */
	if (strncasecmp($cacti_root_path, $cacti_export_path, strlen($cacti_export_path))== 0) {
		export_fatal("Export path '" . $cacti_export_path . "' is a parent folder from the Cacti web root.  Can not continue.");
	}

	/* check for bad directories within the cacti path */
	if (strcasecmp($cacti_root_path, $cacti_export_path) < 0) {
		$cacti_system_paths = array(
			"include",
			"lib",
			"install",
			"rra",
			"log",
			"scripts",
			"plugins",
			"images",
			"resource");

		foreach($cacti_system_paths as $cacti_system_path) {
			if (substr_count(strtolower($cacti_export_path), strtolower($cacti_system_path)) > 0) {
				export_fatal("Export path '" . $cacti_export_path . "' is potentially within a Cacti system path '" . $cacti_system_path . "'.  Can not continue.");
			}
		}
	}

	/* don't allow to export to system paths */
	$system_paths = array(
		"/boot",
		"/lib",
		"/usr",
		"/bin",
		"/sbin",
		"/root",
		"/etc",
		"windows",
		"winnt",
		"program files");

	foreach($system_paths as $system_path) {
		if (substr_count(strtolower($cacti_export_path), strtolower($system_path)) > 0) {
			export_fatal("Export path '" . $cacti_export_path . "' is within a system path '" . $system_path . "'.  Can not continue.");
		}
	}

	export_log("Running graph export");

	/* delete all files and directories in the cacti_export_path */
	del_directory($cacti_export_path, false);

	/* test how will the export will be made */
	if (read_config_option('export_presentation') == 'tree') {
		export_log("Running graph export with tree organization");
		$total_graphs_created = tree_export();
	}else {
		export_log("Running graph export with legacy organization");
		$total_graphs_created = classical_export($cacti_root_path, $cacti_export_path);
	}

	return $total_graphs_created;
}

function classical_export($cacti_root_path, $cacti_export_path) {
	global $config;

	$total_graphs_created = 0;

	/* copy the css/images on the first time */
	if (file_exists("$cacti_export_path/main.css") == false) {
		copy("$cacti_root_path/include/main.css", "$cacti_export_path/main.css");
		copy("$cacti_root_path/images/tab_cacti.gif", "$cacti_export_path/tab_cacti.gif");
		copy("$cacti_root_path/images/cacti_backdrop.gif", "$cacti_export_path/cacti_backdrop.gif");
		copy("$cacti_root_path/images/transparent_line.gif", "$cacti_export_path/transparent_line.gif");
		copy("$cacti_root_path/images/shadow.gif", "$cacti_export_path/shadow.gif");
	}

	/* create the base directory */
	if (!mkdir("$cacti_export_path/graphs", 0755)) {
		export_fatal("Create directory '$cacti_export_paths/graphs' failed.  Can not continue");
	}

	/* if the index file already exists, delete it */
	check_remove($cacti_export_path . "/index.html");

	/* open pointer to the new index file */
	$fp_index = fopen($cacti_export_path . "/index.html", "w");

	/* get a list of all graphs that need exported */
	$graphs = db_fetch_assoc("SELECT
		graph_templates_graph.id,
		graph_templates_graph.local_graph_id,
		graph_templates_graph.height,
		graph_templates_graph.width,
		graph_templates_graph.title_cache,
		graph_templates.name,
		graph_local.host_id
		FROM graph_templates_graph
		LEFT JOIN graph_templates
		ON (graph_templates_graph.graph_template_id=graph_templates.id)
		LEFT JOIN graph_local
		ON (graph_templates_graph.local_graph_id=graph_local.id)
		WHERE graph_templates_graph.local_graph_id!=0
		AND graph_templates_graph.export='on'
		ORDER BY graph_templates_graph.title_cache");

	$rras = db_fetch_assoc("SELECT
		rra.id,
		rra.name
		FROM rra
		ORDER BY steps");

	/* write the html header data to the index file */
	fwrite($fp_index, HTML_HEADER_CLASSIC);
	fwrite($fp_index, HTML_GRAPH_HEADER_ONE_CLASSIC);
	fwrite($fp_index, "<strong>Displaying " . sizeof($graphs) . " Exported Graph" . ((sizeof($graphs) > 1) ? "s" : "") . "</strong>");
	fwrite($fp_index, HTML_GRAPH_HEADER_TWO_CLASSIC);

	/* open a pipe to rrdtool for writing */
	$rrdtool_pipe = rrd_init();

	/* for each graph... */
	$i = 0; $k = 0;
	if ((sizeof($graphs) > 0) && (sizeof($rras) > 0)) {
		foreach ($graphs as $graph) {
			check_remove($cacti_export_path . "graphs/thumb_" . $graph["local_graph_id"] . ".png");
			check_remove($cacti_export_path . "graph_" . $graph["local_graph_id"] . ".html");

			/* settings for preview graphs */
			$graph_data_array["graph_height"] = read_config_option("export_default_height");
			$graph_data_array["graph_width"] = read_config_option("export_default_width");
			$graph_data_array["graph_nolegend"] = true;
			$graph_data_array["export"] = true;
			$graph_data_array["export_filename"] = "graphs/thumb_" . $graph["local_graph_id"] . ".png";

			rrdtool_function_graph($graph["local_graph_id"], 0, $graph_data_array, $rrdtool_pipe);

			$total_graphs_created++;

			/* generate html files for each graph */
			if (!file_exists($cacti_export_path . "/graph_" . $graph["local_graph_id"] . ".html")) {
				$fp_graph_index = fopen($cacti_export_path . "/graph_" . $graph["local_graph_id"] . ".html", "w");

				fwrite($fp_graph_index, HTML_HEADER_CLASSIC);
				fwrite($fp_graph_index, HTML_GRAPH_HEADER_ONE_CLASSIC);
				fwrite($fp_graph_index, "<strong>Graph - " . $graph["title_cache"] . "</strong>");
				fwrite($fp_graph_index, HTML_GRAPH_HEADER_TWO_CLASSIC);
				fwrite($fp_graph_index, "<td>");
			}else{
				$fp_graph_index = NULL;
			}

			/* reset vars for actual graph image creation */
			reset($rras);
			unset($graph_data_array);

			/* generate graphs for each rra */
			foreach ($rras as $rra) {
				$graph_data_array["export"] = true;
				$graph_data_array["export_filename"] = "graphs/graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png";

				rrdtool_function_graph($graph["local_graph_id"], $rra["id"], $graph_data_array, $rrdtool_pipe);

				$total_graphs_created++;

				/* write image related html */
				fwrite($fp_graph_index, "<div align=center><img src='graphs/graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png' border=0></div>\n
					<div align=center><strong>" . $rra["name"] . "</strong></div><br>");
			}

			fwrite($fp_graph_index, "</td></table>");
			fwrite($fp_graph_index, HTML_GRAPH_FOOTER_CLASSIC);
			fwrite($fp_graph_index, HTML_FOOTER_CLASSIC);
			fclose($fp_graph_index);

			/* main graph page html */
			fwrite($fp_index, "<tr><td align='center' width='42%'><a href='graph_" . $graph["local_graph_id"] . ".html'><img src='graphs/thumb_" . $graph["local_graph_id"] . ".png' border='0' alt='" . $graph["title_cache"] . "'></a></td>\n");

			$i++;
			$k++;

			if (($i == 2) && ($k < count($graphs))) {
				$i = 0;

				fwrite($fp_index, "</tr><tr>");
			}
  		}
	}else{
		fwrite($fp_index, "<td><em>No Graphs Found.</em></td>");
	}

	/* close the rrdtool pipe */
	rrd_close($rrdtool_pipe);

	fwrite($fp_index, HTML_GRAPH_FOOTER_CLASSIC);
	fwrite($fp_index, HTML_FOOTER_CLASSIC);
	fclose($fp_index);

	return $total_graphs_created;
}

function tree_export() {
	global $config;

	$total_graphs_created = 0;

	/* set the user to utilize for establishing export permissions */
	$_SESSION["sess_user_id"] = read_config_option("export_user_id");

	$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id='" . $_SESSION["sess_user_id"] . "'");

	$cacti_root_path   = $config["base_path"];
	$cacti_export_path = read_config_option("path_html_export");

	/* if the selected user has default rights, show all the graphs */
	if ($current_user["policy_trees"] == 1) {
		$trees = db_fetch_assoc("SELECT
			id,
			name
			FROM graph_tree");
	}else{
		/* otherwise, show only those tree's that the user has access to */
		$trees = db_fetch_assoc("SELECT
			graph_tree.id AS id,
			graph_tree.name AS name
			FROM user_auth_perms
			INNER JOIN graph_tree
			ON (user_auth_perms.item_id = graph_tree.id)
			WHERE user_auth_perms.user_id ='" . $current_user["id"] . "'");
	}

	/* if tree isolation is off, create the treeview and graphs directories for the initial hierarchy */
	if (read_config_option("export_tree_isolation") == "off") {
		create_export_directory_structure($cacti_root_path, $cacti_export_path);

		/* construction */
		$total_graphs_created += export_build_tree_single("", "index.html", 0, 0);
	}

	/* now let's populate all the sub trees */
	foreach ($trees as $tree) {
		/* construction */
		if (read_config_option("export_tree_isolation") == "off") {
			$total_graphs_created += export_build_tree_single(clean_up_export_name($tree["name"]), "index.html", $tree["id"], 0);
		}else{
			/* create the base directory */
			if (!is_dir("$cacti_export_path/" . clean_up_export_name($tree["name"]))) {
				if (!mkdir("$cacti_export_path/" . clean_up_export_name($tree["name"]), 0755)) {
					export_fatal("Create directory '" . clean_up_export_name($tree["name"]) . "' failed.  Can not continue");
				}
			}

			create_export_directory_structure($cacti_root_path, $cacti_export_path . "/" . clean_up_export_name($tree["name"]));
			$total_graphs_created += export_build_tree_isolated(clean_up_export_name($tree["name"]), "index.html", $tree["id"], 0);
		}
	}

	return $total_graphs_created;
}

/* create_export_directory_structure - builds the export directory strucutre and copies
   graphics and treeview scripts to those directories.
   @arg $cacti_root_path - the directory where Cacti is installed
        $dir - the export directory where graphs will either be staged or located.
*/
function create_export_directory_structure($cacti_root_path, $dir) {
	/* create the treeview sub-directory */
	if (!is_dir("$dir/treeview")) {
		if (!mkdir("$dir/treeview", 0755)) {
			export_fatal("Create directory '" . $dir . "/treeview' failed.  Can not continue");
		}
	}

	/* create the graphs sub-directory */
	if (!is_dir("$dir/graphs")) {
		if (!mkdir("$dir/graphs", 0755)) {
			export_fatal("Create directory '" . $dir . "/graphs' failed.  Can not continue");
		}
	}

	$treeview_dir = $dir . "/treeview";

	/* css */
	copy("$cacti_root_path/include/main.css", "$dir/main.css");

	/* images for html */
	copy("$cacti_root_path/images/tab_cacti.gif", "$dir/tab_cacti.gif");
	copy("$cacti_root_path/images/cacti_backdrop.gif", "$dir/cacti_backdrop.gif");
	copy("$cacti_root_path/images/transparent_line.gif", "$dir/transparent_line.gif");
	copy("$cacti_root_path/images/shadow.gif", "$dir/shadow.gif");
	copy("$cacti_root_path/images/shadow_gray.gif", "$dir/shadow_gray.gif");

	/* java scripts for the tree */
	copy("$cacti_root_path/include/treeview/ftiens4_export.js", "$treeview_dir/ftiens4.js");
	copy("$cacti_root_path/include/treeview/ua.js", "$treeview_dir/ua.js");

	/* images for the tree */
	copy("$cacti_root_path/include/treeview/ftv2blank.gif", "$treeview_dir/ftv2blank.gif");
	copy("$cacti_root_path/include/treeview/ftv2lastnode.gif", "$treeview_dir/ftv2lastnode.gif");
	copy("$cacti_root_path/include/treeview/ftv2mlastnode.gif", "$treeview_dir/ftv2mlastnode.gif");
	copy("$cacti_root_path/include/treeview/ftv2mnode.gif", "$treeview_dir/ftv2mnode.gif");
	copy("$cacti_root_path/include/treeview/ftv2node.gif", "$treeview_dir/ftv2node.gif");
	copy("$cacti_root_path/include/treeview/ftv2plastnode.gif", "$treeview_dir/ftv2plastnode.gif");
	copy("$cacti_root_path/include/treeview/ftv2pnode.gif", "$treeview_dir/ftv2pnode.gif");
	copy("$cacti_root_path/include/treeview/ftv2vertline.gif", "$treeview_dir/ftv2vertline.gif");
}

/* export_build_tree_single - build the complete exported files for a graph_tree
   @arg $path - the directory where the graph tree is exported
        $filename - the filename of the html file that will be generated
        $tree_id - id of the graph_tree
        $parent_tree_item_id - the id of the upper-level graph_tree_item (0 if root)
        $parent_uri - needed for a Parent directory link
*/
function export_build_tree_single($path, $filename, $tree_id, $parent_tree_item_id) {
	$cacti_export_path = read_config_option("path_html_export");

	$total_graphs_created = 0;

	/* open pointer to the new file */
	if ((substr_count($filename,"index.html")) && ($tree_id != 0)) {
		$fp = fopen($cacti_export_path . "/" . clean_up_export_name(get_tree_name($tree_id)) . "_" . $filename, "w");
	}else{
		$fp = fopen($cacti_export_path . "/" . $filename, "w");
	}

	/* write the html header data to the file */
	fwrite($fp, HTML_HEADER_TREE);

	/* write the code for the tree at the left */
	draw_html_left_tree($fp, $tree_id);

	/* write the associated graphs for this graph_tree_item or graph_tree*/
	fwrite($fp, HTML_GRAPH_HEADER_ONE_TREE);
	if (($parent_tree_item_id == 0) && ($tree_id != 0)) {
		fwrite($fp, "<strong>Tree:</strong> - " . get_tree_name($tree_id) . "</td></tr>");
	}else if ($tree_id == 0) {
		fwrite($fp, "<strong>Exported Graphs Last Updated on :</strong> - " . get_tree_name($tree_id) . "</td></tr>");
	}else {
		$title = get_tree_item_title($parent_tree_item_id);
		fwrite($fp, "<strong>" . $title . "</strong> - Associated Graphs" . "</td></tr>");
	}

	fwrite($fp, "<tr>");

	if ($tree_id != 0) {
		$total_graphs_created += export_build_graphs($fp, $path, $tree_id, $parent_tree_item_id);
	}

	/* write the html footer to the file */
	fwrite($fp, HTML_FOOTER_TREE);
	fclose($fp);

	$total_graphs_created += explore_tree($path,$tree_id,$parent_tree_item_id);

	return $total_graphs_created;
}

function export_build_tree_isolated($path, $filename, $tree_id, $parent_tree_item_id) {
	$cacti_export_path = read_config_option("path_html_export");

	$total_graphs_created = 0;

	/* open pointer to the new file */
	$fp = fopen($cacti_export_path . "/" . $path . "/" . $filename, "w");

	/* write the html header data to the file */
	fwrite($fp, HTML_HEADER_TREE);

	/* write the code for the tree at the left */
	draw_html_left_tree($fp, $tree_id);

	/* write the associated graphs for this graph_tree_item or graph_tree*/
	fwrite($fp, HTML_GRAPH_HEADER_ONE_TREE);
	if ($parent_tree_item_id == 0)  {
		fwrite($fp, "<strong>Tree:</strong> - " . get_tree_name($tree_id) . "</td></tr>");
	}else {
		$title = get_tree_item_title($parent_tree_item_id);
		fwrite($fp, "<strong>" . $title . "</strong> - Associated Graphs" . "</td></tr>");
	}

	fwrite($fp, "<tr>");
	if ($tree_id != 0) {
		$total_graphs_created += export_build_graphs($fp, $path, $tree_id, $parent_tree_item_id);
	}
//	fwrite($fp, HTML_GRAPH_FOOTER_TREE);

	/* write the html footer to the file */
	fwrite($fp, HTML_FOOTER_TREE);
	fclose($fp);

	$total_graphs_created += explore_tree($path,$tree_id,$parent_tree_item_id);

	return $total_graphs_created;
}

function explore_tree($path, $tree_id, $parent_tree_item_id) {
	/* seek graph_tree_items of the tree_id which are NOT graphs but headers */
	$links = db_fetch_assoc("SELECT
		id,
		title,
		host_id
		FROM graph_tree_items
		WHERE rra_id = 0
		AND graph_tree_id = " . $tree_id);

	$total_graphs_created = 0;

	foreach( $links as $link) {
		/* this test gives us the parent of the curent graph_tree_item */
		if (get_parent_id($link["id"], "graph_tree_items", "graph_tree_id = " . $tree_id) == $parent_tree_item_id) {
			if (get_tree_item_type($link["id"]) == "host") {
				if (read_config_option("export_tree_isolation") == "off") {
					$total_graphs_created += export_build_tree_single(clean_up_export_name($path), clean_up_export_name(get_host_description($link["host_id"]) . "_" . $link["id"] . ".html"), $tree_id, $link["id"]);
				}else{
					$total_graphs_created += export_build_tree_isolated(clean_up_export_name($path), clean_up_export_name(get_host_description($link["host_id"]) . "_" . $link["id"] . ".html"), $tree_id, $link["id"]);
				}
			}else {
				/*now, this graph_tree_item is the parent of others graph_tree_items*/
				if (read_config_option("export_tree_isolation") == "off") {
					$total_graphs_created += export_build_tree_single(clean_up_export_name($path), clean_up_export_name($link["title"] . "_" . $link["id"] . ".html"), $tree_id, $link["id"]);
				}else{
					$total_graphs_created += export_build_tree_isolated(clean_up_export_name($path), clean_up_export_name($link["title"] . "_" . $link["id"] . ".html"), $tree_id, $link["id"]);
				}
			}
		}
	}

	return $total_graphs_created;
}

/* export_build_graphs - build the graphs section on an html page
   @arg $fp - file pointer on the html file
        $path - this parameter is needed to make a recursive call of export_build_tree
        $tree_id - id of the graph_tree
        $parent_tree_item_id - the id of the upper-level graph_tree_item (0 if root)
*/
function export_build_graphs($fp, $path, $tree_id, $parent_tree_item_id)  {
	/* start the count of graphs */
	$total_graphs_created = 0;

	$cacti_export_path = read_config_option("path_html_export");

	$req="";
	if (get_tree_item_type($parent_tree_item_id)=="host") {
		$req = "SELECT
			graph_templates_graph.id,
			graph_templates_graph.local_graph_id,
			graph_templates_graph.height,
			graph_templates_graph.width,
			graph_templates_graph.title_cache,
			graph_templates.name,
			graph_local.host_id
			FROM graph_tree_items
			RIGHT JOIN ((graph_templates_graph
			INNER JOIN graph_templates ON graph_templates_graph.graph_template_id = graph_templates.id)
			INNER JOIN graph_local ON graph_templates_graph.local_graph_id = graph_local.id)
			ON graph_tree_items.local_graph_id = graph_templates_graph.local_graph_id
			WHERE (((graph_templates_graph.local_graph_id)<>0)
			AND graph_local.host_id=" . get_host_id($parent_tree_item_id) . "
			AND ((graph_templates_graph.export)='on'))
			ORDER BY graph_templates_graph.title_cache";

// Original code
//		$req="SELECT DISTINCT
//			graph_templates_graph.id,
//			graph_templates_graph.local_graph_id,
//			graph_templates_graph.height,
//			graph_templates_graph.width,
//			graph_templates_graph.title_cache,
//			graph_templates.name,
//			graph_local.host_id
//			FROM graph_templates_graph
//			LEFT JOIN graph_templates
//			ON (graph_templates_graph.graph_template_id=graph_templates.id)
//			LEFT JOIN graph_local
//			ON (graph_templates_graph.local_graph_id=graph_local.id)
//			WHERE graph_local.host_id=".get_host_id($parent_tree_item_id)."
//			AND graph_templates_graph.local_graph_id!=0
//			AND graph_templates_graph.export='on'
//			ORDER BY graph_templates_graph.title_cache";
	}else{
		/* searching for the graph_tree_items of the tree_id which are graphs */
		if ($tree_id == 0) {
			$sql_where = "WHERE graph_templates_graph.local_graph_id!=0
				AND graph_templates_graph.export='on'";
		}else{
			$sql_where = "WHERE graph_tree_items.graph_tree_id =" . $tree_id . "
				AND graph_templates_graph.local_graph_id!=0
				AND graph_templates_graph.export='on'";
		}

		$req ="SELECT
			graph_templates_graph.id,
			graph_templates_graph.local_graph_id,
			graph_templates_graph.height,
			graph_templates_graph.width,
			graph_templates_graph.title_cache,
			graph_templates.name,
			graph_local.host_id,
			graph_tree_items.id AS gtid
			FROM graph_tree_items
			RIGHT JOIN ((graph_templates_graph
			INNER JOIN graph_templates ON graph_templates_graph.graph_template_id = graph_templates.id)
			INNER JOIN graph_local ON graph_templates_graph.local_graph_id = graph_local.id)
			ON graph_tree_items.local_graph_id = graph_templates_graph.local_graph_id
			$sql_where
			ORDER BY graph_templates_graph.title_cache";

// Original code
//		$req="SELECT DISTINCT
//			graph_templates_graph.id,
//			graph_templates_graph.local_graph_id,
//			graph_templates_graph.height,
//			graph_templates_graph.width,
//			graph_templates_graph.title_cache,
//			graph_templates.name,
//			graph_local.host_id,
//			graph_tree_items.id as gtid
//			FROM graph_templates_graph
//			LEFT JOIN graph_tree_items
//			ON (graph_templates_graph.local_graph_id=graph_tree_items.local_graph_id)
//			LEFT JOIN graph_templates
//			ON (graph_templates_graph.graph_template_id=graph_templates.id)
//			LEFT JOIN graph_local
//			ON (graph_templates_graph.local_graph_id=graph_local.id)
//			$sql_where
//			ORDER BY graph_templates_graph.title_cache";
	}

	$graphs=db_fetch_assoc($req);
	$rras = db_fetch_assoc("SELECT
		rra.id,
		rra.name
		FROM rra
		ORDER BY timespan");

	/* open a pipe to rrdtool for writing */
	$rrdtool_pipe = rrd_init();

	/* for each graph... */
	$i = 0;
	foreach($graphs as $graph)  {
		/* this test gives us the graph_tree_items which are just under the parent_graph_tree_item */
		if (((get_tree_item_type($parent_tree_item_id)=="header") ||
			($parent_tree_item_id == 0)) &&
			(get_parent_id($graph["gtid"], "graph_tree_items", "graph_tree_id = " . $tree_id) != $parent_tree_item_id))  {
			/* do nothing */
		}else {
			/* settings for preview graphs */
			$graph_data_array["graph_height"] = read_config_option("export_default_height");
			$graph_data_array["graph_width"] = read_config_option("export_default_width");
			$graph_data_array["graph_nolegend"] = true;
			$graph_data_array["export"] = true;

			if (read_config_option("export_tree_isolation") == "on") {
				$graph_data_array["export_filename"] = "'" . $path . "'/graphs/thumb_" . $graph["local_graph_id"] . ".png";
				$export_filename = $cacti_export_path . "/" . $path . "/graphs/thumb_" . $graph["local_graph_id"] . ".png";
			}else{
				$graph_data_array["export_filename"] = "/graphs/thumb_" . $graph["local_graph_id"] . ".png";
				$export_filename = $cacti_export_path . "/graphs/thumb_" . $graph["local_graph_id"] . ".png";
			}

			if (!file_exists($export_filename)) {
				rrdtool_function_graph($graph["local_graph_id"], 0, $graph_data_array, $rrdtool_pipe);
				$total_graphs_created++;

				/* generate html files for each graph */
				if (read_config_option("export_tree_isolation") == "on") {
					$fp_graph_index = fopen($cacti_export_path . "/" . $path ."/graph_" . $graph["local_graph_id"] . ".html", "w");
				}else{
					$fp_graph_index = fopen($cacti_export_path . "/graph_" . $graph["local_graph_id"] . ".html", "w");
				}

				fwrite($fp_graph_index, HTML_HEADER_TREE);

				/* write the code for the tree at the left */
				draw_html_left_tree($fp_graph_index, $tree_id);

				fwrite($fp_graph_index, HTML_GRAPH_HEADER_ONE_TREE);
				fwrite($fp_graph_index, "<strong>Graph - " . $graph["title_cache"] . "</strong></td></tr>");
				fwrite($fp_graph_index, HTML_GRAPH_HEADER_TWO_TREE);
				fwrite($fp_graph_index, "<td>");

				/* reset vars for actual graph image creation */
				reset($rras);
				unset($graph_data_array);

				/* generate graphs for each rra */
				foreach ($rras as $rra) {
					$graph_data_array["export"] = true;

					if (read_config_option("export_tree_isolation") == "on") {
						$graph_data_array["export_filename"] = "'" . $path . "'/graphs/graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png";
					}else{
						$graph_data_array["export_filename"] = "/graphs/graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png";
					}

					if (!file_exists($cacti_export_path . "/" . $graph_data_array["export_filename"])) {
						rrdtool_function_graph($graph["local_graph_id"], $rra["id"], $graph_data_array, $rrdtool_pipe);
						$total_graphs_created++;
					}else{
						export_log("Duplicate file write attempted.");
					}

					/* write image related html */
					if (read_config_option("export_tree_isolation") == "off") {
						fwrite($fp_graph_index, "<div align=center><img src='graphs/graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png' border=0></div>\n
							<div align=center><strong>".$rra["name"]."</strong></div><br>");
					}else{
						fwrite($fp_graph_index, "<div align=center><img src='" . "graphs/graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png' border=0></div>\n
							<div align=center><strong>".$rra["name"]."</strong></div><br>");
					}
				}

				fwrite($fp_graph_index, "</td></tr></table></td></tr></table>");
				fwrite($fp_graph_index, HTML_FOOTER_TREE);
				fclose($fp_graph_index);

			}else{
				export_log("Duplicate file write attempted");
			}

			/* main graph page html */
			fwrite($fp, "<td align='center' width='42%'><a href='graph_" . $graph["local_graph_id"] . ".html'><img src='graphs/thumb_" . $graph["local_graph_id"] . ".png' border='0' alt='" . $graph["title_cache"] . "'></a></td>\n");
			$i++;
			if (($i == 2)) {
				$i = 0;
				fwrite($fp, "</tr><tr>");
			}
		}
	}

	/* close the rrdtool pipe */
	rrd_close($rrdtool_pipe);

	return $total_graphs_created;
}

function check_remove($filename) {
	if (file_exists($filename) == true) {
		unlink($filename);
	}
}

function get_host_description($host_id) {
	$host = db_fetch_row("SELECT description FROM host WHERE id='".$host_id."'");
	return $host["description"];
}

function get_host_id($tree_item_id) {
	$graph_tree_item=db_fetch_row("SELECT host_id FROM graph_tree_items WHERE id='".$tree_item_id."'");
	return $graph_tree_item["host_id"];
}

function get_tree_name($tree_id) {
	$graph_tree=db_fetch_row("SELECT id, name FROM graph_tree WHERE id='".$tree_id."'");
	return $graph_tree["name"];
}

function get_tree_item_title($tree_item_id) {
	if (get_tree_item_type($tree_item_id) == "host")  {
		$tree_item=db_fetch_row("SELECT host_id FROM graph_tree_items WHERE id='".$tree_item_id."'");
		return get_host_description($tree_item["host_id"]);
	}else{
		$tree_item=db_fetch_row("SELECT title FROM graph_tree_items WHERE id='".$tree_item_id."'");
		return $tree_item["title"];
	}
}

/* clean_up_export_name - runs a string through a series of regular expressions designed to
     eliminate "bad" characters
   @arg $string - the string to modify/clean
   @returns - the modified string */
function clean_up_export_name($string) {
	$string = preg_replace("/[\s\ ]+/", "_", $string);
	$string = preg_replace("/[^a-zA-Z0-9_.]+/", "", $string);
	$string = preg_replace("/_{2,}/", "_", $string);

	return $string;
}

/* $path to the directory to delete or clean */
/* $deldir (optionnal parameter, true as default) delete the diretory (true) or just clean it (false) */
function del_directory($path, $deldir = true) {
	/* check if the directory name have a "/" at the end, add if not */
	if ($path[strlen($path)-1] != "/") {
		$path .= "/";
	}

	/* cascade through the directory structure(s) until they are all delected */
	if (is_dir($path)) {
		$d = opendir($path);
		while ($f = readdir($d)) {
			if ($f != "." && $f != "..") {
				$rf = $path . $f;

				/* if it is a directory, recursive call to the function */
				if (is_dir($rf)) {
					del_directory($rf);
				}else {
					unlink($rf);
				}
			}
		}
		closedir($d);

		/* if $deldir is true, remove the directory */
		if ($deldir) {
			rmdir($path);
		}
	}
}

function draw_html_left_tree($fp, $tree_id)  {
	/* create the treeview representation for the html data */
	grow_dhtml_trees_export($fp,$tree_id);

	fwrite($fp,"<script type='text/javascript'>initializeDocument();</script>\n");
	fwrite($fp,"<script type='text/javascript'>\n");
	fwrite($fp,"var obj;\n");
	fwrite($fp,"obj = findObj(1);\n");
	fwrite($fp,"if (!obj.isOpen) {\n");
	fwrite($fp,"clickOnNode(1);\n");
	fwrite($fp,"}\n");
	fwrite($fp,"clickOnLink(2,'','main');\n");
	fwrite($fp,"</script>\n");
	fwrite($fp,"</td>\n");
	fwrite($fp,"<td valign='top'>\n");
}

function grow_dhtml_trees_export($fp,$tree_id) {
	global $colors, $config;
	include_once($config["library_path"] . "/tree.php");
	include_once($config["library_path"] . "/data_query.php");

	fwrite($fp, "<script type='text/javascript'>\n");
	fwrite($fp,"<!--
			USETEXTLINKS = 1
			STARTALLOPEN = 0
			USEFRAMES = 0
			USEICONS = 0
			WRAPTEXT = 1
			ICONPATH = 'treeview/'
			PERSERVESTATE = 1
			HIGHLIGHT = 1\n");

	if (read_config_option("export_tree_isolation") == "off") {
		$dhtml_tree_base = 0;
	}else{
		$dhtml_tree_base = $tree_id;
	}

	$dhtml_tree = create_dhtml_tree_export($dhtml_tree_base);

	$total_tree_items = sizeof($dhtml_tree) - 1;
	for ($i = 2; $i <= $total_tree_items; $i++) {
		fwrite($fp,$dhtml_tree[$i]);
	}

	fwrite($fp,"foldersTree.treeID = \"t2\"
			//-->\n
			</script>\n");
}

function create_dhtml_tree_export($tree_id) {
	/* record start time */
	list($micro,$seconds) = split(" ", microtime());
	$start = $seconds + $micro;

	$dhtml_tree = array();
	$dhtml_tree[0] = $start;
	$dhtml_tree[1] = read_graph_config_option("expand_hosts");
	$dhtml_tree[2] = "foldersTree = gFld(\"\", \"\")\n";
	$i = 2;

	$i++;

	if ($tree_id == 0) {
		$sql_where = "WHERE graph_tree_items.local_graph_id = 0";
	}else{
		$sql_where = "WHERE graph_tree_items.graph_tree_id=" . $tree_id . "
			AND graph_tree_items.local_graph_id = 0";
	}

	$heirarchy = db_fetch_assoc("SELECT
		graph_tree.name,
		graph_tree.id AS tree_id,
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.order_key,
		graph_tree_items.host_id,
		graph_tree_items.host_grouping_type,
		host.description AS hostname
		FROM graph_tree
		INNER JOIN (graph_tree_items LEFT JOIN host
		ON graph_tree_items.host_id = host.id)
		ON graph_tree.id = graph_tree_items.graph_tree_id
		$sql_where
		ORDER BY graph_tree.name, graph_tree_items.order_key");

	$dhtml_tree_id = 0;

	if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $leaf) {
		if ($dhtml_tree_id <> $leaf["tree_id"]) {
			if (read_config_option("export_tree_isolation") == "on") {
				$dhtml_tree[$i] = "ou0 = insFld(foldersTree, gFld(\"" . get_tree_name($leaf["tree_id"]) . "\", \"index.html\"))\n";
			}else{
				$dhtml_tree[$i] = "ou0 = insFld(foldersTree, gFld(\"" . get_tree_name($leaf["tree_id"]) . "\", \"" . clean_up_export_name(get_tree_name($leaf["tree_id"])) . "_index.html\"))\n";
			}
		}
		$dhtml_tree_id = $leaf["tree_id"];

		$i++;
		$tier = tree_tier($leaf["order_key"]);

		if ($leaf["host_id"] > 0) {  //It's a host
			$dhtml_tree[$i] = "ou" . ($tier) . " = insFld(ou" . ($tier-1) . ", gFld(\"<strong>Host:</strong> " . htmlentities($leaf["hostname"], ENT_QUOTES) . "\", \"" . clean_up_export_name($leaf["hostname"] . "_" . $leaf["id"]) . ".html\"))\n";

			if (read_graph_config_option("expand_hosts") == "on") {
				if ($leaf["host_grouping_type"] == HOST_GROUPING_GRAPH_TEMPLATE) {
					$graph_templates = db_fetch_assoc("SELECT
						graph_templates.id,
						graph_templates.name,
						graph_templates_graph.local_graph_id,
						graph_templates_graph.title_cache
						FROM (graph_local,graph_templates,graph_templates_graph)
						WHERE graph_local.id=graph_templates_graph.local_graph_id
						AND graph_templates_graph.graph_template_id=graph_templates.id
						AND graph_local.host_id=" . $leaf["host_id"] . "
						GROUP BY graph_templates.id
						ORDER BY graph_templates.name");

				 	if (sizeof($graph_templates) > 0) {
						foreach ($graph_templates as $graph_template) {
							$i++;
							$leaf["title"] = htmlentities("title" . $i, ENT_QUOTES);
							$dhtml_tree[$i] = "ou" . ($tier+1) . " = insFld(ou" . ($tier) . ", gFld(\" " . $graph_template["name"] . "\", \"" . clean_up_export_name($leaf["name"] . "/" . $leaf["title"] . "_" . $leaf["id"]) . "_" . $i . ".html\"))\n";
						}
					}
				}else if ($leaf["host_grouping_type"] == HOST_GROUPING_DATA_QUERY_INDEX) {
					$data_queries = db_fetch_assoc("SELECT
						snmp_query.id,
						snmp_query.name
						FROM (graph_local,snmp_query)
						WHERE graph_local.snmp_query_id=snmp_query.id
						AND graph_local.host_id=" . $leaf["host_id"] . "
						GROUP BY snmp_query.id
						ORDER BY snmp_query.name");

					array_push($data_queries, array(
						"id" => "0",
						"name" => "(Non Indexed)"
						));

					if (sizeof($data_queries) > 0) {
					foreach ($data_queries as $data_query) {
						$i++;
						$leaf["title"] = htmlentities("title" . $i, ENT_QUOTES);

						$dhtml_tree[$i] = "ou" . ($tier+1) . " = insFld(ou" . ($tier) . ", gFld(\" " . $data_query["name"] . "\", \"" . clean_up_export_name($leaf["name"] . "/" . $leaf["title"] . "_" . $leaf["id"]) . "_" . $i . ".html\"))\n";

						/* fetch a list of field names that are sorted by the preferred sort field */
						$sort_field_data = get_formatted_data_query_indexes($leaf["host_id"], $data_query["id"]);

						while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
							$i++;
							$dhtml_tree[$i] = "ou" . ($tier+2) . " = insFld(ou" . ($tier+1) . ", gFld(\" " . $sort_field_value . "\", \"" . clean_up_export_name($leaf["name"] . "/" . $leaf["title"] . "_" . $leaf["id"]) . "_" . $i . ".html\"))\n";
						}
					}
					}
				}
			}
		}else {
//			$dhtml_tree[$i] = "ou" . ($tier) . " = insFld(ou" . ($tier-1) . ", gFld(\"" . htmlentities($leaf["title"], ENT_QUOTES) . "\", \"" . $leaf["name"] . "/" . htmlentities($leaf["title"], ENT_QUOTES) . "_" . $leaf["id"]. ".html\"))\n";
			$dhtml_tree[$i] = "ou" . ($tier) . " = insFld(ou" . ($tier-1) . ", gFld(\"" . htmlentities($leaf["title"], ENT_QUOTES) . "\", \"" . clean_up_export_name($leaf["title"] . "_" . $leaf["id"]). ".html\"))\n";
		}
	}
	}

	return $dhtml_tree;
}

define("HTML_HEADER_TREE",
"<html>
<head>
	<title>cacti</title>
	<link href='main.css' rel='stylesheet'>
	<meta http-equiv=refresh content='300'; url='index.html'>
	<meta http-equiv=Pragma content=no-cache>
	<meta http-equiv=cache-control content=no-cache>
	<script type=\"text/javascript\" src=\"./treeview/ua.js\"></script>
	<script type=\"text/javascript\" src=\"./treeview/ftiens4.js\"></script>
</head>

<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>

<table width='100%' height='100%' cellspacing='0' cellpadding='0'>
	<tr height='37' bgcolor='#a9a9a9'>
		<td colspan='2' valign='bottom' nowrap>
			<table width='100%' cellspacing='0' cellpadding='0'>
				<tr>
					<td nowrap>
						&nbsp;<a href='http://www.cacti.net/'><img src='tab_cacti.gif' alt='Cacti - http://www.cacti.net/' align='absmiddle' border='0'></a>
					</td>
					<td align='right'>
						<img src='cacti_backdrop.gif' align='absmiddle'>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr height='2' colspan='2' bgcolor='#183c8f'>
		<td colspan='2'>
			<img src='transparent_line.gif' width='170' height='2' border='0'><br>
		</td>
	</tr>
	<tr height='5' bgcolor='#e9e9e9'>
		<td colspan='2'>
			<table width='100%'>
				<tr>
					<td>
						Exported Graphs
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td bgcolor='#efefef' colspan='1' height='8' style='background-image: url(shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;'>
			<img src='transparent_line.gif' width='200' height='2' border='0'><br>
		</td>
		<td bgcolor='#ffffff' colspan='1' height='8' style='background-image: url(shadow.gif); background-repeat: repeat-x;'>
		</td>
	</tr>
	<tr>
		<td valign='top' style='padding: 5px; border-right: #aaaaaa 1px solid;' bgcolor='#efefef' width='200'>
			<table border=0 cellpadding=0 cellspacing=0><tr><td><font size=-2><a style='font-size:7pt;text-decoration:none;color:silver' href='http://www.treemenu.net/' target=_blank></a></font></td></tr></table>\n"
);

define("HTML_GRAPH_HEADER_ONE_TREE", "
	<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='3'>
		<tr bgcolor='#6d88ad'>
			<td width='390' colspan='3' class='textHeaderDark'>");

define("HTML_GRAPH_HEADER_TWO_TREE", "
		<tr>"
);

define("HTML_GRAPH_HEADER_ICE_TREE", "</td></tr></table><br><br>			<br>
		</td>
	</tr>
</table>\n");

define("HTML_GRAPH_FOOTER_TREE", "
	</tr></table>\n"
);

define("HTML_FOOTER_TREE", "
</body>
</html>"
);

/* HTML header for the Classic Presentation */
define("HTML_HEADER_CLASSIC", "
	<html>
	<head>
		<title>cacti</title>
		<link href='main.css' rel='stylesheet'>
		<meta http-equiv=refresh content='300'; url='index.html'>
		<meta http-equiv=Pragma content=no-cache>
		<meta http-equiv=cache-control content=no-cache>
	</head>

	<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>

	<table width='100%' cellspacing='0' cellpadding='0'>
		<tr height='37' bgcolor='#a9a9a9'>
			<td valign='bottom' colspan='3' nowrap>
				<table width='100%' cellspacing='0' cellpadding='0'>
					<tr>
						<td valign='bottom'>
							&nbsp;<a href='http://www.cacti.net/'><img src='tab_cacti.gif' alt='Cacti - http://www.cacti.net/' align='absmiddle' border='0'></a>
						</td>
						<td align='right'>
							<img src='cacti_backdrop.gif' align='absmiddle'>
						</td>
					</tr>
				</table>
			</td>
		</tr>\n
		<tr height='2' bgcolor='#183c8f'>
			<td colspan='3'>
				<img src='transparent_line.gif' width='170' height='2' border='0'><br>
			</td>
		</tr>\n
		<tr height='5' bgcolor='#e9e9e9'>
			<td colspan='3'>
				<table width='100%'>
					<tr>
						<td>
							Exported Graphs
						</td>
					</tr>
				</table>
			</td>
		</tr>\n
		<tr>
			<td colspan='3' height='8' style='background-image: url(shadow.gif); background-repeat: repeat-x;' bgcolor='#ffffff'>

			</td>
		</tr>\n
	</table>

	<br>"
);

/* Traditional Graph Export Representation Graph Headers */
define("HTML_GRAPH_HEADER_ONE_CLASSIC", "
	<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
		<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td colspan='2'>
				<table width='100%' cellspacing='0' cellpadding='3' border='0'>
					<tr>
						<td align='center' class='textHeaderDark'>"
);

define("HTML_GRAPH_HEADER_TWO_CLASSIC", "
					</tr>
				</table>
			</td>
		</tr>
		<tr>"
);

define("HTML_GRAPH_FOOTER_CLASSIC", "
	</tr></table>\n
	<br><br>"
);

define("HTML_FOOTER_CLASSIC", "
	<br>

	</body>
	</html>"
);

?>