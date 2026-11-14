<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require __DIR__ . '/automation_creation_bootstrap.php';
clear_fixture();
$pdo->exec('DELETE FROM snmp_query_graph_rrd');
$pdo->exec('DELETE FROM data_input_data');
$pdo->exec("INSERT INTO data_template (id,hash,name) VALUES (1,'review-data','Review data')");
$pdo->exec("INSERT INTO data_template_rrd (id,hash,local_data_id,data_template_id,data_source_name) VALUES (1,'review-template',0,1,'value'),(2,'',1,1,'value')");
$pdo->exec("INSERT INTO data_local (id,host_id,data_template_id,snmp_query_id,snmp_index) VALUES (1,1,1,1,'7')");
$pdo->exec("INSERT INTO data_template_data (id,local_data_id,data_template_id,data_input_id,name) VALUES (1,1,1,2,'Review existing source')");
$pdo->exec("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES (14,1,'','1')");
$pdo->exec("INSERT INTO snmp_query_graph (id,snmp_query_id,graph_template_id,name) VALUES (1,1,1,'First graph'),(2,1,2,'Second graph using same counter'),(3,1,3,'Separate counter graph')");
$pdo->exec("INSERT INTO snmp_query_graph_rrd VALUES (1,1,1,'counterA'),(2,1,1,'counterA')");
$pdo->exec('UPDATE data_template_rrd SET local_data_template_rrd_id=1 WHERE id=2');
$template = ['id'=>1, 'data_source_name'=>'value'];
$query    = ['snmp_query_id'=>1, 'snmp_query_graph_id'=>2, 'snmp_index'=>'7'];
$before   = data_source_exists(2,1,$template,$query);
check((int)($before['id'] ?? 0) === 1,'existing matching source reused before unrelated mapping');
$pdo->exec("INSERT INTO snmp_query_graph_rrd VALUES (3,1,1,'counterB')");
$after = data_source_exists(2,1,$template,$query);
check((int)($after['id'] ?? 0) === 1,'unrelated mappings do not prevent reuse');
$query['snmp_query_graph_id'] = 3;
$different                    = data_source_exists(3,1,$template,$query);
check($different === [],'a different counter must not reuse the existing source');
$pdo->exec("INSERT INTO snmp_query_graph_rrd VALUES (2,2,99,'unrelatedTemplateCounter')");
$query['snmp_query_graph_id'] = 2;
$multi                        = data_source_exists(2,1,$template,$query);
check((int)($multi['id'] ?? 0) === 1,'another data template does not pollute requested fields');
$pdo->exec("INSERT INTO data_template_rrd (id,hash,local_data_id,data_template_id,data_source_name) VALUES (99,'unused',0,1,'unused')");
check((int)(data_source_exists(2,1,$template,$query)['id'] ?? 0) === 1,'unmapped template outputs do not prevent reuse');
// Reuse traffic sources across 32/64-bit output types in either direction.
$pdo->exec('DELETE FROM snmp_query_graph_rrd');
$pdo->exec("INSERT INTO data_template_rrd (id,hash,local_data_id,data_template_id,data_source_name,local_data_template_rrd_id) VALUES (3,'out',0,1,'out',0),(4,'',1,1,'out',3)");
$pdo->exec("INSERT INTO snmp_query_graph_rrd VALUES (1,1,1,'ifInOctets'),(1,1,3,'ifOutOctets'),(2,1,1,'ifHCInOctets'),(2,1,3,'ifHCOutOctets')");
$traffic64 = data_source_exists(2,1,$template,$query);
$pdo->exec("UPDATE data_input_data SET value='2' WHERE data_template_data_id=1");
$query['snmp_query_graph_id'] = 1;
$traffic32                    = data_source_exists(1,1,$template,$query);
check((int)($traffic64['id'] ?? 0) === 1 && (int)($traffic32['id'] ?? 0) === 1,'traffic width changes reuse existing source');
print json_encode(['before'=>$before['id'], 'after'=>$after['id'], 'different'=>$different, 'multi'=>$multi['id'], 'traffic32'=>$traffic32['id'], 'traffic64'=>$traffic64['id']]) . PHP_EOL;
