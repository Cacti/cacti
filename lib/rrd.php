<?/*
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
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
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?

function rrdtool_execute($command_line, $log_command, $output_flag) {
	include ('include/config.php');
	include_once ('include/functions.php');
	
	if ($log_command == true) {
		LogData("CMD: " . read_config_option("path_rrdtool") . " $command_line");
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
	if ($config[cacti_server_os] == "unix") {
		$fp = popen(read_config_option("path_rrdtool") . " $command_line", "r");
	}elseif ($config[cacti_server_os] == "win32") {
		$fp = popen(read_config_option("path_rrdtool") . " $command_line", "rb");
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

function rrdtool_function_create($dsid, $show_source) {
    global $config;
    include_once ('include/functions.php');
    
    $data_source_path = GetDataSourcePath($dsid, true);
    
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
    
    $rra_list = db_fetch_assoc("select 
		d.Step,
		r.*,
		c.name as CName,
		(r.rows*r.steps) as RS
		from rrd_ds d left join lnk_ds_rra l on l.dsid=d.id
		left join rrd_rra r on l.rraid=r.id
		left join lnk_rra_cf rc on rc.rraid=r.id
		left join def_cf c on rc.consolidationfunctionid=c.id
		where d.id=$dsid
		order by rc.consolidationfunctionid, rs");
    
    /* if we find that this DS has no RRA associated; get out */
    if (sizeof($rra_list) <= 0) {
		LogData("There are no RRA's assigned to DSID $dsid!");
		return -1;
    }
    
    /* create the "--step" line */
    $create_ds .= " \\\n--step ".$rra_list[0][Step]." \\\n";
    
    /* query the data sources to be used in this .rrd file */
    $ds_list = db_fetch_assoc("select 
		d.ID, d.Heartbeat, d.MinValue, d.MaxValue,d.SubDSID,
		t.Name
		from rrd_ds d left join def_ds t on d.datasourcetypeid=t.id
		where d.id=$dsid
		or d.subdsid=$dsid
		order by d.id");
    $rows = sizeof($ds_list);
    
    /* ONLY make a new DS entry if:
     - There is multiple data sources and this item is not the main one.
     - There is only one data source (then use it) */
    
    if ($rows > 0) {
		foreach ($ds_list as $ds) {
		    if ((($rows > 1) && ($ds[SubDSID] != 0)) || ($rows == 1)) {
				/* use the cacti ds name by default or the user defined one, if entered */
				$data_source_name = GetDataSourceName($ds[ID]);
				
				$create_ds .= "DS:$data_source_name:" . 
					$ds[Name] . ":" . 
					$ds[Heartbeat] . ":" . 
					$ds[MinValue] . ":" . 
					$ds[MaxValue] . " \\\n";
			}
		}
    }
    
    /* loop through each available RRA for this DS */
    
    foreach ($rra_list as $rra) {
		$create_rra .= "RRA:$rra[CName]:$rra[XFilesFactor]:$rra[Steps]:$rra[Rows] \\\n";
    }
    
    if ($show_source == true) {
		return read_config_option("path_rrdtool") . " create \\\n$data_source_path$create_ds$create_rra";
    }else{
		if (read_config_option("log_create") == "on") { $log_data = true; }
		rrdtool_execute("create $data_source_path $create_ds$create_rra",$log_data,1);
    }
}

function rrdtool_function_update($dsid, $multi_data_source, $show_source) {
    global $config;
	include_once ('include/functions.php');
	
    $data_source_path = GetDataSourcePath($dsid, true);
    
    if ($multi_data_source == "") {
		/* find out if this DS has multiple outputs */
		$multi = db_fetch_assoc("select ID from rrd_ds where subdsid=$dsid");
		
		if (sizeof($multi) <= 0) {
		    $multi_data_source = false;
		}else{
		    $multi_data_source = true;
		}
    }
    
    if ($multi_data_source == true) {
		/* multi DS: This string joins on the rrd_ds->src_fields to get the 
		field names */
		$data = db_fetch_assoc("select 
			d.DSName,
			a.Value
			from rrd_ds d left join src_fields f on d.subfieldid=f.id 
			left join src_data a on d.id=a.dsid
			where d.subdsid=$dsid
			and f.inputoutput=\"out\"
			and f.updaterra=\"on\"
			order by d.id");
    }else{
		/* single DS: This string joins on the src_data->src_fields table to get 
		the field name */
		$data = db_fetch_assoc("select 
			d.DSName,
			a.Value
			from rrd_ds d left join src_data a on d.id=a.dsid
			left join src_fields f on a.fieldid=f.id 
			where d.id=$dsid
			and f.inputoutput=\"out\"
			and f.updaterra=\"on\"
			order by d.id");
    }
    
    /* setup the counter */
    $rows = sizeof($data);
    
    /* set initial values for strings to be used in the loop */
    $update_string = "N";
    $template_string = "--template ";
    
    $i = 0;
    /* loop through each item in this data source and build the UPDATE string */
    if (sizeof($data) > 0) {
		foreach ($data as $item) {
		    ++$i;
		    if (trim($data[Value]) == "") {
				$data_value = "U"; /* rrdtool: unknown */
		    }else{
				$data_value = $data[Value];
		    }
		    
		    $update_string .= ":$data_value";
		    $template_string .= $data[DSName];
		    
		    /* do NOT put a colon after the last template item */
		    if ($i != sizeof($data)) { $template_string .= ":"; }
		}
    }
    
    $update_string = "update $data_source_path $template_string $update_string";
    
    if ($show_source == true) {
		return read_config_option("path_rrdtool") . " $update_string";
    }else{
		if (read_config_option("log_update") == "on") { $log_data = true; }
		rrdtool_execute($update_string,true,1);
    }
}

function rrdtool_function_tune($rrd_tune_array) {
    global $config,$paths;
	include_once ('include/functions.php');
	
	$data_source_name = GetDataSourceName($rrd_tune_array["data_source_id"]);
	$data_source_type = GetDataSourceType($rrd_tune_array["data-source-type"]);
	$data_source_path = GetDataSourcePath($rrd_tune_array["data_source_id"], true);
	
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
			
			LogData("CMD: " . read_config_option("path_rrdtool") . " tune $data_source_path $rrd_tune");
		}
 	}
}

function rrdtool_function_graph($graphid, $rra, $graph_data_array) {
    global $config,$paths;
    include_once ('include/functions.php');
    
    /* before we do anything; make sure the user has permission to view this graph,
     if not then get out */
    if (read_config_option("global_auth") == "on") {
		global $HTTP_COOKIE_VARS;
		
		$user_auth = db_fetch_row("select UserID from auth_graph where graphid=$graphid and userid=" . GetCurrentUserID($HTTP_COOKIE_VARS["cactilogin"],read_config_option("guest_user")));
		
		if ($config["graph_policy"]["auth"] == "1") {
			if (sizeof($user_auth) > 0) { $access_denied = true; }
		}elseif ($config["graph_policy"]["auth"] == "2") {
			if (sizeof($user_auth) <= 0) { $access_denied = true; }
		}
		
		if ($access_denied == true) {
		    return "GRAPH ACCESS DENIED";
		}
    }
    
    $graph = db_fetch_row("select g.*, i.Name from rrd_graph g left join def_image_type i 
		on g.imageformatid=i.id where g.id=$graphid");
    
    /* define the time span, which decides which rra to use */
    $this_rra = db_fetch_row("select Rows,Steps from rrd_rra where id=$rra");
    $timespan = -($this_rra[Rows] * $this_rra[Steps] * 144);
	
	/* this is so we do not show the data for MIN/MAX data on daily graphs (steps <= 1),
	this code is a little hacked at the moment as GPRINT's are not covered. some changes will have
	to be made for this to be included also */
    if ($rra[Steps] > 1) {
		$sql_order_by = "";
	}else{
		$sql_order_by = "and !(cf.id != 1 and (t.name=\"LINE1\" or t.name=\"AREA\" or t.name=\"STACK\"
			or t.name=\"LINE2\" or t.name=\"LINE3\"))";
    }
    
    /* lets make that sql query... */
    $items = db_fetch_assoc("select 
		i.ID as IID, i.CDEFID, i.TextFormat, i.Value, i.HardReturn, i.ConsolidationFunction, i.Parent, i.GprintOpts, i.GprintCustom,
		c.Hex, 
		d.DSName, d.Name as DName, d.ID,
		cf.name as CName, t.name as TName 
		from rrd_graph_item i 
		left join rrd_ds d 
		on i.dsid=d.id 
		left join def_colors c 
		on i.colorid=c.id 
		left join def_graph_type t 
		on i.graphtypeid=t.id 
		left join def_cf cf 
		on i.consolidationfunction=cf.id 
		where i.graphid=$graphid 
		$sql_order_by
		order by i.sequenceparent,i.sequence");
    $rows = sizeof($items);
	
    /* +-------------------------------------------------------------------+
     |                           GRAPH OPTIONS                           |
     +-------------------------------------------------------------------+ */
    
    if ($graph[AutoScale] == "on") {
		switch ($graph[AutoScaleOpts]) {
	 		case "1":
	    		$scale .= "--alt-autoscale" . " \\\n";
				break;
	 		case "2":
	    		$scale .= "--alt-autoscale-max" . " \\\n";
	    		$scale .= "--lower-limit=" . mysql_result($sql_id, 0, "lowerlimit") . " \\\n"; 
			break;
		}
		
		if ($graph[AutoScaleLog] == "on") {
			$scale .= "--logarithmic" . " \\\n";
		}
    }else{
		$scale = "--upper-limit=$graph[UpperLimit]  \\\n" .
			"--lower-limit=$graph[LowerLimit] \\\n";
    }
    
    if ($graph[Rigid] == "on") {
		$rigid = "--rigid" . " \\\n";
    }
	
	if ($graph[UnitValue] != "") {
		$unit_value = "--unit=" . $graph[UnitValue] . " \\\n";
	}
	
	if ($graph[UnitExponentValue] != "0") {
		$unit_exponent_value = "--units-exponent=" . $graph[UnitExponentValue] . " \\\n";
	}
    
    /* optionally you can specify and array that overrides some of the db's
     values, lets set that all up here */
    if ($graph_data_array["use"] == true) {
		if ($graph_data_array["graph_start"] == "0") {
	    	$graph_start = $timespan;
		}else{
	    	$graph_start = $graph_data_array["graph_start"];
		}
		
		$graph_height = $graph_data_array["graph_height"];
		$graph_width = $graph_data_array["graph_width"];
    }else{
		$graph_start = $timespan;
		$graph_height = $graph[Height];
		$graph_width = $graph[Width];
    }
    
    if ($graph_data_array["graph_nolegend"] == true) {
		$graph_legend = "--no-legend" . " \\\n";
    }else{
		$graph_legend = "";
    }
    
    /* export options */
    if ($graph_data_array["export"] == true) {
		$graph_opts = read_config_option("path_html_export") . "/" . $graph_data_array["export_filename"] . " \\\n";
    }else{
		if ($graph_data_array["output_filename"] == "") {
		    $graph_opts = "- \\\n";
		}else{
		    $graph_opts = $graph_data_array["output_filename"] . " \\\n";
		}
    }
    
    /* basic graph options */
    $graph_opts .= "--imgformat=$graph[Name]  \\\n" .
		"--start=\"$graph_start\" \\\n" .
		"--title=\"$graph[Title]\" \\\n" .
		"$rigid" .
		"--base=$graph[BaseValue] \\\n" .
		"--height=$graph_height \\\n" .
		"--width=$graph_width \\\n" .
		"$scale" .
		"$graph_legend" .
		"--vertical-label=\"$graph[VerticalLabel]\" \\\n";
    
    /* a note about different CF's on a graph. for now we are only going to display MAX/MIN CF
	data when viewing anything greater than daily graphs. From what I know, rrdtool does not
	store AVERAGE MAX/MIN data for any RRA with less than 1 step */
    if ($rra[teps] > 1) {
		$sql_group_by = "group by d.id,i.consolidationfunction";
    }else{
		$sql_group_by = "and c.name=\"AVERAGE\" group by d.id";
    }
    
    /* +-------------------------------------------------------------------+
     |                           GRAPH DEFS                              |
     +-------------------------------------------------------------------+ */
    
    /* define the datasources used; only once */
    $defs = db_fetch_assoc("select 
		i.id as IID, i.CDEFID, i.ConsolidationFunction,
		d.ID, d.Name, d.DSName, d.DSPath,
		c.Name as CName
		from rrd_graph_item i 
		left join rrd_ds d 
		on i.dsid=d.id 
		left join def_cf c 
		on i.consolidationfunction=c.id
		left join def_graph_type t 
		on i.graphtypeid=t.id
		where i.graphid=$graphid 
		and (t.name=\"AREA\" or t.name=\"STACK\" 
		or t.name=\"LINE1\" or t.name=\"LINE2\" 
		or t.name=\"LINE3\") 
		and d.dsname is not null
		$sql_group_by");
    $rows_defs = sizeof($defs);
    
    $i = 0;
    while ($i < $rows_defs) {
 		$def = $defs[$i];
		
		if ($def[dsname] == "") {
		    $dsname = CheckDataSourceName($def[Name]);
		}else{
		    $dsname = $def[DSName];
		}
		
		/* use a user-specified ds path if one is entered */
		$dspath = GetDataSourcePath($def[ID], true);
		
		/* FOR WIN32: Ecsape all colon for drive letters (ex. D\:/path/to/rra) */
		$dspath = str_replace(":", "\:", $dspath);
		
		/* NOTE: (Update) Data source DEF names are created using the graph_item_id; then passed
		 to a function that matches the digits with letters. rrdtool likes letters instead
		 of numbers in DEF names; especially with CDEF's. cdef's are created
		 the same way, except a 'cdef' is put on the beginning of the hash */
		
		if ($dsname != "") {
		    $graph_defs .= "DEF:" . CreateGraphDefName(("$i")) . "=\"$dspath\":$dsname:$def[CName] \\\n";
		}
		
		$cf_ds_cache[$def[ID]][$def[ConsolidationFunction]] = "$i";
		$graph_group_cache[$def[IID]] = "$i";
		
		$i++;
    }
    
    /* if we are not displaying a legend there is no point in us even processing the auto padding,
	text format stuff. */
    if ($graph_data_array["graph_nolegend"] != true) {
		/* use this loop to to setup all textformat data (hr, padding, subsitution, etc) */
		$i = 0; $greatest_text_format = 0;
		while ($i < sizeof($items)) {
		    $item = $items[$i];
		    /* +-------------------------------------------------------------------+
		     |                   LEGEND: TEXT SUBSITUTION (<>'s)                 |
		     +-------------------------------------------------------------------+ */
		    
		    /* format the textformat string, and add values where there are <>'s */
		    $text_format[$i] = $item[TextFormat];
		    $value_format[$i] = $item[Value];
		    
		    /* set hard return variable if selected (\n) */
		    if ($item[HardReturn] == "on") { 
				$hardreturn[$i] = "\\n"; 
		    }else{
				$hardreturn[$i] = "";
		    }
		    
		    if ($item[ID] != "") {
			$fields = db_fetch_assoc("select d.FieldID, d.DSID, d.Value, 
				f.SrcID, f.DataName
				from src_data d
				left join src_fields f
				on d.fieldid=f.id
				 where d.dsid=$item[ID]");
			
			if (sizeof($fields) > 0) {
			    foreach ($fields as $field) {
					$text_format[$i] = ereg_replace ("<$field[DataName]>", $field[Value],$text_format[$i]);
					$value_format[$i] = ereg_replace ("<$field[DataName]>", $field[Value],$value_format[$i]);
			    }
			}
		}
	    
	    /*
		+-------------------------------------------------------------------+
		|                       LEGEND: AUTO PADDING                        |
		+-------------------------------------------------------------------+ */
	    
	    /* PADDING: remember this is not perfect! its main use is for the basic graph setup of:
		AREA - GPRINT-CURRENT - GPRINT-AVERAGE - GPRINT-MAXIMUM \n
		of course it can be used in other situations, however may not work as intended.
		If you have any additions to this small peice of code, feel free to send them to me. */
	    if ($graph[AutoPadding] == "on") {
			$item_dsid = $item[ID];
			
			/* only applies to AREA and STACK */
			if (($item[TName] == "AREA") || ($item[TName] == "STACK") || ($item[TName] == "LINE1") || ($item[TName] == "LINE2") || ($item[TName] == "LINE3")) {
			    $text_format_lengths[$item_dsid] = strlen($text_format[$i]);
			    
			    if (strlen($text_format[$i]) > $greatest_text_format) {
					if ($item[TName] != "COMMENT") {
					    $greatest_text_format = strlen($text_format[$i]);
					}
			    }
			}
	    }
	    
	    $i++;
	}
    }
    
    /* draw the actual items on the graph */
    
    /* +-------------------------------------------------------------------+
     |                   GRAPH ITEMS: CDEF's                             |
     +-------------------------------------------------------------------+ */
    
    $i = 0;
    while ($i < sizeof($items)) {
		$item = $items[$i];
		/* ------ CDEF ------ */
		/* make cdef string here; a note about CDEF's in cacti. A CDEF is neither unique to a 
		data source of global cdef, but is unique when those two variables combine. */
		$cdef_graph_defs = "";
		
		if (($item[CDEFID] != "0") && (isset($cdef_cache[$item[CDEFID]][$item[ID]]) == false)) {
		    /* pull out what kind of cdef type this is */
		    $cdef_type = db_fetch_cell("select Type from rrd_ds_cdef where id=$item[CDEFID]");
		    
		    /* get all of the items for this cdef */
		    $cdef_items = db_fetch_assoc("select case
				when ci.type=\"CDEF Function\" then cf.name
				when ci.type=\"Data Source\" then ds.name
				when ci.type=\"Custom Entry\" then ci.custom
				end 'CDEF',
				ci.Type,ci.DSID,ci.CurrentDS
				from rrd_ds_cdef_item ci left join def_cdef cf on cf.id=ci.cdeffunctionid left 
				join rrd_ds ds on ds.id=ci.dsid
				where ci.cdefid=$item[CDEFID]
				order by ci.sequence");
		    
		    /* CF rules: if we are using a CF because it's defined in the AREA, STACK, LINE[1-3] then
		     it is ok to use it elsewhere on the graph. But it is not ok to use a CF DEF because
		     its used in a GPRINT; so check that here */
		    if (isset($cf_ds_cache[$item[ID]][$item[ConsolidationFunction]]) == true) {
				$cf_id = $item[ConsolidationFunction];
		    }else{
				$cf_id = 1; /* CF: AVERAGE */
		    }
		    
		    /* make the initial "virtual" cdef name: 'cdef' + md5(dsid) */
		    $cdef_graph_defs .= "CDEF:cdef" . CreateGraphDefName("$i") . "=";
		    $i_cdef = 0;
		    /* form the cdef string by looping through each item. Additionally MD5 hash each
		     data source that we come across so this works right */
		    switch ($cdef_type) {
		     case "1": /* normal */
				if (sizeof($cdef_items) > 0) {
				    foreach ($cdef_items as $cdef_item) {
						if ($cdef_item[Type] == "Data Source") {
						    if ($cdef_item[CurrentDS] == "on") {
								$cdef_current_item = CreateGraphDefName($cf_ds_cache[$item[ID]][$cf_id]);
						    }else{
								$cdef_current_item = CreateGraphDefName($cf_ds_cache[$cdef_item[DSID]][$cf_id]);
						    }
						}else{
						    $cdef_current_item = $cdef_item[CDEF];
						}
						
						if ($i_cdef == 0) { $delimeter = ""; }else{ $delimeter = ","; }
						$cdef_graph_defs .= "$delimeter$cdef_current_item";
						
						$i_cdef++;
				    }
				}
				break;
		     case "2": /* total */
				/* for this special case, we are going to loop through all of the DEF's on this
				graph, and make a cdef string total each of these items */
				$first = true; $o = 0; /* "o" is used to count how many items this cdef has,
				"$i_cdef" will not work because we may skip some items */
				
				/* only add "real" graph items: AREA, STACK, LINE[1-3] */
				if (sizeof($cdef_items) > 0) {
				    foreach ($cdef_items as $cdef_item) {
						if ($cdef_item[ID] != "") {
						    $cdef_current_item = CreateGraphDefName($cf_ds_cache[$cdef_item[ID]][$cf_id]);
						    
						    if ($first == true) { $delimeter = ""; }else{ $delimeter = ","; }
						    $cdef_graph_defs .= "$delimeter$cdef_current_item";
						    $first = false; 
						    $o++;
						}
						
						$i_cdef++;
				    }
				}
				
				/* create the ending ",+"'s ($o-1) times */
				$cdef_graph_defs .= str_pad("", strlen(",+")*($o-1), ",+");
				
				break;
		     case "3": /* total staggered data sources */
				/* for this special case, we are going to loop through all of the DEF's on this
				graph, and make a cdef string total for the odd numbered items */
				$first = true; $o = 0; /* "o" is used to count how many items this cdef has,
				"$i_cdef" will not work because we may skip some items */
				
				/* parse the value string: start=<position>&skip=<count> */
				parse_str($item[Value]);
				
				/* only add "real" graph items: AREA, STACK, LINE[1-3] */
				$skipcount = 0; $begin = 0; $i_cdef = 0;
				if (sizeof($cdef_items) > 0) {
				    foreach ($cdef_items as $cdef_item) {
						if ($cdef_item[ID] != "") {
						    $begin++;
						    $skipcount++;
						    if (($begin == $start) || ($begin > $start && $skipcount == $skip)) {
								$cdef_current_item = CreateGraphDefName($cf_ds_cache[$cdef_item[ID]][$cf_id]);
								
								if ($first == true) { $delimeter = ""; }else{ $delimeter = ","; }
								
								$cdef_graph_defs .= "$delimeter$cdef_current_item";
								$first = false; $o++; $skipcount = 0;
						    }
						}
						
						$i_cdef++;
				    }
				}
				
				/* create the ending ",+"'s ($o-1) times */
				$cdef_graph_defs .= str_pad("", strlen(",+")*($o-1), ",+");
				
				break;
		     case "4": /* average all data sources */
				/* for this special case, we are going to loop through all of the DEF's on this
				graph, and make a cdef string average of these items.*/
				$first = true; $o = 0; /* "o" is used to count how many items this cdef has,
				"$i_cdef" will not work because we may skip some items */
				
				/* only add "real" graph items: AREA, STACK, LINE[1-3] */
				$i_cdef = 0;
				if (sizeof($cdef_items) > 0) {
				    foreach ($cdef_items as $cdef_item) {
					if ($cdef_item[ID] != "") {
					    $cdef_current_item = CreateGraphDefName($cf_ds_cache[$cdef_item[ID]][$cf_id]);
					    
					    if ($first == true) {
							$delimeter = "";
					    }else{
							$delimeter = ",";
					    }
					    
					    $cdef_graph_defs .= "$delimeter$cdef_current_item";
					    $first = false; $o++;
					}
					
					$i_cdef++;
				    }
				}
				
				/* create the ending ",+"'s ($o-1) times */
				$cdef_graph_defs .= str_pad("", strlen(",+")*($o-1), ",+");
				$cdef_graph_defs .= "," . $o . ",/";
				
				break;
		     case "5": /* average staggered data sources */
				/* for this special case, we are going to loop through all of the DEF's on this
				graph, and make a cdef string average for the staggered data sources */
				$first = true; $o = 0; /* "o" is used to count how many items this cdef has,
				"$i_cdef" will not work because we may skip some items */
				
				/* parse the value string: start=<position>&skip=<count> */
				parse_str($item[Value]);
				
				/* only add "real" graph items: AREA, STACK, LINE[1-3] */
				$skipcount = 0; $begin = 0; $i_cdef = 0;
				if (sizeof($cdef_items) > 0) {
				    foreach ($cdef_items as $cdef_item) {
						if ($cdef_item[ID] != "") {
						    $begin++; $skipcount++;
						    if (($begin == $start) || ($begin > $start && $skipcount == $skip)) {
								$cdef_current_item = CreateGraphDefName($cf_ds_cache[$cdef_item[ID]][$cf_id]);
								
								if ($first == true) { $delimeter = ""; }else{ $delimeter = ","; }
								$cdef_graph_defs .= "$delimeter$cdef_current_item";
								$first = false; $o++; $skipcount = 0;
						    }
					    }
						
						$i_cdef++;
				    }
				}
				
				/* create the ending ",+"'s ($o-1) times */
				$cdef_graph_defs .= str_pad("", strlen(",+")*($o-1), ",+");
				$cdef_graph_defs .= "," . $o . ",/";
				
				break;
		    }
		    
		    $cdef_graph_defs .= " \\\n";
		    
		    /* the CDEF cache is so we do not create duplicate CDEF's on a graph */
		    $cdef_cache[$item[CDEFID]][$item[ID]] = "$i";
		}
		
		/* add the cdef string to the end of the def string */
		$graph_defs .= $cdef_graph_defs;
		
		/* if we are not displaying a legend there is no point in us even processing the auto padding,
		 text format stuff. */
		if ($graph_data_array["graph_nolegend"] != true) {
		    if ($graph[AutoPadding] == "on") {
				$item_dsid = $item[ID];
				
				/* we are basing how much to pad on area and stack text format, 
				 not gprint. but of course the padding has to be displayed in gprint,
				 how fun! */
				
				$pad_number = ($greatest_text_format - $text_format_lengths[$item_dsid]);
				//LogData("MAX: $greatest_text_format, CURR: $text_format_lengths[$item_dsid], DSID: $item_dsid");
				$text_padding = str_pad("", $pad_number);
				
				/* two GPRINT's in a row screws up the padding, lets not do that */
				if (($item[TName] == "GPRINT") && ($last_graph_type == "GPRINT")) {
				    $text_padding = "";
				}
				
				$last_graph_type = $item[TName];
		    }
		}
		
		/* we put this in a variable so it can be manipulated before mainly used
		 if we want to skip it, like below */
		$current_graph_item_type = $item[TName];
		
		/* CF rules: if we are using a CF because it's defined in the AREA, STACK, LINE[1-3] then
		 it is ok to use it elsewhere on the graph. But it is not ok to use a CF DEF because
		 its used in a GPRINT; so check that here */
		if ((isset($cf_ds_cache[$item[ID]][$item[ConsolidationFunction]])) && ($item[TName] != "GPRINT")) {
		    $cf_id = $item[ConsolidationFunction];
		}else{
		    $cf_id = 1; /* CF: AVERAGE */
		}
		
		/* make sure grouping is on, before we make decisions based on the group */
		if ($graph[Grouping] == "on") {
		    /* if this item belongs to a graph group that has a parent that does not exist, do
		     not show the child item. this happens with MAX/MIN items on daily graphs mostly. */
		    if ((isset($graph_group_cache[$item[Parent]]) == false) && ($item[TName] == "GPRINT")) {
				$current_graph_item_type = "SKIP";
		    }
		}
		
		/* use cdef if one if specified */
		if ($item[CDEFID] == "0") {
		    $data_source_name = CreateGraphDefName($cf_ds_cache[$item[ID]][$cf_id]);
		}else{
		    $data_source_name = "cdef" . CreateGraphDefName($cdef_cache[$item[CDEFID]][$item[ID]]);
		}
		
		/* +-------------------------------------------------------------------+
		 |                           GRAPH ITEMS                             |
		 +-------------------------------------------------------------------+ */
		
		/* this switch statement is basically used to grab all of the graph data above and
		print it out in an rrdtool-friendly fashion, not too much calculation done here. */
		
		switch ($current_graph_item_type) {
			case 'AREA':
			    $text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
			    $graph_items .= $item[TName] . ":" . 
			      $data_source_name . "#" . 
			      $item[Hex] . ":" . 
			      "\"$text_format[$i]$hardreturn[$i]\" ";
		    	break;
		 	case 'STACK':
			    $text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
			    $graph_items .= $item[TName] . ":" . 
			      $data_source_name . "#" . 
			      $item[Hex] . ":" .
			      "\"$text_format[$i]$hardreturn[$i]\" ";
			    break;
		 	case 'LINE1':
			    $text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
			    $graph_items .= $item[TName] . ":" . 
			      $data_source_name . "#" . 
			      $item[Hex] . ":" . 
			      "\"$text_format[$i]$hardreturn[$i]\" ";
			    break;
		 	case 'LINE2':
			    $text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
			    $graph_items .= $item[TName] . ":" . 
			      $data_source_name . "#" . 
			      $item[Hex] . ":" . 
			      "\"$text_format[$i]$hardreturn[$i]\" ";
			    break;
		 	case 'LINE3':
			    $text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
			    $graph_items .= $item[TName] . ":" . 
			      $data_source_name . "#" . 
			      $item[Hex] . ":" . 
			      "\"$text_format[$i]$hardreturn[$i]\" ";
			    break;
		 	case 'COMMENT':
			    $graph_items .= $item[TName] . ":\"" .
			      "$text_format[$i]$hardreturn[$i]\" ";
			    break;
		 	case 'GPRINT':
				if ($graph_data_array["graph_nolegend"] != true) {
					if ($item[GprintCustom] == "") {
						switch ($item[GprintOpts]) {
							case "1":
							$gprint_text = "%8.2lf %s"; break;
							case "2":
							$gprint_text = "%8.0lf"; break;
						}
					}else{
						$gprint_text = $item[GprintCustom];
					}
					
					$text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
					$graph_items .= $item[TName] . ":" .
						$data_source_name . ":" . $item[CName] .
						":\"$text_padding$text_format[$i]$gprint_text$hardreturn[$i]\" ";
				}
			    break;
		 	case 'HRULE':
			    $text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
			    
			    if ($graph_data_array["graph_nolegend"] == true) {
					$value_format[$i] = "0";
			    }else{
					$value_format[$i] = ereg_replace (":", "\:" ,$value_format[$i]); /* escape colons */
			    }
			    
			    $graph_items .= $item[TName] . ":" .
			      $value_format[$i] . "#" . $item[Hex] . ":\"" . 
			      "$text_format[$i]$hardreturn[$i]\" ";
			    break;
		 	case 'VRULE':
			    $text_format[$i] = ereg_replace (":", "\:" ,$text_format[$i]); /* escape colons */
			    
			    $value_array = explode(":", $item[Value]);
			    $value = date("U", mktime($value_array[0],$value_array[1],0));
			    
			    $graph_items .= $item[TName] . ":" .
			      $value . "#" . $item[Hex] . ":\"" . 
			      "$text_format[$i]$hardreturn[$i]\" ";
			    break;
		}
		
		$i++;
		
		if ($i < sizeof($items)) {
		    $graph_items .= "\\\n";
		}
    }
    
    /* either print out the source or pass the source onto rrdtool to get us a nice PNG */
    if ($graph_data_array["print_source"] == "true") {
		print "<PRE>" . read_config_option("path_rrdtool") . " graph $graph_opts$graph_defs$graph_items</PRE>";
    }else{
		if ($graph_data_array["export"] == true) {
	    	rrdtool_execute("graph $graph_opts$graph_defs$graph_items", false, "0");
	    	return 0;
		}else{
	    	if (read_config_option("log_graph") == "on") { $log_data = true; }
	    	//if ($graph_data_array["output_flag"] == "") { $graph_data_array["output_flag"] = 1; }
	    	return rrdtool_execute("graph $graph_opts$graph_defs$graph_items",$log_data,$graph_data_array["output_flag"]);
		}
    }
}


?>
