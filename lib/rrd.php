<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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

define("RRD_NL", " \\\n");

function escape_command($command) {
	return ereg_replace("(\\\$|`)", "", $command);
}

function rrdtool_execute($command_line, $log_command, $output_flag) {
	global $config;
	
	if ($log_command == true) {
		log_data("CMD: " . read_config_option("path_rrdtool") . " $command_line");
	}
	
	if ($output_flag == "") { $output_flag = "1"; }
	
	/* WIN32: before sending this command off to rrdtool, get rid
	of all of the '\' characters. Unix does not care; win32 does. 
	Also make sure to replace all of the fancy \'s at the end of the line,
	but make sure not to get rid of the "\n"'s that are supposed to be
	in there (text format) */
	$command_line = str_replace("\\\n", " ", $command_line);
	
	/* if we want to see the error output from rrdtool; make sure to specify this */
	if ($output_flag == "2") {
		$command_line .= " 2>&1";
	}
	
	/* use popen to eliminate the zombie issue */
	if ($config["cacti_server_os"] == "unix") {
		$fp = popen(read_config_option("path_rrdtool") . escape_command(" $command_line"), "r");
	}elseif ($config["cacti_server_os"] == "win32") {
		$fp = popen(read_config_option("path_rrdtool") . escape_command(" $command_line"), "rb");
	}
	
	/* Return Flag:
	0: Null
	1: Pass output back
	2: Pass error output back */
	
	switch ($output_flag) {
		case '0':
			return; break;
		case '1':
			return fpassthru($fp); break;
		case '2':
			$output = fgets($fp, 1000000);
			
			if (substr($output, 0, 4) == "‰PNG") {
				return "OK";
			}
			
			if (substr($output, 0, 5) == "GIF87") {
				return "OK";
			}
			
			print $output;
			break;
	}
}

function rrdtool_function_create($local_data_id, $show_source) {
	global $config;
	
	include ($config["include_path"] . "/config_arrays.php");
	
	$data_source_path = get_data_source_path($local_data_id, true);
	
	/* ok, if that passes lets check to make sure an rra does not already
	exist, the last thing we want to do is overright data! */
	if ($show_source != true) {
		if (file_exists($data_source_path) == true) {
			return -1;
		}
	}
	
	/* the first thing we must do is make sure there is at least one
	rra associated with this data source... * 
	UPDATE: As of version 0.6.6, we are splitting this up into two
	SQL strings because of the multiple DS per RRD support. This is 
	not a big deal however since this function gets called once per
	data source */
	
	$rras = db_fetch_assoc("select 
		data_template_data.rrd_step,
		rra.x_files_factor,
		rra.steps,
		rra.rows,
		rra_cf.consolidation_function_id,
		(rra.rows*rra.steps) as rra_order
		from data_template_data
		left join data_template_data_rra on data_template_data.id=data_template_data_rra.data_template_data_id
		left join rra on data_template_data_rra.rra_id=rra.id
		left join rra_cf on rra.id=rra_cf.rra_id
		where data_template_data.local_data_id=$local_data_id
		and (rra.steps is not null or rra.rows is not null)
		order by rra_cf.consolidation_function_id,rra_order");
	
	/* if we find that this DS has no RRA associated; get out */
	if (sizeof($rras) <= 0) {
		log_data("ERROR: There are no RRA's assigned to local_data_id: $local_data_id.", true);
		return false;
	}
	
	/* create the "--step" line */
	$create_ds = RRD_NL . "--step ". $rras[0]["rrd_step"] . " " . RRD_NL;
	
	/* query the data sources to be used in this .rrd file */
	$data_sources = db_fetch_assoc("select
		data_template_rrd.id,
		data_template_rrd.rrd_heartbeat,
		data_template_rrd.rrd_minimum,
		data_template_rrd.rrd_maximum,
		data_template_rrd.data_source_type_id
		from data_template_rrd
		where data_template_rrd.local_data_id=$local_data_id");
	
	/* ONLY make a new DS entry if:
	- There is multiple data sources and this item is not the main one.
	- There is only one data source (then use it) */
	
	if (sizeof($data_sources) > 0) {
	foreach ($data_sources as $data_source) {
		/* use the cacti ds name by default or the user defined one, if entered */
		$data_source_name = get_data_source_name($data_source["id"]);
	
		$create_ds .= "DS:$data_source_name:" . $data_source_types{$data_source["data_source_type_id"]} . ":" . $data_source["rrd_heartbeat"] . ":" . $data_source["rrd_minimum"] . ":" . $data_source["rrd_maximum"] . RRD_NL;
	}
	}
	
	$create_rra = "";
	/* loop through each available RRA for this DS */
	foreach ($rras as $rra) {
		$create_rra .= "RRA:" . $consolidation_functions{$rra["consolidation_function_id"]} . ":" . $rra["x_files_factor"] . ":" . $rra["steps"] . ":" . $rra["rows"] . RRD_NL;
	}
	
	if ($show_source == true) {
		return read_config_option("path_rrdtool") . " create" . RRD_NL . "$data_source_path$create_ds$create_rra";
	}else{
		if (read_config_option("log_create") == "on") { $log_data = true; }
		rrdtool_execute("create $data_source_path $create_ds$create_rra",$log_data,1);
	}
}

function rrdtool_function_update($update_cache_array) {
	while (list($local_data_id, $update_array) = each($update_cache_array)) {
		$data_source_path = get_data_source_path($local_data_id, true);
		
		$i = 0; $rrd_update_template = ""; $rrd_update_values = "";
		while (list($field_name, $field_value) = each($update_array)) {
			$rrd_update_template .= $field_name;
			$rrd_update_values .= $field_value;
			
			if (($i+1) < count($update_array)) {
				$rrd_update_template .= ":";
				$rrd_update_values .= ":";
			}
			
			$i++;
		}
		
		if (read_config_option("log_update") == "on") {
			$log_data = true;
		}else{
			$log_data = false;
		}
		
		/* if we have "invalid data", give rrdtool an Unknown (U) */
		if ($rrd_update_values == "") {
			$rrd_update_values = "U";
		}
		
		print "update $data_source_path --template $rrd_update_template N:$rrd_update_values\n";
		rrdtool_execute("update $data_source_path --template $rrd_update_template N:$rrd_update_values",$log_data,1);
	}
}

function rrdtool_function_tune($rrd_tune_array) {
	global $config;
	
	include ($config["include_path"] . "/config_arrays.php");
	
	$data_source_name = get_data_source_name($rrd_tune_array["data_source_id"]);
	$data_source_type = $data_source_types{$rrd_tune_array["data-source-type"]};
	$data_source_path = get_data_source_path($rrd_tune_array["data_source_id"], true);
	
	if ($rrd_tune_array["heartbeat"] != "") {
		$rrd_tune .= " --heartbeat $data_source_name:" . $rrd_tune_array["heartbeat"];
	}
	
	if ($rrd_tune_array["minimum"] != "") {
		$rrd_tune .= " --minimum $data_source_name:" . $rrd_tune_array["minimum"];
	}
	
	if ($rrd_tune_array["maximum"] != "") {
		$rrd_tune .= " --maximum $data_source_name:" . $rrd_tune_array["maximum"];
	}
	
	if ($rrd_tune_array["data-source-type"] != "") {
		$rrd_tune .= " --data-source-type $data_source_name:" . $data_source_type;
	}
	
	if ($rrd_tune_array["data-source-rename"] != "") {
		$rrd_tune .= " --data-source-rename $data_source_name:" . $rrd_tune_array["data-source-rename"];
	}
	
	if ($rrd_tune != "") {
		if (file_exists($data_source_path) == true) {
			$fp = popen(read_config_option("path_rrdtool") . " tune $data_source_path $rrd_tune", "r");
			pclose($fp);
			
			log_data("CMD: " . read_config_option("path_rrdtool") . " tune $data_source_path $rrd_tune");
		}
	}
}

function rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array) {
	global $config;
	
	include_once ($config["include_path"] . "/cdef_functions.php");
	include ($config["include_path"] . "/config_arrays.php");
	
	/* before we do anything; make sure the user has permission to view this graph,
	if not then get out */
	if ((read_config_option("global_auth") == "on") && (isset($_SESSION["sess_user_id"]))) {
		$user_auth = db_fetch_row("select user_id from user_auth_graph where local_graph_id=$local_graph_id and user_id=" . $_SESSION["sess_user_id"]);
		$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);
		
		$access_denied = false;
		
		if ($current_user["graph_policy"] == "1") {
			if (sizeof($user_auth) > 0) { $access_denied = true; }
		}elseif ($current_user["graph_policy"] == "2") {
			if (sizeof($user_auth) == 0) { $access_denied = true; }
		}
		
		if ($access_denied == true) {
			return "GRAPH ACCESS DENIED";
		}
	}
	
	/* define the time span, which decides which rra to use */
	$rra = db_fetch_row("select rows,steps from rra where id=$rra_id");
	
	$timespan = -($rra["rows"] * $rra["steps"] * 144);
	
	$graph = db_fetch_row("select
		graph_templates_graph.title,
		graph_templates_graph.vertical_label,
		graph_templates_graph.auto_scale,
		graph_templates_graph.auto_scale_opts,
		graph_templates_graph.auto_scale_log,
		graph_templates_graph.auto_scale_rigid,
		graph_templates_graph.auto_padding,
		graph_templates_graph.base_value,
		graph_templates_graph.upper_limit,
		graph_templates_graph.lower_limit,
		graph_templates_graph.height,
		graph_templates_graph.width,
		graph_templates_graph.image_format_id,
		graph_templates_graph.unit_value,
		graph_templates_graph.unit_exponent_value,
		graph_templates_graph.export
		from graph_templates_graph
		where graph_templates_graph.local_graph_id=$local_graph_id");
	
    	/* lets make that sql query... */
    	$graph_items = db_fetch_assoc("select
		graph_templates_item.id as graph_templates_item_id,
		graph_templates_item.cdef_id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.consolidation_function_id,
		graph_templates_item.graph_type_id,
		graph_templates_gprint.gprint_text,
		colors.hex,
		data_template_rrd.id as data_template_rrd_id,
		data_template_rrd.local_data_id,
		data_template_rrd.data_source_name
		from graph_templates_item
		left join data_template_rrd on graph_templates_item.task_item_id=data_template_rrd.id
		left join colors on graph_templates_item.color_id=colors.id
		left join graph_templates_gprint on graph_templates_item.gprint_id=graph_templates_gprint.id
		where graph_templates_item.local_graph_id=$local_graph_id
		order by graph_templates_item.sequence");
	
  	/* +++++++++++++++++++++++ GRAPH OPTIONS +++++++++++++++++++++++ */
	
	/* define some variables */
	$scale = "";
	$rigid = "";
	$graph_legend = "";
	$graph_defs = "";
	$txt_graph_items = "";
	$text_padding = "";
	
    	if ($graph["auto_scale"] == "on") {
		if ($graph["auto_scale_opts"] == "1") {
			$scale = "--alt-autoscale" . RRD_NL;
		}elseif ($graph["auto_scale_opts"] == "2") {
			$scale = "--alt-autoscale-max" . RRD_NL;
			$scale .= "--lower-limit=" . $graph["lower_limit"] . RRD_NL; 
		}
		
		if ($graph["auto_scale_log"] == "on") {
			$scale .= "--logarithmic" . RRD_NL;
		}
	}else{
		$scale =  "--upper-limit=" . $graph["upper_limit"] . RRD_NL;
		$scale .= "--lower-limit=" . $graph["lower_limit"] . RRD_NL;
	}
	
	if ($graph["auto_scale_rigid"] == "on") {
		$rigid = "--rigid" . RRD_NL;
	}
	
	if (!empty($graph["unit_value"])) {
		$unit_value = "--unit=" . $graph["unit_value"] . RRD_NL;
	}
	
	if (!empty($graph["unit_exponent_value"])) {
		$unit_exponent_value = "--units-exponent=" . $graph["unit_exponent_value"] . RRD_NL;
	}
	
	/* optionally you can specify and array that overrides some of the db's
	values, lets set that all up here */
	if (isset($graph_data_array["use"])) {
		if ($graph_data_array["graph_start"] == "0") {
			$graph_start = $timespan;
		}else{
			$graph_start = $graph_data_array["graph_start"];
		}
		
		$graph_height = $graph_data_array["graph_height"];
		$graph_width = $graph_data_array["graph_width"];
	}else{
		$graph_start = $timespan;
		$graph_height = $graph["height"];
		$graph_width = $graph["width"];
	}
	
	if (isset($graph_data_array["graph_nolegend"])) {
		$graph_legend = "--no-legend" . RRD_NL;
	}else{
		$graph_legend = "";
	}
    
	/* export options */
	if (isset($graph_data_array["export"])) {
		$graph_opts = read_config_option("path_html_export") . "/" . $graph_data_array["export_filename"] . RRD_NL;
	}else{
		if (empty($graph_data_array["output_filename"])) {
	    		$graph_opts = "-" . RRD_NL;
		}else{
			$graph_opts = $graph_data_array["output_filename"] . RRD_NL;
		}
	}
    
	/* basic graph options */
	$graph_opts .= 
		"--imgformat=" . $image_types{$graph["image_format_id"]} . RRD_NL . 
		"--start=$graph_start" . RRD_NL .
		"--title=\"" . get_graph_title($local_graph_id) . "\"" . RRD_NL .
		"$rigid" .
		"--base=" . $graph["base_value"] . RRD_NL .
		"--height=$graph_height" . RRD_NL .
		"--width=$graph_width" . RRD_NL .
		"$scale" .
		"$graph_legend" .
		"--vertical-label=\"" . $graph["vertical_label"] . "\"" . RRD_NL;
    	
	$i = 0;
    	if (sizeof($graph_items > 0)) {
	foreach ($graph_items as $graph_item) {
		if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_item["graph_type_id"]})) && (!empty($graph_item["data_source_name"]))) {
			$data_source_name = $graph_item["data_source_name"];
			
			/* use a user-specified ds path if one is entered */
			$data_source_path = get_data_source_path($graph_item["local_data_id"], true);
			
			/* FOR WIN32: Ecsape all colon for drive letters (ex. D\:/path/to/rra) */
			$data_source_path = str_replace(":", "\:", $data_source_path);
			
			/* NOTE: (Update) Data source DEF names are created using the graph_item_id; then passed
			to a function that matches the digits with letters. rrdtool likes letters instead
			of numbers in DEF names; especially with CDEF's. cdef's are created
			the same way, except a 'cdef' is put on the beginning of the hash */
			$graph_defs .= "DEF:" . generate_graph_def_name(("$i")) . "=\"$data_source_path\":$data_source_name:" . $consolidation_functions{$graph_item["consolidation_function_id"]} . RRD_NL;
			
			//print "ds: " . $graph_item["data_template_rrd_id"] . "<br>";
			$cf_ds_cache{$graph_item["data_template_rrd_id"]}{$graph_item["consolidation_function_id"]} = "$i";
			
			$i++;
		}
	}
	}
    
	/* if we are not displaying a legend there is no point in us even processing the auto padding,
	text format stuff. */
	if (!isset($graph_data_array["graph_nolegend"])) {
		/* use this loop to to setup all textformat data (hr, padding, subsitution, etc) */
		$greatest_text_format = 0;
		
		reset($graph_items);
		
		if (sizeof($graph_items) > 0) {
		foreach ($graph_items as $graph_item) {
			/* +++++++++++++++++++++++ LEGEND: TEXT SUBSITUTION (<>'s) +++++++++++++++++++++++ */
			
			/* note the current item_id for easy access */
			$graph_item_id = $graph_item["graph_templates_item_id"];
			
			/* format the textformat string, and add values where there are <>'s */
			$text_format[$graph_item_id] = $graph_item["text_format"];
			$value_format[$graph_item_id] = $graph_item["value"];
			
			if (strstr($text_format[$graph_item_id], "|date_time|")) {
				$text_format[$graph_item_id] = str_replace("|date_time|", date('D M H:i:s T Y', strtotime(db_fetch_cell("select value from settings where name='date'"))), $text_format[$graph_item_id]);
			}
			
			/* set hard return variable if selected (\n) */
			if ($graph_item["hard_return"] == "on") { 
				$hardreturn[$graph_item_id] = "\\n"; 
			}else{
				$hardreturn[$graph_item_id] = "";
			}
	    		
			/* +++++++++++++++++++++++ LEGEND: AUTO PADDING (<>'s) +++++++++++++++++++++++ */
			
			/* PADDING: remember this is not perfect! its main use is for the basic graph setup of:
			AREA - GPRINT-CURRENT - GPRINT-AVERAGE - GPRINT-MAXIMUM \n
			of course it can be used in other situations, however may not work as intended.
			If you have any additions to this small peice of code, feel free to send them to me. */
			if ($graph["auto_padding"] == "on") {
				/* only applies to AREA and STACK */
				if (ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_item["graph_type_id"]})) {
					$text_format_lengths{$graph_item["data_template_rrd_id"]} = strlen($text_format[$graph_item_id]);
					
					if ((strlen($text_format[$graph_item_id]) > $greatest_text_format) && ($graph_item_types{$graph_item["graph_type_id"]} != "COMMENT")) {
						$greatest_text_format = strlen($text_format[$graph_item_id]);
					}
				}
			}
		}
		}
    	}
	
    	/* +++++++++++++++++++++++ GRAPH ITEMS: CDEF's +++++++++++++++++++++++ */
    	
	$i = 0;
	reset($graph_items);
		
	if (sizeof($graph_items) > 0) {
	foreach ($graph_items as $graph_item) {
		/* CF rules: if we are using a CF because it's defined in the AREA, STACK, LINE[1-3] then
		it is ok to use it elsewhere on the graph. But it is not ok to use a CF DEF because
		its used in a GPRINT; so check that here */
		if ((isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}{$graph_item["consolidation_function_id"]})) && ($graph_item_types{$graph_item["graph_type_id"]} != "GPRINT")) {
			$cf_id = $graph_item["consolidation_function_id"];
		}else{
			$cf_id = 1; /* CF: AVERAGE */
		}
		
		/* make cdef string here; a note about CDEF's in cacti. A CDEF is neither unique to a 
		data source of global cdef, but is unique when those two variables combine. */
		$cdef_graph_defs = ""; $cdef_total_ds = ""; $cdef_total = "";
		
		if ((!empty($graph_item["cdef_id"])) && (isset($cdef_cache{$graph_item["cdef_id"]}{$graph_item["data_template_rrd_id"]}) == false)) {
			$cdef_string = get_cdef($graph_item["cdef_id"]);
			
			/* create cdef string for "total all data sources" if requested */
			if (ereg("ALL_DATA_SOURCES_(NO)?DUPS", $cdef_string)) {
				$item_count = 0;
				for ($t=0;($t<count($graph_items));$t++) {
					if ((ereg("(AREA|STACK|LINE[123])", $graph_item_types{$graph_items[$t]["graph_type_id"]})) && (!empty($graph_items[$t]["data_template_rrd_id"]))) {
						/* if the user screws up CF settings, PHP will generate warnings if left unchecked */
						if (isset($cf_ds_cache{$graph_items[$t]["data_template_rrd_id"]}[$cf_id])) {
							$cdef_total_ds .= generate_graph_def_name($cf_ds_cache{$graph_items[$t]["data_template_rrd_id"]}[$cf_id]) . ",";
						}
						
						$item_count++;
					}
				}
				
				/* if there is only one item to total, don't even bother with the summation. otherwise
				cdef=a,b,c,+,+ is fine. */
				if ($item_count == 1) {
					$cdef_total = str_replace(",", "", $cdef_total_ds);
				}else{
					$cdef_total = $cdef_total_ds . str_repeat("+,", ($item_count - 2)) . "+";
				}
			}
			
			$cdef_string = str_replace("CURRENT_DATA_SOURCE", generate_graph_def_name((isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id]) ? $cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id] : "0")), $cdef_string);
			$cdef_string = str_replace("ALL_DATA_SOURCES_NODUPS", $cdef_total, $cdef_string);
			
			/* make the initial "virtual" cdef name: 'cdef' + [a,b,c,d...] */
			$cdef_graph_defs .= "CDEF:cdef" . generate_graph_def_name("$i") . "=";
			$cdef_graph_defs .= $cdef_string;
			$cdef_graph_defs .= " \\\n";
			
			/* the CDEF cache is so we do not create duplicate CDEF's on a graph */
			$cdef_cache{$graph_item["cdef_id"]}{$graph_item["data_template_rrd_id"]} = "$i";
		}
		
		/* add the cdef string to the end of the def string */
		$graph_defs .= $cdef_graph_defs;
		
		/* note the current item_id for easy access */
		$graph_item_id = $graph_item["graph_templates_item_id"];
		
		/* if we are not displaying a legend there is no point in us even processing the auto padding,
		text format stuff. */
		if ((!isset($graph_data_array["graph_nolegend"])) && ($graph["auto_padding"] == "on") && (isset($text_format_lengths{$graph_item["data_template_rrd_id"]}))) {
			/* we are basing how much to pad on area and stack text format, 
			not gprint. but of course the padding has to be displayed in gprint,
			how fun! */
			
			$pad_number = ($greatest_text_format - $text_format_lengths{$graph_item["data_template_rrd_id"]});
			//log_data("MAX: $greatest_text_format, CURR: $text_format_lengths[$item_dsid], DSID: $item_dsid");
			$text_padding = str_pad("", $pad_number);
			
			/* two GPRINT's in a row screws up the padding, lets not do that */
			if (($graph_item_types{$graph_item["graph_type_id"]} == "GPRINT") && ($last_graph_type == "GPRINT")) {
				$text_padding = "";
			}
			
			$last_graph_type = $graph_item_types{$graph_item["graph_type_id"]};
		}
		
	
		/* we put this in a variable so it can be manipulated before mainly used
		if we want to skip it, like below */
		$current_graph_item_type = $graph_item_types{$graph_item["graph_type_id"]};
		
		/* IF this graph item has a data source... get a DEF name for it, or the cdef if that applies
		to this graph item */
		if ($graph_item["cdef_id"] == "0") {
			if (isset($cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id])) {
				$data_source_name = generate_graph_def_name($cf_ds_cache{$graph_item["data_template_rrd_id"]}[$cf_id]);
			}else{
				$data_source_name = "";
			}
		}else{
			$data_source_name = "cdef" . generate_graph_def_name($cdef_cache{$graph_item["cdef_id"]}{$graph_item["data_template_rrd_id"]});
		}
		
		/* to make things easier... if there is no text format set; set blank text */
		if (!isset($text_format[$graph_item_id])) {
			$text_format[$graph_item_id] = "";
		}
		
		if (!isset($hardreturn[$graph_item_id])) {
			$hardreturn[$graph_item_id] = "";
		}
		
		/* +++++++++++++++++++++++ GRAPH ITEMS +++++++++++++++++++++++ */
		
		/* this switch statement is basically used to grab all of the graph data above and
		print it out in an rrdtool-friendly fashion, not too much calculation done here. */
		
		switch ($graph_item_types{$graph_item["graph_type_id"]}) {
		case 'AREA':
			$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . 
			$data_source_name . "#" . 
			$graph_item["hex"] . ":" . 
			"\"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		case 'STACK':
			$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . 
			$data_source_name . "#" . 
			$graph_item["hex"] . ":" .
			"\"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		case 'LINE1':
			$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . 
			$data_source_name . "#" . 
			$graph_item["hex"] . ":" . 
			"\"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		case 'LINE2':
			$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . 
			$data_source_name . "#" . 
			$graph_item["hex"] . ":" . 
			"\"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		case 'LINE3':
			$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" . 
			$data_source_name . "#" . 
			$graph_item["hex"] . ":" . 
			"\"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		case 'COMMENT':
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":\"" .
			"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		case 'GPRINT':
			if (!isset($graph_data_array["graph_nolegend"])) {
				$gprint_text = $graph_item["gprint_text"];
				
				$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
				$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" .
				$data_source_name . ":" . $consolidation_functions{$graph_item["consolidation_function_id"]} .
				":\"$text_padding$text_format[$graph_item_id]$gprint_text$hardreturn[$graph_item_id]\" ";
			}
			
			break;
		case 'HRULE':
			$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
			
			if ($graph_data_array["graph_nolegend"] == true) {
				$value_format[$graph_item_id] = "0";
			}else{
				$value_format[$graph_item_id] = str_replace(":", "\:" ,$value_format[$graph_item_id]); /* escape colons */
			}
			
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" .
			$value_format[$graph_item_id] . "#" . $graph_item["hex"] . ":\"" . 
			"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		case 'VRULE':
			$text_format[$graph_item_id] = str_replace(":", "\:" ,$text_format[$graph_item_id]); /* escape colons */
			
			$value_array = explode(":", $graph_item["value"]);
			$value = date("U", mktime($value_array[0],$value_array[1],0));
			
			$txt_graph_items .= $graph_item_types{$graph_item["graph_type_id"]} . ":" .
			$value . "#" . $graph_item["hex"] . ":\"" . 
			"$text_format[$graph_item_id]$hardreturn[$graph_item_id]\" ";
			break;
		}
		
		$i++;
		
		if ($i < sizeof($graph_items)) {
			$txt_graph_items .= RRD_NL;
		}
	}
	}
    
	/* either print out the source or pass the source onto rrdtool to get us a nice PNG */
	if (isset($graph_data_array["print_source"])) {
		print "<PRE>" . read_config_option("path_rrdtool") . " graph $graph_opts$graph_defs$txt_graph_items</PRE>";
	}else{
		if (isset($graph_data_array["export"])) {
			rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items", false, "0");
			return 0;
		}else{
			$log_data = false;
			if (read_config_option("log_graph") == "on") { $log_data = true; }
			if (!isset($graph_data_array["output_flag"])) { $graph_data_array["output_flag"] = 1; }
			
			return rrdtool_execute("graph $graph_opts$graph_defs$txt_graph_items",$log_data,$graph_data_array["output_flag"]);
		}
	}
}

?>
