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

$struct_graph = array("image_format_id", "title", "height", "width", "upper_limit",
		      "lower_limit", "vertical_label", "auto_scale", "auto_scale_opts",
		      "auto_scale_log", "auto_scale_rigid", "auto_padding", "base_value",
		      "grouping", "export", "unit_value", "unit_exponent_value");


function push_out_graph($graph_template_graph_id) {
	global $struct_graph;
	
	/* get information about this graph template */
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where id=$graph_template_graph_id");
	
	/* must be a graph template */
	if ($graph_template_graph[graph_template_id] == 0) { return 0; }
	
	foreach ($struct_graph as $struct) {
		$value_type = "t_$struct";
		
		if ($graph_template_graph[$value_type] == "") {
			db_execute("update graph_templates_graph set $struct='$graph_template_graph[$struct]' where graph_template_id=$graph_template_graph[id]"); 
		}
	}
}

function push_out_graph_item($graph_template_item_id) {
	
	
}

function change_graph_template($local_graph_id, $graph_template_id, $_graph_template_id) {
	/* always update tables to new graph template (or no graph template) */
	db_execute("update graph_templates_graph set graph_template_id=$graph_template_id where local_graph_id=$local_graph_id");
	db_execute("update graph_templates_item set graph_template_id=$graph_template_id where local_graph_id=$local_graph_id");
	db_execute("update graph_local set graph_template_id=$graph_template_id where id=$local_graph_id");
	
	/* make sure the 'local_graph_template_graph_id' column is set */
	$local_graph_template_graph_id = db_fetch_cell("select id from graph_templates_graph where graph_template_id=$graph_template_id and graph_template_id=id");
	//print "select local_graph_template_graph_id from graph_templates_graph where graph_template_id=$graph_template_id<br>";
	if ($local_graph_template_graph_id == "") { $local_graph_template_graph_id = 0; }
	db_execute("update graph_templates_graph set local_graph_template_graph_id=$local_graph_template_graph_id where local_graph_id=$local_graph_id");
	//print "update graph_templates_graph set local_graph_template_graph_id=$local_graph_template_graph_id where local_graph_id=$local_graph_id<br>";
	/* if the user turned off the template for this graph; there is nothing more
	to do here */
	if ($graph_template_id == "0") { return 0; }
	
	/* we are going from no template -> a template */
	if ($_graph_template_id == "0") {
		$graph_items_list = db_fetch_assoc("select * from graph_templates_item where local_graph_id=$local_graph_id");
		$template_items_list = db_fetch_assoc("select * from graph_templates_item where local_graph_id=0 and graph_template_id=$graph_template_id");
		
		if (sizeof($graph_items_list) > 0) {
			/* this graph already has "child" items */
		}else{
			/* this graph does NOT have "child" items; loop through each item in the template
			and write it exactly to each item */
			if (sizeof($template_items_list) > 0) {
			foreach ($template_items_list as $template_item) {
				$save["id"] = 0;
				$save["local_graph_template_item_id"] = $template_item["id"];
				$save["local_graph_id"] = $local_graph_id;
				$save["graph_template_id"] = $template_item["graph_template_id"];
				$save["task_item_id"] = $template_item["task_item_id"];
				$save["color_id"] = $template_item["color_id"];
				$save["graph_type_id"] = $template_item["graph_type_id"];
				$save["cdef_id"] = $template_item["cdef_id"];
				$save["consolidation_function_id"] = $template_item["consolidation_function_id"];
				$save["text_format"] = $template_item["text_format"];
				$save["value"] = $template_item["value"];
				$save["hard_return"] = $template_item["hard_return"];
				$save["gprint_opts"] = $template_item["gprint_opts"];
				$save["gprint_custom"] = $template_item["gprint_custom"];
				$save["custom"] = $template_item["custom"];
				$save["sequence"] = $template_item["sequence"];
				$save["sequence_parent"] = $template_item["sequence_parent"];
				$save["parent"] = $template_item["parent"];
				
				sql_save($save, "graph_templates_item");
			}
			}
		}
		
		/* make sure to "correct" parent column for graph items */
		unset($graph_items_list);
		$graph_items_list = db_fetch_assoc("select
			id,
			parent
			from graph_templates_item
			where local_graph_template_item_id=parent
			and local_graph_id=$local_graph_id");
		
		if (sizeof($graph_items_list) > 0) {
		foreach ($graph_items_list as $graph_item) {
			db_execute("update graph_templates_item set parent=$graph_item[id] where parent=$graph_item[parent] and local_graph_id=$local_graph_id");
		}
		}
	}
	
	/* "merge" graph stuff */
	$graph_list = db_fetch_row("select * from graph_templates_graph where local_graph_id=$local_graph_id");
	$template_graph_list = db_fetch_row("select * from graph_templates_graph where local_graph_id=0 and graph_template_id=$graph_template_id");
	
	unset($save);
	
	$save["id"] = $graph_list["id"];
	$save["local_graph_template_graph_id"] = $template_graph_list["id"];
	$save["local_graph_id"] = $local_graph_id;
	$save["graph_template_id"] = $graph_template_id;
	$save["order_key"] = $graph_list["order_key"];
	
	if ($template_graph_list[t_image_format_id] == "on") { $save["image_format_id"] = $graph_list["image_format_id"]; }else{ $save["image_format_id"] = $template_graph_list["image_format_id"]; }
	if ($template_graph_list[t_title] == "on") { $save["title"] = $graph_list["title"]; }else{ $save["title"] = $template_graph_list["title"]; }
	if ($template_graph_list[t_height] == "on") { $save["height"] = $graph_list["height"]; }else{ $save["height"] = $template_graph_list["height"]; }
	if ($template_graph_list[t_width] == "on") { $save["width"] = $graph_list["width"]; }else{ $save["width"] = $template_graph_list["width"]; }
	if ($template_graph_list[t_upper_limit] == "on") { $save["upper_limit"] = $graph_list["upper_limit"]; }else{ $save["upper_limit"] = $template_graph_list["upper_limit"]; }
	if ($template_graph_list[t_lower_limit] == "on") { $save["lower_limit"] = $graph_list["lower_limit"]; }else{ $save["lower_limit"] = $template_graph_list["lower_limit"]; }
	if ($template_graph_list[t_vertical_label] == "on") { $save["vertical_label"] = $graph_list["vertical_label"]; }else{ $save["vertical_label"] = $template_graph_list["vertical_label"]; }
	if ($template_graph_list[t_auto_scale] == "on") { $save["auto_scale"] = $graph_list["auto_scale"]; }else{ $save["auto_scale"] = $template_graph_list["auto_scale"]; }
	if ($template_graph_list[t_auto_scale_opts] == "on") { $save["auto_scale_opts"] = $graph_list["auto_scale_opts"]; }else{ $save["auto_scale_opts"] = $template_graph_list["auto_scale_opts"]; }
	if ($template_graph_list[t_auto_scale_log] == "on") { $save["auto_scale_log"] = $graph_list["auto_scale_log"]; }else{ $save["auto_scale_log"] = $template_graph_list["auto_scale_log"]; }
	if ($template_graph_list[t_auto_scale_rigid] == "on") { $save["auto_scale_rigid"] = $graph_list["auto_scale_rigid"]; }else{ $save["auto_scale_rigid"] = $template_graph_list["auto_scale_rigid"]; }
	if ($template_graph_list[t_auto_padding] == "on") { $save["auto_padding"] = $graph_list["auto_padding"]; }else{ $save["auto_padding"] = $template_graph_list["auto_padding"]; }
	if ($template_graph_list[t_base_value] == "on") { $save["base_value"] = $graph_list["base_value"]; }else{ $save["base_value"] = $template_graph_list["base_value"]; }
	if ($template_graph_list[t_grouping] == "on") { $save["grouping"] = $graph_list["grouping"]; }else{ $save["grouping"] = $template_graph_list["grouping"]; }
	if ($template_graph_list[t_export] == "on") { $save["export"] = $graph_list["export"]; }else{ $save["export"] = $template_graph_list["export"]; }
	if ($template_graph_list[t_unit_value] == "on") { $save["unit_value"] = $graph_list["unit_value"]; }else{ $save["unit_value"] = $template_graph_list["unit_value"]; }
	if ($template_graph_list[t_unit_exponent_value] == "on") { $save["unit_exponent_value"] = $graph_list["unit_exponent_value"]; }else{ $save["unit_exponent_value"] = $template_graph_list["unit_exponent_value"]; }
	
	sql_save($save, "graph_templates_graph");
}

function update_graph_item_groups($id, $_id, $_graph_type_id, $_parent) {
	include_once ("include/functions.php");
	
	$new_id = $id;
	$skip_end_save = false;
	
	$graph_type_name = db_fetch_cell("select def_graph_type.name from def_graph_type,graph_templates_item where def_graph_type.id=graph_templates_item.graph_type_id and graph_templates_item.id=$id");
	//print "select def_graph_type.name from def_graph_type,graph_templates_item where def_graph_type.id=graph_templates_item.graph_type_id and graph_templates_item.id=$id<br>";
	$graph_items = db_fetch_row("select local_graph_id,graph_template_id,task_item_id,parent from graph_templates_item where id=$new_id");
	//print "select local_graph_id,graph_template_id,task_item_id,parent from graph_templates_item where id=$new_id<br>";
	
	/* a graph template uses the 'graph_template_id' field as a unique separator; a single graph uses just 
	the 'local_graph_id' as the unique separator */
	if ($graph_items[graph_template_id] == "0") {
		$local_group = "local_graph_id";
		$local_group_value = $graph_items[$local_group];
	}else{
		$local_group = "graph_template_id";
		$local_group_value = $graph_items[$local_group];
	}
	
	if ($_graph_type_id != "0") {
		/* get the name of the LAST graph item type */
		$_graph_type_name = db_fetch_cell("select name from def_graph_type where id=$_graph_type_id");
	}
	
	//print "Current Type is a: $graph_type_name, Old Type was a: $_graph_type_name<br>";
	//print "Current ID is: $id, Old ID was: $_id<br>";
	//print "Current Parent is: $graph_items[parent], Old Parent was: $_parent<br>";
	//print "Current Group is: $local_group, its value is: $local_group_value<br>";
	
	if ($graph_type_name != "GPRINT") {
		/* if item is not a GPRINT and the user changed the PARENT field... please ignore */
		if (($_graph_type_name != "GPRINT") && ($graph_items[parent] != $_parent) && ($_id != 0)) {
			print "EXCEPTION!";
			db_execute("update graph_templates_item set parent=$_parent where id=$new_id");
			return 0;
		}
		
		/* graph type changed; force a new sequence id */
		if ($_graph_type_name == "GPRINT") { $_id = 0; }
		
		if ($_id == 0) { db_execute("update graph_templates_item set sequence=0 where id=$new_id"); } /* reset sequence so it doesn't get taken into account */
		$max_sequence = GetSequence($_id, "sequence_parent", "graph_templates_item", $local_group, $local_group_value);
		
		db_execute("update graph_templates_item set sequence_parent=$max_sequence where id=$new_id");
		db_execute("update graph_templates_item set parent=$new_id where id=$new_id");
		//print "update graph_templates_item set sequence_parent=$max_sequence where id=$new_id<br>";
		//print "update graph_templates_item set parent=$new_id where id=$new_id<br>";
		$skip_end_save == true;
		
		/* a parent will always have a sequence of '1' */
		//print "update graph_templates_item set sequence=1 where id=$new_id<br>";
		db_execute("update graph_templates_item set sequence=1 where id=$new_id");
		
		
		/* this is a save on a parent item: if this parent has children with all of the same
		data source, then update the data source change in the parent to the children as 
		well. this saves the user some clicks */
		$sql_id = db_fetch_assoc("select task_item_id from graph_templates_item where parent=$new_id and parent!=id group by task_item_id");
		
		if (sizeof($sql_id) == "1") {
			/* there is only one data source in use for this group */
			db_execute("update graph_templates_item set task_item_id=$graph_items[task_item_id] where parent=$new_id");
			//print "update graph_templates_item set task_item_id=$graph_items[task_item_id] where parent=$new_id<br>";
		}
	}else{
		/* if the group changes, force a new sequence */
		if ($_parent != $graph_items[parent]) { $_id = 0; }
		
		$parent = $graph_items[parent];
		
		if ($_graph_type_name != "GPRINT") {
			/* graph type changed; force a new sequence id */
			$_id = 0;
			
			/* if the old item was a parent and now it is a child (GPRINT), we must find
			a new parent for this child. cacti says... go with the last available parent... */
			$parents = db_fetch_row("select
				graph_templates_item.sequence_parent,
				graph_templates_item.parent 
				from graph_templates_item left join def_graph_type on graph_templates_item.graph_type_id=def_graph_type.id 
				where graph_templates_item.$local_group=$local_group_value
				and (def_graph_type.name = 'AREA' 
				or def_graph_type.name = 'STACK' 
				or def_graph_type.name = 'LINE1' 
				or def_graph_type.name = 'LINE2' 
				or def_graph_type.name = 'LINE3')
				order by graph_templates_item.sequence_parent desc");
			if (sizeof($parents) != "0") { $parent = $parents[parent]; }
			
			/* also... what if that old parent had children... orfans need homes as well */
			$items_list = db_fetch_assoc("select id from graph_templates_item where parent=$new_id");
			//print "select id from graph_templates_item where parent=$new_id<br>";
			
			if (sizeof($items_list) > 0) {
			$skip_end_save = true; /* already doing that here */
			foreach ($items_list as $items) {
				$sequence_parent = $parents[sequence_parent];
				db_execute("update graph_templates_item set sequence=0 where id=$items_list[id]"); /* reset sequence so it doesn't get taken into account */
				$max_sequence = GetSequence(0, "sequence", "graph_templates_item", $local_group, "$local_group_value and parent=$parent");
				
				db_execute("update graph_templates_item set sequence=$max_sequence,
					sequence_parent=$sequence_parent,
					parent=$parent
					where id=" . $items[id]);
				//print "update graph_templates_item set sequence=$max_sequence,
				//	sequence_parent=$sequence_parent,
				//	parent=$parent
				//	where id=" . $items[id] . "<br>";
			}
			}
		}
		
		$sequence_parent = db_fetch_cell("select sequence_parent from graph_templates_item where id=$parent");
		
		if ($skip_end_save == false) {
			if ($_id == 0) { db_execute("update graph_templates_item set sequence=0 where id=$new_id"); } /* reset sequence so it doesn't get taken into account */
			$max_sequence = GetSequence($_id, "sequence", "graph_templates_item", $local_group, "$local_group_value and parent=$parent");
			
			db_execute("update graph_templates_item set sequence=$max_sequence where id=$new_id");
			db_execute("update graph_templates_item set sequence_parent=$sequence_parent where id=$new_id");
			//print "update graph_templates_item set sequence=$max_sequence where id=$new_id<br>";
			//print "update graph_templates_item set sequence_parent=$sequence_parent where id=$new_id<br>";
		}
	}
	
}

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
