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
 | This program is distributed in the hope that it will be useful,        
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

function run_data_query($host_id, $snmp_query_id) {
	debug_log_insert("data_query", "Running data query [$snmp_query_id].");
	$type_id = db_fetch_cell("select data_input.type_id from snmp_query,data_input where snmp_query.data_input_id=data_input.id and snmp_query.id=$snmp_query_id");
	
	if ($type_id == "3") {
		debug_log_insert("data_query", "Found type = '3' [snmp query].");
		return query_snmp_host($host_id, $snmp_query_id);
	}elseif ($type_id == "4") {
		debug_log_insert("data_query", "Found type = '4 '[script query].");
		return query_script_host($host_id, $snmp_query_id);
	}else{
		debug_log_insert("data_query", "Unknown type = '$type_id'");
	}
}

function get_data_query_array($snmp_query_id) {
	global $config;
	
	include_once($config["library_path"] . "/xml.php");
	
	$xml_file_path = db_fetch_cell("select xml_path from snmp_query where id=$snmp_query_id");
	$xml_file_path = str_replace("<path_cacti>", $config["base_path"], $xml_file_path);
	
	if (!file_exists($xml_file_path)) {
		debug_log_insert("data_query", "Could not find data query XML file at '$xml_file_path'");
		return false;
	}
	
	debug_log_insert("data_query", "Found data query XML file at '$xml_file_path'");
	
	$data = implode("",file($xml_file_path));
	return xml2array($data);
}

function query_script_host($host_id, $snmp_query_id) {
	$script_queries = get_data_query_array($snmp_query_id);
	
	if ($script_queries == false) {
		debug_log_insert("data_query", "Error parsing XML file into an array.");
		return false;
	}
	
	debug_log_insert("data_query", "XML file parsed ok.");
	
	/* get a complete path for out target script */
	$script_path = substitute_data_query_path($script_queries["script_path"]);
	
	/* get any extra arguments that need to be passed to the script */
	if (!empty($script_queries["arg_prepend"])) {
		$extra_arguments = substitute_host_data($script_queries["arg_prepend"], "|", "|", $host_id);
	}else{
		$extra_arguments = "";
	}
	
	/* fetch specified index at specified OID */
	$script_index_array = exec_into_array("$script_path $extra_arguments " . $script_queries["arg_index"]);
	
	debug_log_insert("data_query", "Executing script for list of indexes '$script_path $extra_arguments " . $script_queries["arg_index"] . "'");
	
	db_execute("delete from host_snmp_cache where host_id=$host_id and snmp_query_id=$snmp_query_id");
	
	while (list($field_name, $field_array) = each($script_queries["fields"])) {
		if ($field_array["direction"] == "input") {
			$script_data_array = exec_into_array("$script_path $extra_arguments " . $script_queries["arg_query"] . " " . $field_array["query_name"]);
			
			debug_log_insert("data_query", "Executing script query '$script_path $extra_arguments " . $script_queries["arg_query"] . " " . $field_array["query_name"] . "'");
			
			for ($i=0;($i<sizeof($script_data_array));$i++) {
				if (preg_match("/(.*)" . preg_quote($script_queries["output_delimeter"]) . "(.*)/", $script_data_array[$i], $matches)) {
					$script_index = $matches[1];
					$field_value = $matches[2];
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','$field_value','$script_index','')");
					
					debug_log_insert("data_query", "Found item [$field_name='$field_value'] index: $script_index");
				}
			}
		}
	}
	
	return true;
}

function query_snmp_host($host_id, $snmp_query_id) {
	global $config;
	
	include_once($config["library_path"] . "/snmp.php");
	
	$host = db_fetch_row("select hostname,snmp_community,snmp_version,snmp_username,snmp_password,snmp_port,snmp_timeout from host where id=$host_id");
	
	$snmp_queries = get_data_query_array($snmp_query_id);
	
	if ((empty($host["hostname"])) || ($snmp_queries == false)) {
		debug_log_insert("data_query", "Error parsing XML file into an array.");
		return false;
	}
	
	debug_log_insert("data_query", "XML file parsed ok.");
	
	/* fetch specified index at specified OID */
	$snmp_index = cacti_snmp_walk($host["hostname"], $host["snmp_community"], $snmp_queries["oid_index"], $host["snmp_version"], $host["snmp_username"], $host["snmp_password"], $host["snmp_port"], $host["snmp_timeout"]);
	
	debug_log_insert("data_query", "Executing SNMP walk for list of indexes @ '" . $snmp_queries["oid_index"] . "'");
	
	/* no data found; get out */
	if (!$snmp_index) {
		debug_log_insert("data_query", "No SNMP data returned");
		return false;
	}
	
	db_execute("delete from host_snmp_cache where host_id=$host_id and snmp_query_id=$snmp_query_id");
	
	while (list($field_name, $field_array) = each($snmp_queries["fields"])) {
		if (($field_array["method"] == "get") && ($field_array["direction"] == "input")) {
			debug_log_insert("data_query", "Located input field '$field_name' [get]");
			
			if ($field_array["source"] == "value") {
				for ($i=0;($i<sizeof($snmp_index));$i++) {
					$oid = $field_array["oid"] .  "." . $snmp_index[$i]["value"];
					
					$value = cacti_snmp_get($host["hostname"], $host["snmp_community"], $oid, $host["snmp_version"], $host["snmp_username"], $host["snmp_password"], $host["snmp_port"], $host["snmp_timeout"]);
					
					debug_log_insert("data_query", "Executing SNMP get for data @ '$oid' [value='$value']");
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','$value'," . $snmp_index[$i]["value"] . ",'$oid')");
				}
			}
		}elseif (($field_array["method"] == "walk") && ($field_array["direction"] == "input")) {
			debug_log_insert("data_query", "Located input field '$field_name' [walk]");
			
			$snmp_data = cacti_snmp_walk($host["hostname"], $host["snmp_community"], $field_array["oid"], $host["snmp_version"], $host["snmp_username"], $host["snmp_password"], $host["snmp_port"], $host["snmp_timeout"]);
			
			debug_log_insert("data_query", "Executing SNMP walk for data @ '" . $field_array["oid"] . "'");
			
			if ($field_array["source"] == "value") {
				for ($i=0;($i<sizeof($snmp_data));$i++) {
					$snmp_index = ereg_replace('.*\.([0-9]+)$', "\\1", $snmp_data[$i]["oid"]);
					$oid = $field_array["oid"] . ".$snmp_index";
					
					debug_log_insert("data_query", "Found item [$field_name='" . $snmp_data[$i]["value"] . "'] index: $snmp_index [from value]");
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','" . $snmp_data[$i]["value"] . "',$snmp_index,'$oid')");
				}
			}elseif (ereg("^OID/REGEXP:", $field_array["source"])) {
				for ($i=0;($i<sizeof($snmp_data));$i++) {
					$value = ereg_replace(ereg_replace("^OID/REGEXP:", "", $field_array["source"]), "\\1", $snmp_data[$i]["oid"]);
					$snmp_index = $snmp_data[$i]["value"];
					$oid = $field_array["oid"] .  "." . $value;
					
					debug_log_insert("data_query", "Found item [$field_name='$value'] index: $snmp_index [from regexp oid parse]");
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','$value',$snmp_index,'$oid')");
				}
			}elseif (ereg("^VALUE/REGEXP:", $field_array["source"])) {
				for ($i=0;($i<sizeof($snmp_data));$i++) {
					$value = ereg_replace(ereg_replace("^VALUE/REGEXP:", "", $field_array["source"]), "\\1", $snmp_data[$i]["value"]);
					$snmp_index = ereg_replace('.*\.([0-9]+)$', "\\1", $snmp_data[$i]["oid"]);
					$oid = $field_array["oid"] .  "." . $value;
					
					debug_log_insert("data_query", "Found item [$field_name='$value'] index: $snmp_index [from regexp value parse]");
					
					db_execute("replace into host_snmp_cache 
						(host_id,snmp_query_id,field_name,field_value,snmp_index,oid)
						values ($host_id,$snmp_query_id,'$field_name','$value',$snmp_index,'$oid')");
				}
			}
		}
	}
	
	return true;
}

/* data_query_index - returns an array containing the data query ID and index value given
     a data query index type/value combination and a host ID
   @arg $index_type - the name of the index to match
   @arg $index_value - the value of the index to match
   @arg $host_id - (int) the host ID to match
   @returns - (array) the data query ID and index that matches the three arguments */
function data_query_index($index_type, $index_value, $host_id) {
	return db_fetch_row("select
		host_snmp_cache.snmp_query_id,
		host_snmp_cache.snmp_index
		from host_snmp_cache
		where host_snmp_cache.field_name='$index_type'
		and host_snmp_cache.field_value='$index_value'
		and host_snmp_cache.host_id=$host_id");
}

/* data_query_field_list - returns an array containing data query information for a given data source
   @arg $data_template_data_id - the ID of the data source to retrieve information for
   @returns - (array) an array that looks like:
     Array
     (
        [index_type] => ifIndex
        [index_value] => 3
        [output_type] => 13
     ) */
function data_query_field_list($data_template_data_id) {
	$field = db_fetch_assoc("select
		data_input_fields.type_code,
		data_input_data.value
		from data_input_fields,data_input_data
		where data_input_fields.id=data_input_data.data_input_field_id
		and data_input_data.data_template_data_id=$data_template_data_id
		and (data_input_fields.type_code='index_type' or data_input_fields.type_code='index_value' or data_input_fields.type_code='output_type')");
	$field = array_rekey($field, "type_code", "value");
	
	if ((!isset($field["index_type"])) || (!isset($field["index_value"])) || (!isset($field["output_type"]))) {
		return 0;
	}else{
		return $field;
	}
}

/* encode_data_query_index - encodes a data query index value so that it can be included
     inside of a form
   @arg $index - the index name to encode
   @returns - the encoded data query index */
function encode_data_query_index($index) {
	return md5($index);
}

/* decode_data_query_index - decodes a data query index value so that it can be read from
     a form
   @arg $encoded_index - the index that was encoded with encode_data_query_index()
   @arg $data_query_id - the id of the data query that this index belongs to
   @arg $encoded_index - the id of the host that this index belongs to
   @returns - the decoded data query index */
function decode_data_query_index($encoded_index, $data_query_id, $host_id) {
	/* yes, i know MySQL has a MD5() function that would make this a bit quicker. however i would like to
	keep things abstracted for now so Cacti works with ADODB fully when i get around to porting my db calls */
	$indexes = db_fetch_assoc("select snmp_index from host_snmp_cache where host_id=$host_id and snmp_query_id=$data_query_id  group by snmp_index");
	
	if (sizeof($indexes) > 0) {
	foreach ($indexes as $index) {
		if (encode_data_query_index($index["snmp_index"]) == $encoded_index) {
			return $index["snmp_index"];
		}
	}
	}
}

function update_graph_data_query_cache($local_graph_id) {
	global $config;
	
	include_once($config["library_path"] . "/data_query.php");
	
	$host_id = db_fetch_cell("select host_id from graph_local where id=$local_graph_id");
	
	$field = data_query_field_list(db_fetch_cell("select
		data_template_data.id
		from graph_templates_item,data_template_rrd,data_template_data 
		where graph_templates_item.task_item_id=data_template_rrd.id
		and data_template_rrd.local_data_id=data_template_data.local_data_id
		and graph_templates_item.local_graph_id=$local_graph_id
		limit 0,1"));
	
	if (empty($field)) { return; }
	
	$query = data_query_index($field["index_type"], $field["index_value"], $host_id);
	
	if (($query["snmp_query_id"] != "0") && ($query["snmp_index"] != "")) {
		db_execute("update graph_local set snmp_query_id=" . $query["snmp_query_id"] . ",snmp_index='" . $query["snmp_index"] . "' where id=$local_graph_id");
		
		/* update data source/graph title cache */
		update_data_source_title_cache_from_query($query["snmp_query_id"], $query["snmp_index"]);
		update_graph_title_cache_from_query($query["snmp_query_id"], $query["snmp_index"]);
	}
}

function update_data_source_data_query_cache($local_data_id) {
	global $config;
	
	include_once($config["library_path"] . "/data_query.php");
	
	$host_id = db_fetch_cell("select host_id from data_local where id=$local_data_id");
	
	$field = data_query_field_list(db_fetch_cell("select
		data_template_data.id
		from data_template_data 
		where data_template_data.local_data_id=$local_data_id"));
	
	if (empty($field)) { return; }
	
	$query = data_query_index($field["index_type"], $field["index_value"], $host_id);
	
	if (($query["snmp_query_id"] != "0") && ($query["snmp_index"] != "")) {
		db_execute("update data_local set snmp_query_id=" . $query["snmp_query_id"] . ",snmp_index='" . $query["snmp_index"] . "' where id=$local_data_id");
		
		/* update graph title cache */
		update_graph_title_cache_from_query($query["snmp_query_id"], $query["snmp_index"]);
	}
}

?>
