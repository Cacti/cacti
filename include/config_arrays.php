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

$cdef_operators = array(1 => "+",
			     "-",
			     "*",
			     "/",
			     "%");

$cdef_functions = array(1 => "SIN",
			     "COS",
			     "LOG",
			     "EXP",
			     "FLOOR",
			     "CEIL",
			     "LT",
			     "LE",
			     "GT",
			     "GE",
			     "EQ",
			     "IF",
			     "MIN",
			     "MAX",
			     "LIMIT",
			     "DUP",
			     "EXC",
			     "POP",
			     "UN",
			     "UNKN",
			     "PREV",
			     "INF",
			     "NEGINF",
			     "NOW",
			     "TIME",
			     "LTIME");

				
$consolidation_functions = array(1 => "AVERAGE",
				      "MIN",
				      "MAX",
				      "LAST");
					
$data_source_types = array(1 => "GAUGE",
				"COUNTER",
				"DERIVE",
				"ABSOLUTE");
				
$graph_item_types = array(1 => "COMMENT",
			       "HRULE",
			       "VRULE",
			       "LINE1",
			       "LINE2",
			       "LINE3",
			       "AREA",
			       "STACK",
			       "GPRINT");
$image_types = array(1 => "PNG",
			  "GIF");


?>
