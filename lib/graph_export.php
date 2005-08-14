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

function config_graph_export() {
	switch (read_config_option("export_type")) {
		case "local":
			$total_graphs_created = export();
			break;
		case "ftp_php":
			// set the temp directory
			$stExportDir = $_ENV["TMP"].'/cacti-ftp-temp';
			$total_graphs_created = export_pre_ftp_upload($stExportDir);
			export_log("Using PHP built-in FTP functions.");
			export_ftp_php_execute($stExportDir);
			export_post_ftp_upload($stExportDir);
			break;
		case "ftp_ncftpput":
			if (strstr(PHP_OS, "WIN")) export_fatal("ncftpput only available in unix environment!  Export can not continue.");
			// set the temp directory
			$stExportDir = $_ENV["TMP"].'/cacti-ftp-temp';
			$total_graphs_created = export_pre_ftp_upload($stExportDir);
			export_log("Using ncftpput.");
			export_ftp_ncftpput_execute($stExportDir);
			export_post_ftp_upload($stExportDir);
			break;
		case "disabled":
			break;
		default:
			export_log("Export method not specified. Updated config to use local exporting.");
			db_execute("insert into settings (name,value) values ('export_type','local')");
	}

	return $total_graphs_created;
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
	// export variable as global
	$_SESSION["sess_config_array"]["path_html_export"] = $stExportDir;
	// clean-up after last cacti instance
	if (is_dir($stExportDir)) {
		if ($dh = opendir($stExportDir)) {
			while (($file = readdir($dh)) !== false) {
				$filePath = $stExportDir."/".$file;
				if ($file != "." && $file != ".." && !is_dir($filePath)) {
					unlink($filePath);
				};
			};
			closedir($dh);
		};
	} else {
		@mkdir($stExportDir);
	}
	// go export
	$total_graphs_created = export();
	// force reaing of the variable from the database
	unset($_SESSION["sess_config_array"]["path_html_export"]);

	$aFtpExport['server'] = read_config_option('export_ftp_host');
	if (empty($aFtpExport['server'])) {
		die("EXPORT (fatal): FTP Hostname is not expected to be blank!");
	}

	$aFtpExport['remotedir'] = read_config_option('path_html_export');
	if (empty($aFtpExport['remotedir'])) {
		die("EXPORT (fatal): FTP Remote export path is not expected to be blank!");
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
	} else {
	$aFtpExport['passive'] = FALSE;
		export_log("Using active transfer method.");
	}

	return $total_graphs_created;
}

function export_ftp_php_execute($stExportDir) {
	global $aFtpExport;

	$oFtpConnection = ftp_connect($aFtpExport['server'], $aFtpExport['port']);
	if (!$oFtpConnection) {
		export_fatal("FTP Connection failed! Check hostname and port.  Export can not continue.");
	} else {
		export_log("Conection to remote server was successful.");
	}

	if (!ftp_login($oFtpConnection, $aFtpExport['username'], $aFtpExport['password'])) {
		ftp_close($oFtpConnection);
		export_fatal("FTP Login failed! Check username and password.  Export can not continue.");
	} else {
		export_log("Remote login was successful.");
	}

	if ($aFtpExport['passive']) {
		ftp_pasv($oFtpConnection, TRUE);
	} else {
		ftp_pasv($oFtpConnection, FALSE);
	}

	if (!@ftp_chdir($oFtpConnection, $aFtpExport['remotedir'])) {
		ftp_close($oFtpConnection);
		export_fatal("FTP Remote directory '" . $aFtpExport['remotedir'] . "' does not exist!.  Export can not continue.");
	}

	// sanitize remote path
	if (read_config_option('export_ftp_sanitize') == 'on') {
		export_log("Deleting remote files.");
		$aFtpRemoteFiles = ftp_nlist($oFtpConnection, $aFtpExport['remotedir']);
		if (is_array($aFtpRemoteFiles)) {
			foreach ($aFtpRemoteFiles as $stFile) {
				ftp_delete($oFtpConnection, $aFtpExport['remotedir'].'/'.$stFile);
			}
		}
	}

	if ($dh = opendir($stExportDir)) {
		export_log("Uploading files to remote location.");
		while (($file = readdir($dh)) !== false) {
			$filePath = $stExportDir."/".$file;
			if ($file != "." && $file != ".." && !is_dir($filePath)) {
				if (!ftp_put($oFtpConnection, $aFtpExport['remotedir'].'/'.$file, $filePath, FTP_BINARY)) {
				export_log("Failed to upload '$file'.");
				}
			}
		}
		closedir($dh);
	}
	ftp_close($oFtpConnection);
	export_log("Closed ftp connection.");
}

function export_ftp_ncftpput_execute($stExportDir) {
	global $aFtpExport;

	chdir($stExportDir);
	$stExecute = 'ncftpput -V -r 1 -u '.$aFtpExport['username'].' -p '.$aFtpExport['password'];
	if ($aFtpExport['passive']) {
		$stExecute .= ' -F ';
	}
	$stExecute .= ' -P '.$aFtpExport['port'].' '.$aFtpExport['server'].' '.$aFtpExport['remotedir'];

	if ($dh = opendir($stExportDir)) {
		while (($file = readdir($dh)) !== false) {
			if ($file != "." && $file != ".." && !is_dir($stExportDir."/".$file)) {
				$stExecute .= " $file";
			}
		}
		closedir($dh);
		system($stExecute, $iExecuteReturns);

		$aNcftpputStatusCodes = array ('Success.', 'Could not connect to remote host.', 'Could not connect to remote host - timed out.', 'Transfer failed.', 'Transfer failed - timed out.', 'Directory change failed.', 'Directory change failed - timed out.', 'Malformed URL.', 'Usage error.', 'Error in login configuration file.', 'Library initialization failed.', 'Session initialization failed.');

		export_log('Ncftpput returned: '.$aNcftpputStatusCodes[$iExecuteReturns]);
	}
}

function export_post_ftp_upload($stExportDir) {
	// clean-up after ftp-put
	if ($dh = opendir($stExportDir)) {
		while (($file = readdir($dh)) !== false) {
			$filePath = $stExportDir."/".$file;
			if ($file != "." && $file != ".." && !is_dir($filePath)) {
				unlink($filePath);
			}
		}
		closedir($dh);
		rmdir($stExportDir);
	}
}

function export() {
	global $config;

	/* count how many graphs are created */
	$total_graphs_created = 0;

	if (!file_exists(read_config_option("path_html_export"))) {
		export_fatal("Export path '" . read_config_option("path_html_export") . "' does not exist!  Export can not continue.");
	}

	export_log("Running graph export");

	$cacti_root_path = $config["base_path"];
	$cacti_export_path = read_config_option("path_html_export");

	/* copy the css/images on the first time */
	if (file_exists("$cacti_export_path/main.css") == false) {
		copy("$cacti_root_path/include/main.css", "$cacti_export_path/main.css");
		copy("$cacti_root_path/images/tab_cacti.gif", "$cacti_export_path/tab_cacti.gif");
		copy("$cacti_root_path/images/cacti_backdrop.gif", "$cacti_export_path/cacti_backdrop.gif");
		copy("$cacti_root_path/images/transparent_line.gif", "$cacti_export_path/transparent_line.gif");
		copy("$cacti_root_path/images/shadow.gif", "$cacti_export_path/shadow.gif");
	}

	/* if the index file already exists, delete it */
	check_remove($cacti_export_path . "/index.html");

	/* open pointer to the new index file */
	$fp_index = fopen($cacti_export_path . "/index.html", "w");

	/* get a list of all graphs that need exported */
	$graphs = db_fetch_assoc("select
		graph_templates_graph.id,
		graph_templates_graph.local_graph_id,
		graph_templates_graph.height,
		graph_templates_graph.width,
		graph_templates_graph.title_cache,
		graph_templates.name,
		graph_local.host_id
		from graph_templates_graph left join graph_templates on graph_templates_graph.graph_template_id=graph_templates.id
		left join graph_local on graph_templates_graph.local_graph_id=graph_local.id
		where graph_templates_graph.local_graph_id!=0 and graph_templates_graph.export='on'
		order by graph_templates_graph.title_cache");
	$rras = db_fetch_assoc("select
		rra.id,
		rra.name
		from rra
		order by timespan");

	/* write the html header data to the index file */
	fwrite($fp_index, HTML_HEADER);
	fwrite($fp_index, HTML_GRAPH_HEADER_ONE);
	fwrite($fp_index, "<strong>Displaying " . sizeof($graphs) . " Exported Graph" . ((sizeof($graphs) > 1) ? "s" : "") . "</strong>");
	fwrite($fp_index, HTML_GRAPH_HEADER_TWO);

	/* open a pipe to rrdtool for writing */
	$rrdtool_pipe = rrd_init();

	/* for each graph... */
	$i = 0; $k = 0;
	if ((sizeof($graphs) > 0) && (sizeof($rras) > 0)) {
	foreach ($graphs as $graph) {
		check_remove($cacti_export_path . "/thumb_" . $graph["local_graph_id"] . ".png");
		check_remove($cacti_export_path . "/graph_" . $graph["local_graph_id"] . ".html");

		/* settings for preview graphs */
		$graph_data_array["graph_height"] = "100";
		$graph_data_array["graph_width"] = "300";
		$graph_data_array["graph_nolegend"] = true;
		$graph_data_array["export"] = true;
		$graph_data_array["export_filename"] = "thumb_" . $graph["local_graph_id"] . ".png";

		rrdtool_function_graph($graph["local_graph_id"], 0, $graph_data_array, $rrdtool_pipe);
		$total_graphs_created++;

		/* generate html files for each graph */
		$fp_graph_index = fopen($cacti_export_path . "/graph_" . $graph["local_graph_id"] . ".html", "w");

		fwrite($fp_graph_index, HTML_HEADER);
		fwrite($fp_graph_index, HTML_GRAPH_HEADER_ONE);
		fwrite($fp_graph_index, "<strong>Graph - " . $graph["title_cache"] . "</strong>");
		fwrite($fp_graph_index, HTML_GRAPH_HEADER_TWO);
		fwrite($fp_graph_index, "<td>");

		/* reset vars for actual graph image creation */
		reset($rras);
		unset($graph_data_array);

		/* generate graphs for each rra */
		foreach ($rras as $rra) {
			$graph_data_array["export"] = true;
			$graph_data_array["export_filename"] = "graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png";

			rrdtool_function_graph($graph["local_graph_id"], $rra["id"], $graph_data_array, $rrdtool_pipe);
			$total_graphs_created++;

			/* write image related html */
			fwrite($fp_graph_index, "<div align=center><img src='graph_" . $graph["local_graph_id"] . "_" . $rra["id"] . ".png' border=0></div>\n
				<div align=center><strong>" . $rra["name"] . "</strong></div><br>");
		}

		fwrite($fp_graph_index, "</td>");
		fwrite($fp_graph_index, HTML_GRAPH_FOOTER);
		fwrite($fp_graph_index, HTML_FOOTER);
		fclose($fp_graph_index);

		/* main graph page html */
		fwrite($fp_index, "<td align='center' width='" . (98 / 2) . "%'><a href='graph_" . $graph["local_graph_id"] . ".html'><img src='thumb_" . $graph["local_graph_id"] . ".png' border='0' alt='" . $graph["title_cache"] . "'></a></td>\n");

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

	fwrite($fp_index, HTML_GRAPH_FOOTER);
	fwrite($fp_index, HTML_FOOTER);
	fclose($fp_index);

	return $total_graphs_created;
}

function check_remove($filename) {
	if (file_exists($filename) == true) {
		unlink($filename);
	}
}

define("HTML_GRAPH_HEADER_ONE", "
	<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>
		<tr bgcolor='#" . $colors["header_panel"] . "'>
			<td colspan='2'>
				<table width='100%' cellspacing='0' cellpadding='3' border='0'>
					<tr>
						<td align='center' class='textHeaderDark'>");

define("HTML_GRAPH_HEADER_TWO", "
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>");

define("HTML_GRAPH_FOOTER", "
	</tr></table>\n
	<br><br>");

define("HTML_HEADER", "
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
							&nbsp;<a href='http://www.raxnet.net/products/cacti/'><img src='tab_cacti.gif' alt='Cacti - http://www.raxnet.net/products/cacti/' align='absmiddle' border='0'></a>
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

	<br>");

define("HTML_FOOTER", "
	<br>

	</body>
	</html>");

?>