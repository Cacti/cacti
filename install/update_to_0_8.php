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


include_once("../include/functions.php");
include_once("../include/utility_functions.php");

set_time_limit(0);
error_reporting(E_ALL & ~E_NOTICE);

$status_array = array();
$order_key_array = array(); /* for graph trees */

$paths["cacti"] = str_replace("/install", "", dirname(__FILE__));
$paths["rra"] = str_replace("/install", "", dirname(__FILE__)) . "/rra";

function update_database($database_old, $database_username, $database_password) {
	global $database_hostname, $database_default, $paths, $order_key_array;
	
	db_connect_real($database_hostname,$database_username,$database_password,$database_default,"mysql");
	
	db_execute("truncate table $database_default.user_auth");
	db_execute("truncate table $database_default.user_auth_realm");
	db_execute("truncate table $database_default.user_auth_hosts");
	db_execute("truncate table $database_default.user_log");
	
	$_users = db_fetch_assoc("select * from $database_old.auth_users");
	
	if (sizeof($_users) > 0) {
	foreach ($_users as $item) {
		if (db_execute("insert into $database_default.user_auth (id,username,password,must_change_password,show_tree,show_list,
			show_preview,login_opts,graph_policy,full_name) values ('" . $item["ID"] . "','" . $item["Username"] . "',
			'" . $item["Password"] . "','" . $item["MustChangePassword"] . "','" . $item["ShowTree"] . "',
			'" . $item["ShowList"] . "','" . $item["ShowPreview"] . "','" . $item["LoginOpts"] . "',
			'" . $item["GraphPolicy"] . "','" . $item["FullName"] . "')")) {
			
			$status_array{count($status_array)}["user"][1] = $item["Username"];
		}else{
			$status_array{count($status_array)}["user"][0] = $item["Username"];
		}
	}
	}
	
	$_users_acl = db_fetch_assoc("select * from $database_old.auth_acl");
	
	if (sizeof($_users_acl) > 0) {
	foreach ($_users_acl as $item) {
		db_execute("insert into $database_default.user_auth_realm (realm_id,user_id) values ('" . $item["SectionID"] . "',
			'" . $item["UserID"] . "')");
	}
	}
	
	$status_array{count($status_array)}["user_acl"][1] = "all";
	
	$_users_hosts = db_fetch_assoc("select * from $database_old.auth_hosts");
	
	if (sizeof($_users_hosts) > 0) {
	foreach ($_users_hosts as $item) {
		db_execute("insert into $database_default.user_auth_hosts (user_id,hostname,policy) values ('" . $item["UserID"] . "',
			'" . $item["Hostname"] . "','" . $item["Type"] . "')");
	}
	}
	
	$status_array{count($status_array)}["user_host"][1] = "all";
	
	$_users_logs = db_fetch_assoc("select * from $database_old.auth_log");
	
	if (sizeof($_users_logs) > 0) {
	foreach ($_users_logs as $item) {
		db_execute("insert into $database_default.user_log (username,time,result,ip) values ('" . $item["Username"] . "',
			'" . $item["Time"] . "','" . $item["Success"] . "','" . $item["IP"] . "')");
	}
	}
	
	$status_array{count($status_array)}["user_log"][1] = "all";
	
	db_execute("delete from data_input_fields where data_input_id > 2");
	db_execute("delete from data_input where id > 2");
	
	$_src = db_fetch_assoc("select * from $database_old.src where id != 11 and id != 13");
	
	if (sizeof($_src) > 0) {
	foreach ($_src as $item) {
		if (db_execute("insert into $database_default.data_input (id,name,input_string,output_string,type_id) values (0,
			'" . $item["Name"] . "','" . $item["FormatStrIn"] . "','" . $item["FormatStrOut"] . "',
			1)")) {
			$data_input_cache{$item["ID"]} = db_fetch_insert_id();
			
			$status_array{count($status_array)}["data_input"][1] = $item["Name"];
			
			$_src_fields = db_fetch_assoc("select * from $database_old.src_fields where srcid=" . $item["ID"]);
			
			if (sizeof($_src_fields) > 0) {
			foreach ($_src_fields as $item2) {
				if (db_execute("insert into data_input_fields (id,data_input_id,name,data_name,input_output,
					update_rra,sequence,type_code,regexp_match,allow_nulls) values (0,
					'" . $data_input_cache{$item["ID"]} . "','" . $item2["Name"] . "','" . $item2["DataName"] . "',
					'" . $item2["InputOutput"] . "','" . $item2["UpdateRRA"] . "',0,'','','')")) {
					$data_input_field_cache{$item2["ID"]} = db_fetch_insert_id();
					$status_array{count($status_array)}["data_input_field"][1] = $item2["Name"];
				}else{
					$status_array{count($status_array)}["data_input_field"][0] = $item2["Name"];
				}
			}
			}
		}else{
			$status_array{count($status_array)}["data_input"][0] = $item["Name"];
		}
	}
	}
	
	/* snmp data input sources */
	$data_input_cache[13] = 1;
	$data_input_cache[11] = 2;
	
	db_execute("truncate table $database_default.host");
	db_execute("truncate table $database_default.host_snmp_query");
	db_execute("truncate table $database_default.host_snmp_cache");
	
	$_hosts = db_fetch_assoc("select * from $database_old.snmp_hosts");
	
	if (sizeof($_hosts) > 0) {
	foreach ($_hosts as $item) {
		if (db_execute("insert into host (id,host_template_id,description,hostname,management_ip,snmp_community,
			snmp_version,snmp_username,snmp_password) values (0,0,'" . $item["Hostname"] . "',
			'" . $item["Hostname"] . "','" . gethostbyname($item["Hostname"]) . "','" . $item["Community"] . "',
			1,'','')")) {
			$host_id = db_fetch_insert_id();
			$ip_to_host_cache{gethostbyname($item["Hostname"])} = $host_id;
			
			$status_array{count($status_array)}["host"][1] = $item["Hostname"];
			db_execute("insert into host_snmp_query (host_id,snmp_query_id) values ($host_id,1)");
			
			$_snmp_cache = db_fetch_assoc("select * from $database_old.snmp_hosts_interfaces where HostID=" . $item["ID"]);
			
			if (sizeof($_snmp_cache) > 0) {
			foreach ($_snmp_cache as $item2) {
				$snmp_index = $item2["InterfaceNumber"];
				db_execute("insert into host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) values ($host_id,1,'ifDesc','" . $item2["Description"] . "',$snmp_index,'.1.3.6.1.2.1.2.2.1.2.$snmp_index')");
				db_execute("insert into host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) values ($host_id,1,'ifType','" . $item2["Type"] . "',$snmp_index,'.1.3.6.1.2.1.2.2.1.3.$snmp_index')");
				db_execute("insert into host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) values ($host_id,1,'ifSpeed','" . $item2["Speed"] . "',$snmp_index,'.1.3.6.1.2.1.2.2.1.5.$snmp_index')");
				db_execute("insert into host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) values ($host_id,1,'ifIndex','" . $item2["InterfaceNumber"] . "',$snmp_index,'.1.3.6.1.2.1.2.2.1.1.$snmp_index')");
				db_execute("insert into host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) values ($host_id,1,'ifHwAddr','" . $item2["HardwareAddress"] . "',$snmp_index,'.1.3.6.1.2.1.2.2.1.6.$snmp_index')");
				db_execute("insert into host_snmp_cache (host_id,snmp_query_id,field_name,field_value,snmp_index,oid) values ($host_id,1,'ifIP','" . $item2["IPAddress"] . "',$snmp_index,'.1.3.6.1.2.1.4.20.1.2." . $item2["IPAddress"] . "')");
			}
			}
		}else{
			$status_array{count($status_array)}["host"][0] = $item["Hostname"];
		}
	}
	}
	
	$non_templated_data_sources = db_fetch_assoc("select id from data_template_data where local_data_id > 0");
	
	if (sizeof($non_templated_data_sources) > 0) {
	foreach ($non_templated_data_sources as $item) {
		db_execute("delete from data_template_data_rra where data_template_data_id=" . $item["id"]);
		db_execute("delete from data_input_data where data_template_data_id=" . $item["id"]);
	}
	}
	
	db_execute("truncate table $database_default.data_local");
	db_execute("delete from $database_default.data_template_data where local_data_id > 0");
	db_execute("delete from $database_default.data_template_rrd where local_data_id > 0");
	
	$_ds = db_fetch_assoc("select * from $database_old.rrd_ds where subdsid=0");
	
	if (sizeof($_ds) > 0) {
	foreach ($_ds as $item) {
		$host_id = 0;
		$hostname = "";
		$data_template_id = 0; $local_data_template_data_id = 0; $local_data_template_rrd_id = 0;
		
		if ($item["SrcID"] == "11") {
			$inout = db_fetch_cell("select value from $database_old.src_data where dsid=" . $item["ID"] . " and fieldid=28");
			$hostname = db_fetch_cell("select value from $database_old.src_data where dsid=" . $item["ID"] . " and fieldid=21");
			
			if ($inout == "in") {
				$data_template_id = "1";
				$local_data_template_data_id = "1";
				$local_data_template_rrd_id = "1";
			}elseif ($inout == "out") {
				$data_template_id = "2";
				$local_data_template_data_id = "2";
				$local_data_template_rrd_id = "2";
			}
		}elseif ($item["SrcID"] == "13") {
			$hostname = db_fetch_cell("select value from $database_old.src_data where dsid=" . $item["ID"] . " and fieldid=41");
		}elseif ($item["SrcID"] == "1") {
			$hostname = db_fetch_cell("select value from $database_old.src_data where dsid=" . $item["ID"] . " and fieldid=1");
		}
		
		if ((!empty($hostname)) && (isset($ip_to_host_cache{gethostbyname($hostname)}))) {
			$host_id = $ip_to_host_cache{gethostbyname($hostname)};
		}
		
		if (empty($host_id)) {
			$host_id = 0;
		}
		
		if (db_execute("insert into data_local (id,data_template_id,host_id) values (0,$data_template_id,$host_id)")) {
			$local_data_id = db_fetch_insert_id();
			$status_array{count($status_array)}["data_local"][1] = $item["Name"];
			
			if (db_execute("insert into data_template_data (id,local_data_template_data_id,local_data_id,
				data_template_id,data_input_id,name,data_source_path,active,rrd_step) values (0,$local_data_template_data_id,$local_data_id,
				$data_template_id," . $data_input_cache{$item["SrcID"]} . ",'" . $item["Name"] . "','" . $item["DSPath"] . "',
				'" . $item["Active"] . "','" . $item["Step"] . "')")) {
				$data_template_data_cache{$item["ID"]} = db_fetch_insert_id();
				$status_array{count($status_array)}["data_source"][1] = $item["Name"];
				
				if ($item["IsParent"] == "0") {
					$old_output_field_id = db_fetch_cell("select ID from $database_old.src_fields where SrcID=" . $item["SrcID"] . " and InputOutput='out' and UpdateRRA='on'");
					
					if (db_execute("insert into data_template_rrd (id,local_data_template_rrd_id,
						local_data_id,data_template_id,rrd_maximum,rrd_minimum,rrd_heartbeat,
						data_source_type_id,data_source_name,data_input_field_id) values (0,$local_data_template_rrd_id,$local_data_id,$data_template_id,
						" . $item["MaxValue"] . "," . $item["MinValue"] . "," . $item["Heartbeat"] . ",
						" . $item["DataSourceTypeID"] . ",'" . $item["DSName"] . "'," . (empty($data_input_field_cache[$old_output_field_id]) ? "0" : $data_input_field_cache{$old_output_field_id}) . ")")) {
						$data_template_rrd_cache{$item["ID"]} = db_fetch_insert_id();
						$status_array{count($status_array)}["data_source_item"][1] = $item["DSName"];
					}else{
						$status_array{count($status_array)}["data_source_item"][0] = $item["DSName"];
					}
				}elseif ($item["IsParent"] == "1") {
					$_sub_ds = db_fetch_assoc("select * from $database_old.rrd_ds where subdsid=" . $item["ID"]);
					
					if (sizeof($_sub_ds) > 0) {
					foreach ($_sub_ds as $item2) {
						if (db_execute("insert into data_template_rrd (id,local_data_template_rrd_id,
							local_data_id,data_template_id,rrd_maximum,rrd_minimum,rrd_heartbeat,
							data_source_type_id,data_source_name,data_input_field_id) values (0,$local_data_template_rrd_id,$local_data_id,$data_template_id,
							" . $item2["MaxValue"] . "," . $item2["MinValue"] . "," . $item2["Heartbeat"] . ",
							" . $item2["DataSourceTypeID"] . ",'" . $item2["DSName"] . "',
							" . $data_input_field_cache{$item2["SubFieldID"]} . ")")) {
							$data_template_rrd_cache{$item2["ID"]} = db_fetch_insert_id();
							$status_array{count($status_array)}["data_source_item"][1] = $item2["DSName"];
						}else{
							$status_array{count($status_array)}["data_source_item"][0] = $item2["DSName"];
						}
					}
					}
				}
				
				/* ds data */
				$_ds_data = db_fetch_assoc("select * from $database_old.src_data where DSID=" . $item["ID"]);
				
				if (sizeof($_ds_data) > 0) {
				foreach ($_ds_data as $item2) {
					/*
					-- 0.6.8: --
					ID 13: Get SNMP Data
					41: IP Address
					42: SNMP Community
					43: SNMP OID
					
					ID 11: Get SNMP Network Data
					21: IP Address
					22: SNMP Community
					23: Interface Description (Optional)
					24: Interface Number (Optional)
					25: Octets
					28: In/Out Data (in or out)
					53: Interface MAC Address (Optional)
					56: Interface IP Address (Optional)
					
					-- 0.8: --
					ID 1: Get SNMP Data
					01: SNMP IP Address
					02: SNMP Community
					03: SNMP Username
					04: SNMP Password
					05: SNMP Version (1, 2, or 3)
					06: OID
					
					ID 2: Get SNMP Data (Indexed)
					07: SNMP IP Address
					08: SNMP Community
					09: SNMP Username (v3)
					10: SNMP Password (v3)
					11: SNMP Version (1, 2, or 3)
					12: Index Type
					13: Index Value
					14: Output Type ID
					*/
					$field_name = "";
					$field_value = "";
					
					if ($item["SrcID"] == "11") {
						if ((!empty($item2["Value"])) && ($item2["FieldID"] == "23")) {
							$field_name = "ifdesc";
							$field_name_id = "12";
							$field_value = $item2["Value"];
							$field_value_id = "13";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "24")) {
							$field_name = "ifindex";
							$field_name_id = "12";
							$field_value = $item2["Value"];
							$field_value_id = "13";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "53")) {
							$field_name = "ifhwaddr";
							$field_name_id = "12";
							$field_value = $item2["Value"];
							$field_value_id = "13";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "56")) {
							$field_name = "ifip";
							$field_name_id = "12";
							$field_value = $item2["Value"];
							$field_value_id = "13";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "21")) {
							$field_value = $item2["Value"];
							$field_value_id = "7";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "22")) {
							$field_value = $item2["Value"];
							$field_value_id = "8";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "28")) {
							$field_value = "1";
							$field_value_id = "11";
							$field_name = "1";
							$field_name_id = "14";
						}
						
						if (!empty($field_value)) {
							if (db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,
								t_value,value) values ($field_value_id," . $data_template_data_cache{$item2["DSID"]} . ",'','$field_value')")) {
								$status_array{count($status_array)}["data_source_data"][1] = $field_value;
								if (!empty($field_name)) {
									if (db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,
										t_value,value) values ($field_name_id," . $data_template_data_cache{$item2["DSID"]} . ",'','$field_name')")) {
										$status_array{count($status_array)}["data_source_data"][1] = $field_name;
									}else{
										$status_array{count($status_array)}["data_source_data"][0] = $field_name;
									}
								}
							}else{
								$status_array{count($status_array)}["data_source_data"][0] = $field_value;
							}
						}
						
						$traffic_graphs{$item["ID"]} = $data_template_data_cache{$item["ID"]};
					}elseif ($item["SrcID"] == "13") {
						if ((!empty($item2["Value"])) && ($item2["FieldID"] == "41")) {
							$field_value = $item2["Value"];
							$field_value_id = "1";
							$field_name = "1";
							$field_name_id = "5";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "42")) {
							$field_value = $item2["Value"];
							$field_value_id = "2";
						}elseif ((!empty($item2["Value"])) && ($item2["FieldID"] == "43")) {
							$field_value = $item2["Value"];
							$field_value_id = "6";
						}
						
						if (!empty($field_value)) {
							if (db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,
								t_value,value) values ($field_value_id," . $data_template_data_cache{$item2["DSID"]} . ",'','$field_value')")) {
								$status_array{count($status_array)}["data_source_data"][1] = $field_value;
								
								if (!empty($field_name)) {
									if (db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,
										t_value,value) values ($field_name_id," . $data_template_data_cache{$item2["DSID"]} . ",'','$field_name')")) {
										$status_array{count($status_array)}["data_source_data"][1] = $field_name;
									}else{
										$status_array{count($status_array)}["data_source_data"][0] = $field_name;
									}
								}
							}else{
								$status_array{count($status_array)}["data_source_data"][0] = $field_value;
							}
						}
					}else{
						if (!empty($item2["Value"])) {
							if (db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,
								t_value,value) values (" . $data_input_field_cache{$item2["FieldID"]} . ",
								" . $data_template_data_cache{$item2["DSID"]} . ",'','" . $item2["Value"] . "')")) {
								$status_array{count($status_array)}["data_source_data"][1] = $item2["Value"];
							}else{
								$status_array{count($status_array)}["data_source_data"][0] = $item2["Value"];
							}
						}
					}
				}
				}
				
				/* ds->rra mappings */
				$_ds_rra = db_fetch_assoc("select * from $database_old.lnk_ds_rra where DSID=" . $item["ID"]);
				
				if (sizeof($_ds_rra) > 0) {
				foreach ($_ds_rra as $item2) {
					if (db_execute("insert into data_template_data_rra (data_template_data_id,rra_id) values
						(" . $data_template_data_cache{$item2["DSID"]} . "," . $item2["RRAID"] . ")")) {
						$status_array{count($status_array)}["data_source_rra"][1] = $item2["RRAID"];
					}else{
						$status_array{count($status_array)}["data_source_rra"][0] = $item2["RRAID"];
					}
				}
				}
			}else{
				$status_array{count($status_array)}["data_source"][0] = $item["Name"];
			}
		}else{
			$status_array{count($status_array)}["data_local"][0] = $item["Name"];
		}
	}
	}
	
	db_execute("truncate table $database_default.cdef");
	db_execute("truncate table $database_default.cdef_items");
	
	$_cdef = db_fetch_assoc("select * from $database_old.rrd_ds_cdef");
	
	if (sizeof($_cdef) > 0) {
	foreach ($_cdef as $item) {
		if (db_execute("insert into cdef (id,name) values (0,'" . $item["Name"] . "')")) {
			$cdef_cache{$item["ID"]} = db_fetch_insert_id();
			$status_array{count($status_array)}["cdef"][1] = $item["Name"];
			
			$_cdef_items = db_fetch_assoc("select * from $database_old.rrd_ds_cdef_item where CDEFID=" . $item["ID"]);
			
			if ($item["Type"] == "2") {
				$_cdef_items[0]["CDEFID"] = $item["ID"];
				$_cdef_items[0]["Type"] = "Total";
				$_cdef_items[0]["Sequence"] = "1";
			}
			
			$cdef_ds_counter = 0;
			if (sizeof($_cdef_items) > 0) {
			foreach ($_cdef_items as $item2) {
				switch ($item2["Type"]) {
				case 'Custom Entry':
					$item_type = 6;
					$item_value = $item2["Custom"];
					break;
				case 'Data Source':
					if ($item2["CurrentDS"] == "on") {
						$item_type = 4;
						$item_value = "CURRENT_DATA_SOURCE";
					}else{
						$item_type = 6;
						$item_value = generate_graph_def_name("$cdef_ds_counter");
						$cdef_ds_counter++;
					}
					break;
				case 'CDEF Function':
					if (ereg('^(27|28|29|30|31)$', $item2["CDEFFunctionID"])) {
						$item_type = 2;
						$item_value = ($item2["CDEFFunctionID"] - 26);
					}else{
						$item_type = 1;
						$item_value = $item2["CDEFFunctionID"];
					}
					break;
				case 'Total':
					$item_type = 4;
					$item_value = "ALL_DATA_SOURCES_NODUPS";
					break;
				}
				
				if (db_execute("insert into cdef_items (id,cdef_id,sequence,type,value) values (0," . $cdef_cache{$item["ID"]} . ",
					" . $item2["Sequence"] . ",$item_type,'$item_value')")) {
					
					$status_array{count($status_array)}["cdef_item"][1] = "Type: $item_type, Value: $item_value";
				}else{
					$status_array{count($status_array)}["cdef_item"][0] = "Type: $item_type, Value: $item_value";
				}
			}
			}
		}else{
			$status_array{count($status_array)}["cdef"][0] = $item["Name"];
		}
	}
	}
	
	db_execute("truncate table $database_default.graph_local");
	db_execute("delete from $database_default.graph_templates_graph where local_graph_id > 0");
	db_execute("delete from $database_default.graph_templates_item where local_graph_id > 0");
	
	$_graphs = db_fetch_assoc("select * from $database_old.rrd_graph");
	
	if (sizeof($_graphs) > 0) {
	foreach ($_graphs as $item) {
		if (db_execute("insert into graph_local (id,graph_template_id) values (0,0)")) {
			$local_graph_id_cache{$item["ID"]} = db_fetch_insert_id();
			$status_array{count($status_array)}["graph_local"][1] = $item["Title"];
			
			if (db_execute("insert into graph_templates_graph (id,local_graph_template_graph_id,local_graph_id,graph_template_id,image_format_id,title,
				height,width,upper_limit,lower_limit,vertical_label,auto_scale,auto_scale_opts,auto_scale_log,
				auto_scale_rigid,auto_padding,base_value,export,unit_value,unit_exponent_value) values (
				0,0," . $local_graph_id_cache{$item["ID"]} . ",0," . $item["ImageFormatID"] . ",'" . $item["Title"] . "'," . $item["Height"] . ",
				" . $item["Width"] . "," . $item["UpperLimit"] . "," . $item["LowerLimit"] . ",'" . $item["VerticalLabel"] . "',
				'" . $item["AutoScale"] . "','" . $item["AutoScaleOpts"] . "','" . $item["AutoScaleLog"] . "',
				'" . $item["Rigid"] . "','" . $item["AutoPadding"] . "','" . $item["BaseValue"] . "',
				'" . $item["Export"] . "','" . $item["UnitValue"] . "','" . $item["UnitExponentValue"] . "')")) {
				$status_array{count($status_array)}["graph"][1] = $item["Title"];
				
				$_graph_items = db_fetch_assoc("select * from $database_old.rrd_graph_item where GraphID=" . $item["ID"] . " order by SequenceParent,Sequence");
				
				$seq = 0; $is_traffic_graph = -1;
				if (sizeof($_graph_items) > 0) {
				foreach ($_graph_items as $item2) {
					$seq++;
					
					if ($item2["GprintOpts"] == "1") {
						$gprint_id = 2;
					}elseif ($item2["GprintOpts"] == "2") {
						$gprint_id = 3;
					}
					
					if (!isset($cdef_cache{$item2["CDEFID"]})) {
						$cdef_cache{$item2["CDEFID"]} = 0;
					}
					
					if (!isset($data_template_rrd_cache{$item2["DSID"]})) {
						$data_template_rrd_cache{$item2["DSID"]} = 0;
					}
					
					/* this is a traffic graph */
					if ((isset($traffic_graphs{$item2["DSID"]})) && (sizeof($_graph_items) == 8) && ($is_traffic_graph != false)) {
						$is_traffic_graph = true;
					}else{
						$is_traffic_graph = false;
					}
					
					if (db_execute("insert into graph_templates_item (id,local_graph_template_item_id,
						local_graph_id,graph_template_id,task_item_id,color_id,graph_type_id,cdef_id,
						consolidation_function_id,text_format,value,hard_return,gprint_id,sequence) 
						values (0,0," . $local_graph_id_cache{$item["ID"]} . ",0," . $data_template_rrd_cache{$item2["DSID"]} . ",
						" . $item2["ColorID"] . "," . $item2["GraphTypeID"] . "," . $cdef_cache{$item2["CDEFID"]} . ",
						" . $item2["ConsolidationFunction"] . ",'" . $item2["TextFormat"] . "',
						'" . $item2["Value"] . "','" . $item2["HardReturn"] . "',$gprint_id,$seq)")) {
						$status_array{count($status_array)}["graph_item"][1] = $item2["TextFormat"];
					}else{
						$status_array{count($status_array)}["graph_item"][0] = $item2["TextFormat"];
					}
				}
				}
				
				/* if we've determined that this graph is a 'traffic graph', then switch to that template */
				if ($is_traffic_graph == true) {
					change_graph_template($local_graph_id_cache{$item["ID"]}, 1, true);
				}
			}else{
				$status_array{count($status_array)}["graph"][0] = $item["Title"];
			}
		}else{
			$status_array{count($status_array)}["graph_local"][0] = $item["Title"];
		}
	}
	}
	
	db_execute("truncate table $database_default.graph_tree");
	db_execute("truncate table $database_default.graph_tree_items");
	
	$_tree = db_fetch_assoc("select * from $database_old.graph_hierarchy");
	
	if (sizeof($_tree) > 0) {
	foreach ($_tree as $item) {
		if (db_execute("insert into graph_tree (id,user_id,name) values (0,0,'" . $item["Name"] . "')")) {
			$graph_tree_id = db_fetch_insert_id();
			$status_array{count($status_array)}["tree"][1] = $item["Name"];
			climb_tree(0, $item["ID"], 0, "", "");
			
			$_tree_items = db_fetch_assoc("select * from $database_old.graph_hierarchy_items where TreeID=" . $item["ID"]);
			
			if (sizeof($_tree_items) > 0) {
			foreach ($_tree_items as $item2) {
				if (!isset($local_graph_id_cache{$item2["GraphID"]})) {
					$local_graph_id_cache{$item2["GraphID"]} = 0;
				}
				
				if (db_execute("insert into graph_tree_items (id,graph_tree_id,local_graph_id,rra_id,title,
					order_key) values (0,$graph_tree_id," . $local_graph_id_cache{$item2["GraphID"]} . ",
					" . $item2["RRAID"] . ",'" . $item2["Title"] . "','" . $order_key_array{$item2["ID"]} . "')")) {
					$status_array{count($status_array)}["tree_item"][1] = $item2["ID"] . "/" . $item2["Title"];
				}else{
					$status_array{count($status_array)}["tree_item"][0] = $item2["ID"] . "/" . $item2["Title"];
				}
			}
			}
		}else{
			$status_array{count($status_array)}["tree"][0] = $item["Name"];
		}
	}
	}
	
	repopulate_poller_cache();
	
	return $status_array;
}

function climb_tree($parent, $tree_id, $branch, $prefix_key, $item_count_array) {
	global $database_old, $order_key_array;
	
	$tree = db_fetch_assoc("select ID from $database_old.graph_hierarchy_items where TreeID=$tree_id and Parent=$parent order by Sequence");
	
	if (sizeof($tree) > 0) {
	foreach ($tree as $item) {
		if (isset($item_count_array[$branch])) {
			$item_count_array[$branch]++;
		}else{
			$item_count_array[$branch] = 1;
		}
		
		$current_key_item = str_pad($item_count_array[$branch],2,'0',STR_PAD_LEFT);
		$order_key = str_pad("$prefix_key$current_key_item",60,'0',STR_PAD_RIGHT);
		$local_prefix_key = "$prefix_key$current_key_item";
		
		$order_key_array{$item["ID"]} = $order_key;
		
		if (sizeof(db_fetch_assoc("select ID from $database_old.graph_hierarchy_items where TreeID=$tree_id and Parent=" . $item["ID"])) > 0) {
			climb_tree($item["ID"], $tree_id, ($branch+1), "$local_prefix_key", $item_count_array);
		}
	}
	}
	
	return $branch;
}

?>
