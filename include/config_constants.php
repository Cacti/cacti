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

define("HOST_GROUPING_GRAPH_TEMPLATE", 1);
define("HOST_GROUPING_DATA_QUERY_INDEX", 2);

define("TREE_ORDERING_NONE", 1);
define("TREE_ORDERING_ALPHABETIC", 2);
define("TREE_ORDERING_NUMERIC", 3);

define("TREE_ITEM_TYPE_HEADER", 1);
define("TREE_ITEM_TYPE_GRAPH", 2);
define("TREE_ITEM_TYPE_HOST", 3);

define("RRDTOOL_PIPE_CHILD_READ", 0);
define("RRDTOOL_PIPE_CHILD_WRITE", 1);
define("RRDTOOL_PIPE_WRITE", 2);

define("RRDTOOL_OUTPUT_NULL", 0);
define("RRDTOOL_OUTPUT_STDOUT", 1);
define("RRDTOOL_OUTPUT_STDERR", 2);
define("RRDTOOL_OUTPUT_GRAPH_DATA", 3);

define("DATA_QUERY_AUTOINDEX_NONE", 0);
define("DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME", 1);
define("DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE", 2);
define("DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION", 3);

define("DATA_INPUT_TYPE_SCRIPT", 1);
define("DATA_INPUT_TYPE_SNMP", 2);
define("DATA_INPUT_TYPE_SNMP_QUERY", 3);
define("DATA_INPUT_TYPE_SCRIPT_QUERY", 4);
define("DATA_INPUT_TYPE_PHP_SCRIPT_SERVER", 5);
define("DATA_INPUT_TYPE_PERL_SCRIPT_SERVER", 6);
define("DATA_INPUT_TYPE_PHP_SCRIPT_QUERY_SERVER", 7);
define("DATA_INPUT_TYPE_PERL_SCRIPT_QUERY_SERVER", 8);

define("POLLER_ACTION_SNMP", 0);
define("POLLER_ACTION_SCRIPT", 1);
define("POLLER_ACTION_SCRIPT_PHP", 2);

define("POLLER_COMMAND_REINDEX", 1);

define("LOW", 1);
define("HIGH", 2);
define("DEBUG", 3);

?>