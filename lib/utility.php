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

function DuplicateGraph($graph_id) {
    #    global $cnn_id;
    #include_once("include/database.php");
    $original = db_fetch_row("select * from rrd_graph where id=$graph_id");
    $name = "$original[Title] (Copy)";
    
    /* duplicate graph defs */
    $sql = "insert into rrd_graph (id,imageformatid,title,height,width,upperlimit,
				       lowerlimit,verticallabel,autoscale,autopadding,autoscaleopts,rigid,basevalue,grouping,export) 
      values (0,
	      $original[ImageFormatID],\"$name\""
	       . ",$original[Height]"
	       . ", $original[Width]"
	       . ", $original[UpperLimit]" 
	       . ", $original[LowerLimit]"
	       . ",\"$original[VerticalLabel]\""
	       . ",\"$original[AutoScale]\""
	       . ",\"$original[AutoPadding]\""
	       . ",\"$original[AutoScaleOpts]\""
	       . ",\"$original[Rigid]\""
	       . ",\"$original[BaseValue]\""
	       . ",\"$original[Grouping]\""
	       . ",\"$original[Export]\")";
#    print "$sql<BR>\n";
    db_execute($sql);
    
    $graph_grouping = $original[Grouping];
    
    /* duplicate graph items */
    $id = db_fetch_row("select LAST_INSERT_ID() as ID");
    $last_id = $id[ID];
    
    $items = db_fetch_assoc("select * from rrd_graph_item where graphid=$graph_id");
    
    if (sizeof($items) > 0) {
	foreach ($items as $item) {
	    db_execute("insert into rrd_graph_item (id,dsid,colorid,textformat,value,sequence,
						    graphid,graphtypeid,consolidationfunction,hardreturn,cdefid,sequenceparent,parent) 
	      values (0" 
		       . ",\"$item[DSID]\""
		       . ",\"$item[ColorID]\""
		       . ",\"$item[TextFormat]\""
		       . ",\"$item[Value]\""
		       . ",\"$item[Sequence]\""
		       . ",\"" . $last_id . "\""
		       . ",\"$item[GraphTypeID]\""
		       . ",\"$item[ConsolidationFunction]\""
		       . ",\"$item[HardReturn]\""
		       . ",\"$item[CDEFID]\""
		       . ",\"$item[SequenceParent]\""
		       . ",\"$item[Parent]\")");
	}
	
	/* if graph grouping is turned on; we are going to "reset" it by undoing and redoing
	 the graph grouping. This will force each item to get valid parent items instead of
	 pointing to the old graph items */
	if ($graph_grouping == "on") {
	    ungroup_graph_items($last_id);
	    group_graph_items($last_id);
	}
    }
}

function DuplicateDataSource($datasource_id) {
    
    $ds = db_fetch_row("select * from rrd_ds where id=$datasource_id");
    
    $name = "$ds[Name]_copy";
    /* duplicate data source defs */
    db_execute("insert into rrd_ds (id,name,datasourcetypeid,heartbeat,
				    minvalue,maxvalue,srcid,active,dsname,dspath,step) values (0"
	       . ",\"$name\""
	       . ",\"$ds[DatasourceTypeID]\""
	       . ",\"$ds[Heartbeat]\""
	       . ",\"$ds[MinValue]\""
	       . ",\"$ds[MaxValue]\""
	       . ",\"$ds[SrcID]\""
	       . ",\"$ds[Active]\""
	       . ",\"$ds[DSName]\""
	       . ",\"\""
	       . ",\"$ds[Step]\""
	       . ")");
    
    /* duplicate data source data */
    $last_id = db_fetch_cell("select LAST_INSERT_ID() as ID","ID");
    
    $ds_list = db_fetch_assoc("select * from src_data where dsid=$datasource_id");
    
    if (sizeof($ds_list) > 0) {
	foreach ($ds_list as $ds) {
	    db_execute("insert into src_data (id,fieldid,dsid,value) values (0" 
		       . ",\"$ds[FieldID]\""
		       . ",\"$last_id\""
		       . ",\"$ds[Value]\""
		       . ")");
	}
    }
    
    /* duplicate data source -> rra link data */
    $link_ds_list = db_fetch_assoc("select * from lnk_ds_rra where dsid=$datasource_id");
    
    if (sizeof($link_ds_list) > 0) {
	foreach ($link_ds_list as $link_ds) {
	    db_execute("insert into lnk_ds_rra (dsid,rraid) values ($last_id,\"$link_ds[RRAID]\")");
	}
	
	return $last_id;
    }
}

function group_graph_items($graph_id) {
    
    $graph_items = db_fetch_assoc("select 
				    i.ConsolidationFunction,i.Sequence,i.ID,i.DSID,
				    t.Name 
				    from rrd_graph_item i left join 
				    def_graph_type t on 
				    i.graphtypeid=t.id 
				    where i.graphid=$graph_id 
				    order by i.sequenceparent, i.sequence");
    
    if (sizeof($graph_items) > 0) {
	foreach ($graph_items as $item) {
	    $child_counter[$item[DSID]]++;
	    
	    if (($item[Name] == "AREA") || ($item[Name] == "STACK") || ($item[Name] == "LINE1") || ($item[Name] == "LINE2") || ($item[Name] == "LINE3")) {
		if (isset($has_been_used[$item[DSID]][$item[ConsolidationFunction]]) == false) {
		    $parent_counter++;
		}
		
		$current_parent_id = $item[ID];
		$current_sequence = 0;
		
		$has_been_used[$item[DSID]][$item[ConsolidationFunction]] = true;
	    } else {
		$current_sequence = $child_counter[$item[DSID]];
	    }
	    
	    db_execute("update rrd_graph_item set sequence=$current_sequence
			 , sequenceparent=$parent_counter, parent=$current_parent_id
			 where id=$item[ID]");
	}
    }
}

function ungroup_graph_items($graph_id) {
    $data = db_fetch_assoc("select ID from rrd_graph_item where graphid=$graph_id
			    order by sequenceparent,sequence");
    $rows = sizeof($data);
    
    while ($i < $rows) {
	db_execute("update rrd_graph_item set sequence=" . ($i+1) . " where id=".$data[$i][ID]);
	$i++;
    }
    
    db_execute("update rrd_graph_item set sequenceparent=0 where graphid=$graph_id");
    db_execute("update rrd_graph_item set parent=0 where graphid=$graph_id");
    
    $i++;
}

function CreateGraphDataFromSNMPData($graph_parameters) {
    $int = db_fetch_row("select * from snmp_hosts_interfaces where id=" . $graph_parameters["snmp_interface_id"]);
    
    /* ---- Data Source ---- */
    
    /* defaults */
    $ds_datasourcetype = "COUNTER";
    $ds_heartbeat = "600";
    $ds_minvalue = "0";
    $ds_maxvalue = $int[Speed];
    $ds_srctype = "snmp_net";
    $snmp_interface_index = $int[InterfaceNumber];
    $snmp_interface_description = $int[Description];
    
    /* host info */
    $snmp_host = db_fetch_row("select * from snmp_hosts where id=$int[HostID]");
    $snmp_host_hostname = $snmp_host[Hostname];
    $snmp_host_community = $snmp_host[Community];
    
    $ds_datasourcetype = db_fetch_cell("select ID from def_ds where name=\"$ds_datasourcetype\"","ID");
    
    /* get the src_id given the src_type */
    $tmp = db_fetch_row("select ID from src where type=\"$ds_srctype\"");
    
    if (sizeof($tmp) > 0) {
	$ds_srcid = $tmp[ID];
    }
    
    $o = 0;
    while ($o <= 1) {
	switch ($o) {
	 case 0:
	    $ds_direction = "in";
	    break;
	 case 1:
	    $ds_direction = "out";
	    break;
	}
	
	/* set a path for this new data source [RRDPATH/FullDSName_[in|out].rrd] */
	$data_source_path = "<path_rra>/" . strtolower(CleanUpName($graph_parameters["data_source_name"])) . "_" . "$ds_direction.rrd";
	
	$sql_id = db_execute("insert into rrd_ds (name,datasourcetypeid,heartbeat,minvalue,
						   maxvalue,srcid,active,dsname,dspath) values (\"" . $graph_parameters["data_source_name"] . "_" . "$ds_direction\",
																		      $ds_datasourcetype,$ds_heartbeat,$ds_minvalue,$ds_maxvalue,$ds_srcid,\"on\",\"\",\"$data_source_path\")"
			      ,$cnn_id);
	
	/* get newly saved id */
	$datasource_id[$o] = db_fetch_cell("select LAST_INSERT_ID() ID","ID"); /* store both dsid's */
	
	/* this will do any of the cleanup that is required on the data source name to make
	 sure rrdtool is ok with it. */
	SyncDataSourceName($datasource_id[$o], "", $data_source_path);
	
	/* save rra data: use all rra's */
	$rra_list = db_fetch_assoc("select id from rrd_rra");
	if (sizeof($rra_list) > 0) {
	    foreach ($rra_list as $rra) {
		db_execute("insert into lnk_ds_rra (dsid,rraid) values ($datasource_id[$o],$rra[ID])");
	    }
	}
	
	/* now we have to write the acual data for these ds's to start working, this will
	 only work correctly if the user did not tamper with the data names of the
	 "Gather SNMP Network Data" data input source! */
	$fields = db_fetch_assoc("select ID,DataName from src_fields where srcid=$ds_srcid and inputoutput=\"in\"");
	if (sizeof($fields) > 0) {
	    foreach ($fields as $field) {
		$ds_input_value = "";
		/* get values for the input fields that need them */
		switch ($field[DataName]) {
		 case 'inout':
		    $ds_input_value = $ds_direction;
		    break;
		 case 'ip':
		    $ds_input_value = $snmp_host_hostname;
		    break;
		 case 'community':
		    $ds_input_value = $snmp_host_community;
		    break;
		 case 'ifnum':
		    $ds_input_value = $snmp_interface_index;
		    break;
		}
		
		db_execute("insert into src_data (fieldid,dsid,value) values ($field[ID],$datasource_id[$o],\"$ds_input_value\")");
	    }
	}
	$o++;
    }
    /* ---- Graph Data ---- */
	
    /* Graph Title */
    $graph_title = BuildGraphTitleFromSNMP($graph_parameters);
    
    if ($graph_title == "") {
	$graph_title = "Traffic Analysis for $snmp_interface_description";
    }
    
    /* defaults */
    $graph_image_format = "PNG";
    $graph_height = "120";
    $graph_width = "500";
    $graph_vertical_label = "Bytes Per Second";
    $graph_color_in = "00CF00";
    $graph_color_out = "002A97";
    
    $graph_color_in  = db_fetch_cell("select ID from def_colors where hex=\"$graph_color_in\"", "ID");
    $graph_color_out = db_fetch_cell("select ID from def_colors where hex=\"$graph_color_out\"","ID");
    
    $graph_image_format = db_fetch_cell("select ID from def_image_type where name=\"$graph_image_format\"","ID");
    
    db_execute("insert into rrd_graph (imageformatid,title,height,width,upperlimit,
				       lowerlimit,verticallabel,autoscale,autopadding,autoscaleopts,rigid,basevalue,grouping,export) 
      values ($graph_image_format,\"$graph_title\",$graph_height,$graph_width,0,0,
	      \"$graph_vertical_label\",\"on\",\"on\",2,\"on\",1000,\"on\",\"on\")");
    
    /* get newly saved id */
    $graph_id = db_fetch_cell("select LAST_INSERT_ID() ID","ID");
    
    /* this would be a great place to put some code for graph templates, however it is not
     written yet, so its all going to be manual for now */
    
    $graph_hard_return_array[0] = "";
    $graph_hard_return_array[1] = "";
    $graph_hard_return_array[2] = "";
    
    $graph_color_array[1] = "0";
    $graph_color_array[2] = "0";
    $graph_color_array[3] = "0";
    
    $graph_text_format_array[1] = "Current:";
    $graph_text_format_array[2] = "Average:";
    $graph_text_format_array[3] = "Maximum:";
    
    $graph_graph_type_array[1] = "GPRINT";
    $graph_graph_type_array[2] = "GPRINT";
    $graph_graph_type_array[3] = "GPRINT";
    
    $graph_cf_function_array[0] = "AVERAGE";
    $graph_cf_function_array[1] = "LAST";
    $graph_cf_function_array[2] = "AVERAGE";
    $graph_cf_function_array[3] = "MAX";
    
    $o = 0;
    while ($o <= 1) {
	switch ($o) {
	 case 0:
	    $graph_text_format_array[0] = "Inbound";
	    $graph_graph_type_array[0] = "AREA";
	    $graph_color_array[0] = $graph_color_in;
	    $graph_hard_return_array[3] = "on";
	    break;
	 case 1:
	    $graph_text_format_array[0] = "Outbound";
	    $graph_graph_type_array[0] = "LINE1";
	    $graph_color_array[0] = $graph_color_out;
	    $graph_hard_return_array[3] = "";
	    break;
	}
	
	$s = 0;
	while ($s < count($graph_text_format_array)) {
	    $graph_type = db_fetch_cell("select ID from def_graph_type where name=\"$graph_graph_type_array[$s]\"","ID");
	    
	    $graph_cf_function = db_fetch_cell("select ID from def_cf where name=\"$graph_cf_function_array[$s]\"","ID");
	    
	    db_execute("insert into rrd_graph_item (dsid,colorid,textformat,
		    sequence,graphid,graphtypeid,consolidationfunction,hardreturn) values ($datasource_id[$o],
		   $graph_color_array[$s],\"$graph_text_format_array[$s]\",((($o*4)+$s)+1),$graph_id,$graph_type,$graph_cf_function,
		   \"$graph_hard_return_array[$s]\")");
	    
	    $s++;
	}
	
	$o++;
    }
    
    /* turn on graph grouping */
    group_graph_items($graph_id);
}

?>
