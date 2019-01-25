<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_0_8_4() {
	global $database_log;

	db_install_drop_table('user_realm');
	db_install_drop_table('user_realm_filename');
	db_install_drop_table('host_template_data_sv');
	db_install_drop_table('host_template_graph_sv');

	if (db_column_exists('host', 'management_ip')) {
		db_install_execute("UPDATE `host` set hostname=management_ip;");
		db_install_execute("UPDATE `data_input_fields` set type_code='hostname' where type_code='management_ip';");
	}

	if (db_column_exists('data_input_data_cache', 'management_ip')) {
		db_install_execute("ALTER TABLE `data_input_data_cache` CHANGE `management_ip` `hostname` VARCHAR( 250 ) NOT NULL, ADD `snmp_port` MEDIUMINT( 5 ) UNSIGNED DEFAULT '161' NOT NULL AFTER `snmp_password`, ADD `snmp_timeout` MEDIUMINT( 8 ) UNSIGNED NOT NULL AFTER `snmp_port`;");
	}

	db_install_add_column('host', array('name' => 'snmp_port', 'type' => 'mediumint(5)', 'NULL' => false, 'after' => 'snmp_password', 'default' => '161'));

	db_install_drop_column('host', 'management_ip');
	db_install_drop_column('data_input', 'output_string');

	db_install_add_key('host_snmp_cache', 'KEY', 'PRIMARY',  array('host_id' , 'snmp_query_id', 'field_name' , 'snmp_index' ));

	/* hash columns for xml export/import code */
	$field_data = array(
		'name' => 'hash',
		'type' => 'varchar(32)',
		'NULL' => false,
		'after' => 'id'
	);

	$table_data = array(
		'data_input_fields',    'data_input_fields',       'data_template_rrd',
		'graph_template_input', 'graph_templates_item',    'host_template',
		'snmp_query_graph',     'snmp_query_graph_rrd_sv', 'snmp_query_graph_sv',
		'cdef_items',           'cdef',                    'data_input',
		'data_template',        'graph_templates',         'graph_templates_gprint',
		'snmp_query',           'rra');


	foreach ($table_data as $table_name) {
		db_install_add_column($table_name, $field_data);
	}

	/* new realms */
	$users = db_fetch_assoc("select id from user_auth");

	if ($users !== false && cacti_sizeof($users) > 0) {
		foreach ($users as $user) {
			$realms = db_fetch_assoc("select realm_id from user_auth_realm where user_id=" . $user["id"]);
			if ($realms !== false && cacti_sizeof($realms) == 13) {
				db_install_execute("insert into user_auth_realm (user_id,realm_id) values (" . $user["id"] . ",4)");
				db_install_execute("insert into user_auth_realm (user_id,realm_id) values (" . $user["id"] . ",16)");
				db_install_execute("insert into user_auth_realm (user_id,realm_id) values (" . $user["id"] . ",17)");
			}
		}
	}

	/* there are a LOT of hashes to set */
	db_install_execute("update cdef set hash='73f95f8b77b5508157d64047342c421e' where id=2;");
	db_install_execute("update cdef_items set hash='9bbf6b792507bb9bb17d2af0970f9be9' where id=7;");
	db_install_execute("update cdef_items set hash='a4b8eb2c3bf4920a3ef571a7a004be53' where id=9;");
	db_install_execute("update cdef_items set hash='caa4e023ac2d7b1c4b4c8c4adfd55dfe' where id=8;");
	db_install_execute("update cdef set hash='3d352eed9fa8f7b2791205b3273708c7' where id=3;");
	db_install_execute("update cdef_items set hash='c888c9fe6b62c26c4bfe23e18991731d' where id=10;");
	db_install_execute("update cdef_items set hash='1e1d0b29a94e08b648c8f053715442a0' where id=11;");
	db_install_execute("update cdef_items set hash='4355c197998c7f8b285be7821ddc6da4' where id=12;");
	db_install_execute("update cdef set hash='e961cc8ec04fda6ed4981cf5ad501aa5' where id=4;");
	db_install_execute("update cdef_items set hash='40bb7a1143b0f2e2efca14eb356236de' where id=13;");
	db_install_execute("update cdef_items set hash='42686ea0925c0220924b7d333599cd67' where id=14;");
	db_install_execute("update cdef_items set hash='faf1b148b2c0e0527362ed5b8ca1d351' where id=15;");
	db_install_execute("update cdef set hash='f1ac79f05f255c02f914c920f1038c54' where id=12;");
	db_install_execute("update cdef_items set hash='0ef6b8a42dc83b4e43e437960fccd2ea' where id=16;");
	db_install_execute("update cdef set hash='634a23af5e78af0964e8d33b1a4ed26b' where id=14;");
	db_install_execute("update cdef_items set hash='86370cfa0008fe8c56b28be80ee39a40' where id=18;");
	db_install_execute("update cdef_items set hash='9a35cc60d47691af37f6fddf02064e20' where id=19;");
	db_install_execute("update cdef_items set hash='5d7a7941ec0440b257e5598a27dd1688' where id=20;");
	db_install_execute("update cdef set hash='068984b5ccdfd2048869efae5166f722' where id=15;");
	db_install_execute("update cdef_items set hash='44fd595c60539ff0f5817731d9f43a85' where id=21;");
	db_install_execute("update cdef_items set hash='aa38be265e5ac31783e57ce6f9314e9a' where id=22;");
	db_install_execute("update cdef_items set hash='204423d4b2598f1f7252eea19458345c' where id=23;");
	db_install_execute("update graph_templates_gprint set hash='e9c43831e54eca8069317a2ce8c6f751' where id=2;");
	db_install_execute("update graph_templates_gprint set hash='19414480d6897c8731c7dc6c5310653e' where id=3;");
	db_install_execute("update graph_templates_gprint set hash='304a778405392f878a6db435afffc1e9' where id=4;");
	db_install_execute("update data_input set hash='3eb92bb845b9660a7445cf9740726522' where id=1;");
	db_install_execute("update data_input_fields set hash='92f5906c8dc0f964b41f4253df582c38' where id=1;");
	db_install_execute("update data_input_fields set hash='32285d5bf16e56c478f5e83f32cda9ef' where id=2;");
	db_install_execute("update data_input_fields set hash='ad14ac90641aed388139f6ba86a2e48b' where id=3;");
	db_install_execute("update data_input_fields set hash='9c55a74bd571b4f00a96fd4b793278c6' where id=4;");
	db_install_execute("update data_input_fields set hash='012ccb1d3687d3edb29c002ea66e72da' where id=5;");
	db_install_execute("update data_input_fields set hash='4276a5ec6e3fe33995129041b1909762' where id=6;");
	db_install_execute("update data_input set hash='bf566c869ac6443b0c75d1c32b5a350e' where id=2;");
	db_install_execute("update data_input_fields set hash='617cdc8a230615e59f06f361ef6e7728' where id=7;");
	db_install_execute("update data_input_fields set hash='acb449d1451e8a2a655c2c99d31142c7' where id=8;");
	db_install_execute("update data_input_fields set hash='f4facc5e2ca7ebee621f09bc6d9fc792' where id=9;");
	db_install_execute("update data_input_fields set hash='1cc1493a6781af2c478fa4de971531cf' where id=10;");
	db_install_execute("update data_input_fields set hash='b5c23f246559df38662c255f4aa21d6b' where id=11;");
	db_install_execute("update data_input_fields set hash='6027a919c7c7731fbe095b6f53ab127b' where id=12;");
	db_install_execute("update data_input_fields set hash='cbbe5c1ddfb264a6e5d509ce1c78c95f' where id=13;");
	db_install_execute("update data_input_fields set hash='e6deda7be0f391399c5130e7c4a48b28' where id=14;");
	db_install_execute("update data_input set hash='274f4685461170b9eb1b98d22567ab5e' where id=3;");
	db_install_execute("update data_input_fields set hash='edfd72783ad02df128ff82fc9324b4b9' where id=15;");
	db_install_execute("update data_input_fields set hash='8b75fb61d288f0b5fc0bd3056af3689b' where id=16;");
	db_install_execute("update data_input set hash='95ed0993eb3095f9920d431ac80f4231' where id=4;");
	db_install_execute("update data_input_fields set hash='363588d49b263d30aecb683c52774f39' where id=17;");
	db_install_execute("update data_input_fields set hash='ad139a9e1d69881da36fca07889abf58' where id=18;");
	db_install_execute("update data_input_fields set hash='5db9fee64824c08258c7ff6f8bc53337' where id=19;");
	db_install_execute("update data_input set hash='79a284e136bb6b061c6f96ec219ac448' where id=5;");
	db_install_execute("update data_input_fields set hash='c0cfd0beae5e79927c5a360076706820' where id=20;");
	db_install_execute("update data_input_fields set hash='52c58ad414d9a2a83b00a7a51be75a53' where id=21;");
	db_install_execute("update data_input set hash='362e6d4768937c4f899dd21b91ef0ff8' where id=6;");
	db_install_execute("update data_input_fields set hash='05eb5d710f0814871b8515845521f8d7' where id=22;");
	db_install_execute("update data_input_fields set hash='86cb1cbfde66279dbc7f1144f43a3219' where id=23;");
	db_install_execute("update data_input set hash='a637359e0a4287ba43048a5fdf202066' where id=7;");
	db_install_execute("update data_input_fields set hash='d5a8dd5fbe6a5af11667c0039af41386' where id=24;");
	db_install_execute("update data_input set hash='47d6bfe8be57a45171afd678920bd399' where id=8;");
	db_install_execute("update data_input_fields set hash='8848cdcae831595951a3f6af04eec93b' where id=25;");
	db_install_execute("update data_input_fields set hash='3d1288d33008430ce354e8b9c162f7ff' where id=26;");
	db_install_execute("update data_input set hash='cc948e4de13f32b6aea45abaadd287a3' where id=9;");
	db_install_execute("update data_input_fields set hash='c6af570bb2ed9c84abf32033702e2860' where id=27;");
	db_install_execute("update data_input_fields set hash='f9389860f5c5340c9b27fca0b4ee5e71' where id=28;");
	db_install_execute("update data_input set hash='8bd153aeb06e3ff89efc73f35849a7a0' where id=10;");
	db_install_execute("update data_input_fields set hash='5fbadb91ad66f203463c1187fe7bd9d5' where id=29;");
	db_install_execute("update data_input_fields set hash='6ac4330d123c69067d36a933d105e89a' where id=30;");
	db_install_execute("update data_input set hash='80e9e4c4191a5da189ae26d0e237f015' where id=11;");
	db_install_execute("update data_input_fields set hash='d39556ecad6166701bfb0e28c5a11108' where id=31;");
	db_install_execute("update data_input_fields set hash='3b7caa46eb809fc238de6ef18b6e10d5' where id=32;");
	db_install_execute("update data_input_fields set hash='74af2e42dc12956c4817c2ef5d9983f9' where id=33;");
	db_install_execute("update data_input_fields set hash='8ae57f09f787656bf4ac541e8bd12537' where id=34;");
	db_install_execute("update data_template set hash='c8a8f50f5f4a465368222594c5709ede' where id=3;");
	db_install_execute("update data_template_rrd set hash='2d53f9c76767a2ae8909f4152fd473a4' where id=3;");
	db_install_execute("update data_template_rrd set hash='93d91aa7a3cc5473e7b195d5d6e6e675' where id=4;");
	db_install_execute("update data_template set hash='cdfed2d401723d2f41fc239d4ce249c7' where id=4;");
	db_install_execute("update data_template_rrd set hash='7bee7987bbf30a3bc429d2a67c6b2595' where id=5;");
	db_install_execute("update data_template set hash='a27e816377d2ac6434a87c494559c726' where id=5;");
	db_install_execute("update data_template_rrd set hash='ddccd7fbdece499da0235b4098b87f9e' where id=6;");
	db_install_execute("update data_template set hash='c06c3d20eccb9598939dc597701ff574' where id=6;");
	db_install_execute("update data_template_rrd set hash='122ab2097f8c6403b7b90cde7b9e2bc2' where id=7;");
	db_install_execute("update data_template set hash='a14f2d6f233b05e64263ff03a5b0b386' where id=7;");
	db_install_execute("update data_template_rrd set hash='34f50c820092ea0fecba25b4b94a7946' where id=8;");
	db_install_execute("update data_template set hash='def1a9019d888ed2ad2e106aa9595ede' where id=8;");
	db_install_execute("update data_template_rrd set hash='830b811d1834e5ba0e2af93bd92db057' where id=9;");
	db_install_execute("update data_template set hash='513a99ae3c9c4413609c1534ffc36eab' where id=9;");
	db_install_execute("update data_template_rrd set hash='2f1b016a2465eef3f7369f6313cd4a94' where id=10;");
	db_install_execute("update data_template set hash='77404ae93c9cc410f1c2c717e7117378' where id=10;");
	db_install_execute("update data_template_rrd set hash='28ffcecaf8b50e49f676f2d4a822685d' where id=11;");
	db_install_execute("update data_template set hash='9e72511e127de200733eb502eb818e1d' where id=11;");
	db_install_execute("update data_template_rrd set hash='8175ca431c8fe50efff5a1d3ae51b55d' where id=12;");
	db_install_execute("update data_template_rrd set hash='a2eeb8acd6ea01cd0e3ac852965c0eb6' where id=13;");
	db_install_execute("update data_template_rrd set hash='9f951b7fb3b19285a411aebb5254a831' where id=14;");
	db_install_execute("update data_template set hash='dc33aa9a8e71fb7c61ec0e7a6da074aa' where id=13;");
	db_install_execute("update data_template_rrd set hash='a4df3de5238d3beabee1a2fe140d3d80' where id=16;");
	db_install_execute("update data_template set hash='41f55087d067142d702dd3c73c98f020' where id=15;");
	db_install_execute("update data_template_rrd set hash='7fea6acc9b1a19484b4cb4cef2b6c5da' where id=18;");
	db_install_execute("update data_template set hash='9b8c92d3c32703900ff7dd653bfc9cd8' where id=16;");
	db_install_execute("update data_template_rrd set hash='f1ba3a5b17b95825021241398bb0f277' where id=19;");
	db_install_execute("update data_template set hash='c221c2164c585b6da378013a7a6a2c13' where id=17;");
	db_install_execute("update data_template_rrd set hash='46a5afe8e6c0419172c76421dc9e304a' where id=20;");
	db_install_execute("update data_template set hash='a30a81cb1de65b52b7da542c8df3f188' where id=18;");
	db_install_execute("update data_template_rrd set hash='962fd1994fe9cae87fb36436bdb8a742' where id=21;");
	db_install_execute("update data_template set hash='0de466a1b81dfe581d44ac014b86553a' where id=19;");
	db_install_execute("update data_template_rrd set hash='7a8dd1111a8624369906bf2cd6ea9ca9' where id=22;");
	db_install_execute("update data_template set hash='bbe2da0708103029fbf949817d3a4537' where id=20;");
	db_install_execute("update data_template_rrd set hash='ddb6e74d34d2f1969ce85f809dbac23d' where id=23;");
	db_install_execute("update data_template set hash='e4ac5d5fe73e3c773671c6d0498a8d9d' where id=22;");
	db_install_execute("update data_template_rrd set hash='289311d10336941d33d9a1c48a7b11ee' where id=25;");
	db_install_execute("update data_template set hash='f29f8c998425eedd249be1e7caf90ceb' where id=23;");
	db_install_execute("update data_template_rrd set hash='02216f036cca04655ee2f67fedb6f4f0' where id=26;");
	db_install_execute("update data_template set hash='7a6216a113e19881e35565312db8a371' where id=24;");
	db_install_execute("update data_template_rrd set hash='9e402c0f29131ef7139c20bd500b4e8a' where id=27;");
	db_install_execute("update data_template set hash='1dbd1251c8e94b334c0e6aeae5ca4b8d' where id=25;");
	db_install_execute("update data_template_rrd set hash='46717dfe3c8c030d8b5ec0874f9dbdca' where id=28;");
	db_install_execute("update data_template set hash='1a4c5264eb27b5e57acd3160af770a61' where id=26;");
	db_install_execute("update data_template_rrd set hash='7a88a60729af62561812c43bde61dfc1' where id=29;");
	db_install_execute("update data_template set hash='e9def3a0e409f517cb804dfeba4ccd90' where id=27;");
	db_install_execute("update data_template_rrd set hash='3c0fd1a188b64a662dfbfa985648397b' where id=30;");
	db_install_execute("update data_template set hash='4e00eeac4938bc51985ad71ae85c0df5' where id=28;");
	db_install_execute("update data_template_rrd set hash='82b26f81d81fa14e9159618cfa6b4ba8' where id=31;");
	db_install_execute("update data_template set hash='1fd08cfd5825b8273ffaa2c4ac0d7919' where id=29;");
	db_install_execute("update data_template_rrd set hash='f888bdbbe48a17cbb343fefabbc74064' where id=32;");
	db_install_execute("update data_template set hash='9b82d44eb563027659683765f92c9757' where id=30;");
	db_install_execute("update data_template_rrd set hash='ed44c2438ef7e46e2aeed2b6c580815c' where id=33;");
	db_install_execute("update data_template set hash='87847714d19f405ff3c74f3341b3f940' where id=31;");
	db_install_execute("update data_template_rrd set hash='9b3a00c9e3530d9e58895ac38271361e' where id=34;");
	db_install_execute("update data_template set hash='308ac157f24e2763f8cd828a80b3e5ff' where id=32;");
	db_install_execute("update data_template_rrd set hash='6746c2ed836ecc68a71bbddf06b0e5d9' where id=35;");
	db_install_execute("update data_template set hash='797a3e92b0039841b52e441a2823a6fb' where id=33;");
	db_install_execute("update data_template_rrd set hash='9835d9e1a8c78aa2475d752e8fa74812' where id=36;");
	db_install_execute("update data_template set hash='fa15932d3cab0da2ab94c69b1a9f5ca7' where id=34;");
	db_install_execute("update data_template_rrd set hash='9c78dc1981bcea841b8c827c6dc0d26c' where id=37;");
	db_install_execute("update data_template set hash='6ce4ab04378f9f3b03ee0623abb6479f' where id=35;");
	db_install_execute("update data_template_rrd set hash='62a56dc76fe4cd8566a31b5df0274cc3' where id=38;");
	db_install_execute("update data_template_rrd set hash='2e366ab49d0e0238fb4e3141ea5a88c3' where id=39;");
	db_install_execute("update data_template_rrd set hash='dceedc84718dd93a5affe4b190bca810' where id=40;");
	db_install_execute("update data_template set hash='03060555fab086b8412bbf9951179cd9' where id=36;");
	db_install_execute("update data_template_rrd set hash='93330503f1cf67db00d8fe636035e545' where id=42;");
	db_install_execute("update data_template_rrd set hash='6b0fe4aa6aaf22ef9cfbbe96d87fa0d7' where id=43;");
	db_install_execute("update data_template set hash='e4ac6919d4f6f21ec5b281a1d6ac4d4e' where id=37;");
	db_install_execute("update data_template_rrd set hash='4c82df790325d789d304e6ee5cd4ab7d' where id=44;");
	db_install_execute("update data_template_rrd set hash='07175541991def89bd02d28a215f6fcc' where id=56;");
	db_install_execute("update data_template set hash='36335cd98633963a575b70639cd2fdad' where id=38;");
	db_install_execute("update data_template_rrd set hash='c802e2fd77f5b0a4c4298951bf65957c' where id=46;");
	db_install_execute("update data_template_rrd set hash='4e2a72240955380dc8ffacfcc8c09874' where id=47;");
	db_install_execute("update data_template_rrd set hash='13ebb33f9cbccfcba828db1075a8167c' where id=50;");
	db_install_execute("update data_template_rrd set hash='31399c3725bee7e09ec04049e3d5cd17' where id=51;");
	db_install_execute("update data_template set hash='2f654f7d69ac71a5d56b1db8543ccad3' where id=39;");
	db_install_execute("update data_template_rrd set hash='636672962b5bb2f31d86985e2ab4bdfe' where id=48;");
	db_install_execute("update data_template_rrd set hash='18ce92c125a236a190ee9dd948f56268' where id=49;");
	db_install_execute("update data_template set hash='c84e511401a747409053c90ba910d0fe' where id=40;");
	db_install_execute("update data_template_rrd set hash='7be68cbc4ee0b2973eb9785f8c7a35c7' where id=52;");
	db_install_execute("update data_template_rrd set hash='93e2b6f59b10b13f2ddf2da3ae98b89a' where id=53;");
	db_install_execute("update data_template set hash='6632e1e0b58a565c135d7ff90440c335' where id=41;");
	db_install_execute("update data_template_rrd set hash='2df25c57022b0c7e7d0be4c035ada1a0' where id=54;");
	db_install_execute("update data_template_rrd set hash='721c0794526d1ac1c359f27dc56faa49' where id=55;");
	db_install_execute("update data_template set hash='1d17325f416b262921a0b55fe5f7e31d' where id=42;");
	db_install_execute("update data_template_rrd set hash='07492e5cace6d74e7db3cb1fc005a3f3' where id=76;");
	db_install_execute("update data_template set hash='d814fa3b79bd0f8933b6e0834d3f16d0' where id=43;");
	db_install_execute("update data_template_rrd set hash='165a0da5f461561c85d092dfe96b9551' where id=92;");
	db_install_execute("update data_template_rrd set hash='0ee6bb54957f6795a5369a29f818d860' where id=78;");
	db_install_execute("update data_template set hash='f6e7d21c19434666bbdac00ccef9932f' where id=44;");
	db_install_execute("update data_template_rrd set hash='9825aaf7c0bdf1554c5b4b86680ac2c0' where id=79;");
	db_install_execute("update data_template set hash='f383db441d1c246cff8482f15e184e5f' where id=45;");
	db_install_execute("update data_template_rrd set hash='50ccbe193c6c7fc29fb9f726cd6c48ee' where id=80;");
	db_install_execute("update data_template set hash='2ef027cc76d75720ee5f7a528f0f1fda' where id=46;");
	db_install_execute("update data_template_rrd set hash='9464c91bcff47f23085ae5adae6ab987' where id=81;");
	db_install_execute("update graph_templates set hash='5deb0d66c81262843dce5f3861be9966' where id=2;");
	db_install_execute("update graph_templates_item set hash='0470b2427dbfadb6b8346e10a71268fa' where id=9;");
	db_install_execute("update graph_templates_item set hash='84a5fe0db518550266309823f994ce9c' where id=10;");
	db_install_execute("update graph_templates_item set hash='2f222f28084085cd06a1f46e4449c793' where id=11;");
	db_install_execute("update graph_templates_item set hash='55acbcc33f46ee6d754e8e81d1b54808' where id=12;");
	db_install_execute("update graph_templates_item set hash='fdaf2321fc890e355711c2bffc07d036' where id=13;");
	db_install_execute("update graph_templates_item set hash='768318f42819217ed81196d2179d3e1b' where id=14;");
	db_install_execute("update graph_templates_item set hash='cb3aa6256dcb3acd50d4517b77a1a5c3' where id=15;");
	db_install_execute("update graph_templates_item set hash='671e989be7cbf12c623b4e79d91c7bed' where id=16;");
	db_install_execute("update graph_template_input set hash='e9d4191277fdfd7d54171f153da57fb0' where id=3;");
	db_install_execute("update graph_template_input set hash='7b361722a11a03238ee8ab7ce44a1037' where id=4;");
	db_install_execute("update graph_templates set hash='abb5e813c9f1e8cd6fc1e393092ef8cb' where id=3;");
	db_install_execute("update graph_templates_item set hash='b561ed15b3ba66d277e6d7c1640b86f7' where id=17;");
	db_install_execute("update graph_templates_item set hash='99ef051057fa6adfa6834a7632e9d8a2' where id=18;");
	db_install_execute("update graph_templates_item set hash='3986695132d3f4716872df4c6fbccb65' where id=19;");
	db_install_execute("update graph_templates_item set hash='0444300017b368e6257f010dca8bbd0d' where id=20;");
	db_install_execute("update graph_templates_item set hash='4d6a0b9063124ca60e2d1702b3e15e41' where id=21;");
	db_install_execute("update graph_templates_item set hash='181b08325e4d00cd50b8cdc8f8ae8e77' where id=22;");
	db_install_execute("update graph_templates_item set hash='bba0a9ff1357c990df50429d64314340' where id=23;");
	db_install_execute("update graph_templates_item set hash='d4a67883d53bc1df8aead21c97c0bc52' where id=24;");
	db_install_execute("update graph_templates_item set hash='253c9ec2d66905245149c1c2dc8e536e' where id=25;");
	db_install_execute("update graph_templates_item set hash='ea9ea883383f4eb462fec6aa309ba7b5' where id=26;");
	db_install_execute("update graph_templates_item set hash='83b746bcaba029eeca170a9f77ec4864' where id=27;");
	db_install_execute("update graph_templates_item set hash='82e01dd92fd37887c0696192efe7af65' where id=28;");
	db_install_execute("update graph_template_input set hash='b33eb27833614056e06ee5952c3e0724' where id=5;");
	db_install_execute("update graph_template_input set hash='ef8799e63ee00e8904bcc4228015784a' where id=6;");
	db_install_execute("update graph_templates set hash='e334bdcf821cd27270a4cc945e80915e' where id=4;");
	db_install_execute("update graph_templates_item set hash='ff0a6125acbb029b814ed1f271ad2d38' where id=29;");
	db_install_execute("update graph_templates_item set hash='f0776f7d6638bba76c2c27f75a424f0f' where id=30;");
	db_install_execute("update graph_templates_item set hash='39f4e021aa3fed9207b5f45a82122b21' where id=31;");
	db_install_execute("update graph_templates_item set hash='800f0b067c06f4ec9c2316711ea83c1e' where id=32;");
	db_install_execute("update graph_templates_item set hash='9419dd5dbf549ba4c5dc1462da6ee321' where id=33;");
	db_install_execute("update graph_templates_item set hash='e461dd263ae47657ea2bf3fd82bec096' where id=34;");
	db_install_execute("update graph_templates_item set hash='f2d1fbb8078a424ffc8a6c9d44d8caa0' where id=35;");
	db_install_execute("update graph_templates_item set hash='e70a5de639df5ba1705b5883da7fccfc' where id=36;");
	db_install_execute("update graph_templates_item set hash='85fefb25ce9fd0317da2706a5463fc42' where id=37;");
	db_install_execute("update graph_templates_item set hash='a1cb26878776999db16f1de7577b3c2a' where id=38;");
	db_install_execute("update graph_templates_item set hash='7d0f9bf64a0898a0095f099674754273' where id=39;");
	db_install_execute("update graph_templates_item set hash='b2879248a522d9679333e1f29e9a87c3' where id=40;");
	db_install_execute("update graph_templates_item set hash='d800aa59eee45383b3d6d35a11cdc864' where id=41;");
	db_install_execute("update graph_templates_item set hash='cab4ae79a546826288e273ca1411c867' where id=42;");
	db_install_execute("update graph_templates_item set hash='d44306ae85622fec971507460be63f5c' where id=43;");
	db_install_execute("update graph_templates_item set hash='aa5c2118035bb83be497d4e099afcc0d' where id=44;");
	db_install_execute("update graph_template_input set hash='4d52e112a836d4c9d451f56602682606' where id=32;");
	db_install_execute("update graph_template_input set hash='f0310b066cc919d2f898b8d1ebf3b518' where id=33;");
	db_install_execute("update graph_template_input set hash='d9eb6b9eb3d7dd44fd14fdefb4096b54' where id=34;");
	db_install_execute("update graph_templates set hash='280e38336d77acde4672879a7db823f3' where id=5;");
	db_install_execute("update graph_templates_item set hash='4aa34ea1b7542b770ace48e8bc395a22' where id=45;");
	db_install_execute("update graph_templates_item set hash='22f118a9d81d0a9c8d922efbbc8a9cc1' where id=46;");
	db_install_execute("update graph_templates_item set hash='229de0c4b490de9d20d8f8d41059f933' where id=47;");
	db_install_execute("update graph_templates_item set hash='cd17feb30c02fd8f21e4d4dcde04e024' where id=48;");
	db_install_execute("update graph_templates_item set hash='8723600cfd0f8a7b3f7dc1361981aabd' where id=49;");
	db_install_execute("update graph_templates_item set hash='cb06be2601b5abfb7a42fc07586de1c2' where id=50;");
	db_install_execute("update graph_templates_item set hash='55a2ee0fd511e5210ed85759171de58f' where id=51;");
	db_install_execute("update graph_templates_item set hash='704459564c84e42462e106eef20db169' where id=52;");
	db_install_execute("update graph_template_input set hash='2662ef4fbb0bf92317ffd42c7515af37' where id=7;");
	db_install_execute("update graph_template_input set hash='a6edef6624c796d3a6055305e2e3d4bf' where id=8;");
	db_install_execute("update graph_template_input set hash='b0e902db1875e392a9d7d69bfbb13515' where id=9;");
	db_install_execute("update graph_template_input set hash='24632b1d4a561e937225d0a5fbe65e41' where id=10;");
	db_install_execute("update graph_templates set hash='3109d88e6806d2ce50c025541b542499' where id=6;");
	db_install_execute("update graph_templates_item set hash='aaebb19ec522497eaaf8c87a631b7919' where id=53;");
	db_install_execute("update graph_templates_item set hash='8b54843ac9d41bce2fcedd023560ed64' where id=54;");
	db_install_execute("update graph_templates_item set hash='05927dc83e07c7d9cffef387d68f35c9' where id=55;");
	db_install_execute("update graph_templates_item set hash='d11e62225a7e7a0cdce89242002ca547' where id=56;");
	db_install_execute("update graph_templates_item set hash='6397b92032486c476b0e13a35b727041' where id=57;");
	db_install_execute("update graph_templates_item set hash='cdfa5f8f82f4c479ff7f6f54160703f6' where id=58;");
	db_install_execute("update graph_templates_item set hash='ce2a309fb9ef64f83f471895069a6f07' where id=59;");
	db_install_execute("update graph_templates_item set hash='9cbfbf57ebde435b27887f27c7d3caea' where id=60;");
	db_install_execute("update graph_template_input set hash='6d078f1d58b70ad154a89eb80fe6ab75' where id=11;");
	db_install_execute("update graph_template_input set hash='878241872dd81c68d78e6ff94871d97d' where id=12;");
	db_install_execute("update graph_template_input set hash='f8fcdc3a3f0e8ead33bd9751895a3462' where id=13;");
	db_install_execute("update graph_template_input set hash='394ab4713a34198dddb5175aa40a2b4a' where id=14;");
	db_install_execute("update graph_templates set hash='cf96dfb22b58e08bf101ca825377fa4b' where id=7;");
	db_install_execute("update graph_templates_item set hash='80e0aa956f50c261e5143273da58b8a3' where id=61;");
	db_install_execute("update graph_templates_item set hash='48fdcae893a7b7496e1a61efc3453599' where id=62;");
	db_install_execute("update graph_templates_item set hash='22f43e5fa20f2716666ba9ed9a7d1727' where id=63;");
	db_install_execute("update graph_templates_item set hash='3e86d497bcded7af7ab8408e4908e0d8' where id=64;");
	db_install_execute("update graph_template_input set hash='433f328369f9569446ddc59555a63eb8' where id=15;");
	db_install_execute("update graph_template_input set hash='a1a91c1514c65152d8cb73522ea9d4e6' where id=16;");
	db_install_execute("update graph_template_input set hash='2fb4deb1448379b27ddc64e30e70dc42' where id=17;");
	db_install_execute("update graph_templates set hash='9fe8b4da353689d376b99b2ea526cc6b' where id=8;");
	db_install_execute("update graph_templates_item set hash='ba00ecd28b9774348322ff70a96f2826' where id=65;");
	db_install_execute("update graph_templates_item set hash='8d76de808efd73c51e9a9cbd70579512' where id=66;");
	db_install_execute("update graph_templates_item set hash='304244ca63d5b09e62a94c8ec6fbda8d' where id=67;");
	db_install_execute("update graph_templates_item set hash='da1ba71a93d2ed4a2a00d54592b14157' where id=68;");
	db_install_execute("update graph_template_input set hash='592cedd465877bc61ab549df688b0b2a' where id=18;");
	db_install_execute("update graph_template_input set hash='1d51dbabb200fcea5c4b157129a75410' where id=19;");
	db_install_execute("update graph_templates set hash='fe5edd777a76d48fc48c11aded5211ef' where id=9;");
	db_install_execute("update graph_templates_item set hash='93ad2f2803b5edace85d86896620b9da' where id=69;");
	db_install_execute("update graph_templates_item set hash='e28736bf63d3a3bda03ea9f1e6ecb0f1' where id=70;");
	db_install_execute("update graph_templates_item set hash='bbdfa13adc00398eed132b1ccb4337d2' where id=71;");
	db_install_execute("update graph_templates_item set hash='2c14062c7d67712f16adde06132675d6' where id=72;");
	db_install_execute("update graph_templates_item set hash='9cf6ed48a6a54b9644a1de8c9929bd4e' where id=73;");
	db_install_execute("update graph_templates_item set hash='c9824064305b797f38feaeed2352e0e5' where id=74;");
	db_install_execute("update graph_templates_item set hash='fa1bc4eff128c4da70f5247d55b8a444' where id=75;");
	db_install_execute("update graph_template_input set hash='8cb8ed3378abec21a1819ea52dfee6a3' where id=20;");
	db_install_execute("update graph_template_input set hash='5dfcaf9fd771deb8c5430bce1562e371' where id=21;");
	db_install_execute("update graph_template_input set hash='6f3cc610315ee58bc8e0b1f272466324' where id=22;");
	db_install_execute("update graph_templates set hash='63610139d44d52b195cc375636653ebd' where id=10;");
	db_install_execute("update graph_templates_item set hash='5c94ac24bc0d6d2712cc028fa7d4c7d2' where id=76;");
	db_install_execute("update graph_templates_item set hash='8bc7f905526f62df7d5c2d8c27c143c1' where id=77;");
	db_install_execute("update graph_templates_item set hash='cd074cd2b920aab70d480c020276d45b' where id=78;");
	db_install_execute("update graph_templates_item set hash='415630f25f5384ba0c82adbdb05fe98b' where id=79;");
	db_install_execute("update graph_template_input set hash='b457a982bf46c6760e6ef5f5d06d41fb' where id=23;");
	db_install_execute("update graph_template_input set hash='bd4a57adf93c884815b25a8036b67f98' where id=24;");
	db_install_execute("update graph_templates set hash='5107ec0206562e77d965ce6b852ef9d4' where id=11;");
	db_install_execute("update graph_templates_item set hash='d77d2050be357ab067666a9485426e6b' where id=80;");
	db_install_execute("update graph_templates_item set hash='13d22f5a0eac6d97bf6c97d7966f0a00' where id=81;");
	db_install_execute("update graph_templates_item set hash='8580230d31d2851ec667c296a665cbf9' where id=82;");
	db_install_execute("update graph_templates_item set hash='b5b7d9b64e7640aa51dbf58c69b86d15' where id=83;");
	db_install_execute("update graph_templates_item set hash='2ec10edf4bfaa866b7efd544d4c3f446' where id=84;");
	db_install_execute("update graph_templates_item set hash='b65666f0506c0c70966f493c19607b93' where id=85;");
	db_install_execute("update graph_templates_item set hash='6c73575c74506cfc75b89c4276ef3455' where id=86;");
	db_install_execute("update graph_template_input set hash='d7cdb63500c576e0f9f354de42c6cf3a' where id=25;");
	db_install_execute("update graph_template_input set hash='a23152f5ec02e7762ca27608c0d89f6c' where id=26;");
	db_install_execute("update graph_template_input set hash='2cc5d1818da577fba15115aa18f64d85' where id=27;");
	db_install_execute("update graph_templates set hash='6992ed4df4b44f3d5595386b8298f0ec' where id=12;");
	db_install_execute("update graph_templates_item set hash='5fa7c2317f19440b757ab2ea1cae6abc' where id=95;");
	db_install_execute("update graph_templates_item set hash='b1d18060bfd3f68e812c508ff4ac94ed' where id=96;");
	db_install_execute("update graph_templates_item set hash='780b6f0850aaf9431d1c246c55143061' where id=97;");
	db_install_execute("update graph_templates_item set hash='2d54a7e7bb45e6c52d97a09e24b7fba7' where id=98;");
	db_install_execute("update graph_templates_item set hash='40206367a3c192b836539f49801a0b15' where id=99;");
	db_install_execute("update graph_templates_item set hash='7ee72e2bb3722d4f8a7f9c564e0dd0d0' where id=100;");
	db_install_execute("update graph_templates_item set hash='c8af33b949e8f47133ee25e63c91d4d0' where id=101;");
	db_install_execute("update graph_templates_item set hash='568128a16723d1195ce6a234d353ce00' where id=102;");
	db_install_execute("update graph_template_input set hash='6273c71cdb7ed4ac525cdbcf6180918c' where id=30;");
	db_install_execute("update graph_template_input set hash='5e62dbea1db699f1bda04c5863e7864d' where id=31;");
	db_install_execute("update graph_templates set hash='be275639d5680e94c72c0ebb4e19056d' where id=13;");
	db_install_execute("update graph_templates_item set hash='7517a40d478e28ed88ba2b2a65e16b57' where id=103;");
	db_install_execute("update graph_templates_item set hash='df0c8b353d26c334cb909dc6243957c5' where id=104;");
	db_install_execute("update graph_templates_item set hash='c41a4cf6fefaf756a24f0a9510580724' where id=105;");
	db_install_execute("update graph_templates_item set hash='9efa8f01c6ed11364a21710ff170f422' where id=106;");
	db_install_execute("update graph_templates_item set hash='95d6e4e5110b456f34324f7941d08318' where id=107;");
	db_install_execute("update graph_templates_item set hash='0c631bfc0785a9cca68489ea87a6c3da' where id=108;");
	db_install_execute("update graph_templates_item set hash='3468579d3b671dfb788696df7dcc1ec9' where id=109;");
	db_install_execute("update graph_templates_item set hash='c3ddfdaa65449f99b7f1a735307f9abe' where id=110;");
	db_install_execute("update graph_template_input set hash='f45def7cad112b450667aa67262258cb' where id=35;");
	db_install_execute("update graph_template_input set hash='f8c361a8c8b7ad80e8be03ba7ea5d0d6' where id=36;");
	db_install_execute("update graph_templates set hash='f17e4a77b8496725dc924b8c35b60036' where id=14;");
	db_install_execute("update graph_templates_item set hash='4c64d5c1ce8b5d8b94129c23b46a5fd6' where id=111;");
	db_install_execute("update graph_templates_item set hash='5c1845c9bd1af684a3c0ad843df69e3e' where id=112;");
	db_install_execute("update graph_templates_item set hash='e5169563f3f361701902a8da3ac0c77f' where id=113;");
	db_install_execute("update graph_templates_item set hash='35e87262efa521edbb1fd27f09c036f5' where id=114;");
	db_install_execute("update graph_templates_item set hash='53069d7dba4c31b338f609bea4cd16f3' where id=115;");
	db_install_execute("update graph_templates_item set hash='d9c102579839c5575806334d342b50de' where id=116;");
	db_install_execute("update graph_templates_item set hash='dc1897c3249dbabe269af49cee92f8c0' where id=117;");
	db_install_execute("update graph_templates_item set hash='ccd21fe0b5a8c24057f1eff4a6b66391' where id=118;");
	db_install_execute("update graph_template_input set hash='03d11dce695963be30bd744bd6cbac69' where id=37;");
	db_install_execute("update graph_template_input set hash='9cbc515234779af4bf6cdf71a81c556a' where id=38;");
	db_install_execute("update graph_templates set hash='46bb77f4c0c69671980e3c60d3f22fa9' where id=15;");
	db_install_execute("update graph_templates_item set hash='ab09d41c358f6b8a9d0cad4eccc25529' where id=119;");
	db_install_execute("update graph_templates_item set hash='5d5b8d8fbe751dc9c86ee86f85d7433b' where id=120;");
	db_install_execute("update graph_templates_item set hash='4822a98464c6da2afff10c6d12df1831' where id=121;");
	db_install_execute("update graph_templates_item set hash='fc6fbf2a964bea0b3c88ed0f18616aa7' where id=122;");
	db_install_execute("update graph_template_input set hash='2c4d561ee8132a8dda6de1104336a6ec' where id=39;");
	db_install_execute("update graph_template_input set hash='31fed1f9e139d4897d0460b10fb7be94' where id=44;");
	db_install_execute("update graph_templates set hash='8e77a3036312fd0fda32eaea2b5f141b' where id=16;");
	db_install_execute("update graph_templates_item set hash='e4094625d5443b4c87f9a87ba616a469' where id=123;");
	db_install_execute("update graph_templates_item set hash='ae68425cd10e8a6623076b2e6859a6aa' where id=124;");
	db_install_execute("update graph_templates_item set hash='40b8e14c6568b3f6be6a5d89d6a9f061' where id=125;");
	db_install_execute("update graph_templates_item set hash='4afbdc3851c03e206672930746b1a5e2' where id=126;");
	db_install_execute("update graph_templates_item set hash='ea47d2b5516e334bc5f6ce1698a3ae76' where id=127;");
	db_install_execute("update graph_templates_item set hash='899c48a2f79ea3ad4629aff130d0f371' where id=128;");
	db_install_execute("update graph_templates_item set hash='ab474d7da77e9ec1f6a1d45c602580cd' where id=129;");
	db_install_execute("update graph_templates_item set hash='e143f8b4c6d4eeb6a28b052e6b8ce5a9' where id=130;");
	db_install_execute("update graph_template_input set hash='6e1cf7addc0cc419aa903552e3eedbea' where id=40;");
	db_install_execute("update graph_template_input set hash='7ea2aa0656f7064d25a36135dd0e9082' where id=41;");
	db_install_execute("update graph_templates set hash='5892c822b1bb2d38589b6c27934b9936' where id=17;");
	db_install_execute("update graph_templates_item set hash='facfeeb6fc2255ba2985b2d2f695d78a' where id=131;");
	db_install_execute("update graph_templates_item set hash='2470e43034a5560260d79084432ed14f' where id=132;");
	db_install_execute("update graph_templates_item set hash='e9e645f07bde92b52d93a7a1f65efb30' where id=133;");
	db_install_execute("update graph_templates_item set hash='bdfe0d66103211cfdaa267a44a98b092' where id=134;");
	db_install_execute("update graph_template_input set hash='63480bca78a38435f24a5b5d5ed050d7' where id=42;");
	db_install_execute("update graph_templates set hash='9a5e6d7781cc1bd6cf24f64dd6ffb423' where id=18;");
	db_install_execute("update graph_templates_item set hash='098b10c13a5701ddb7d4d1d2e2b0fdb7' where id=139;");
	db_install_execute("update graph_templates_item set hash='1dbda412a9926b0ee5c025aa08f3b230' where id=140;");
	db_install_execute("update graph_templates_item set hash='725c45917146807b6a4257fc351f2bae' where id=141;");
	db_install_execute("update graph_templates_item set hash='4e336fdfeb84ce65f81ded0e0159a5e0' where id=142;");
	db_install_execute("update graph_template_input set hash='bb9d83a02261583bc1f92d9e66ea705d' where id=45;");
	db_install_execute("update graph_template_input set hash='51196222ed37b44236d9958116028980' where id=46;");
	db_install_execute("update graph_templates set hash='0dd0438d5e6cad6776f79ecaa96fb708' where id=19;");
	db_install_execute("update graph_templates_item set hash='7dab7a3ceae2addd1cebddee6c483e7c' where id=143;");
	db_install_execute("update graph_templates_item set hash='aea239f3ceea8c63d02e453e536190b8' where id=144;");
	db_install_execute("update graph_templates_item set hash='a0efae92968a6d4ae099b676e0f1430e' where id=145;");
	db_install_execute("update graph_templates_item set hash='4fd5ba88be16e3d513c9231b78ccf0e1' where id=146;");
	db_install_execute("update graph_templates_item set hash='d2e98e51189e1d9be8888c3d5c5a4029' where id=147;");
	db_install_execute("update graph_templates_item set hash='12829294ee3958f4a31a58a61228e027' where id=148;");
	db_install_execute("update graph_templates_item set hash='4b7e8755b0f2253723c1e9fb21fd37b1' where id=149;");
	db_install_execute("update graph_templates_item set hash='cbb19ffd7a0ead2bf61512e86d51ee8e' where id=150;");
	db_install_execute("update graph_templates_item set hash='37b4cbed68f9b77e49149343069843b4' where id=151;");
	db_install_execute("update graph_templates_item set hash='5eb7532200f2b5cc93e13743a7db027c' where id=152;");
	db_install_execute("update graph_templates_item set hash='b0f9f602fbeaaff090ea3f930b46c1c7' where id=153;");
	db_install_execute("update graph_templates_item set hash='06477f7ea46c63272cee7253e7cd8760' where id=154;");
	db_install_execute("update graph_template_input set hash='fd26b0f437b75715d6dff983e7efa710' where id=47;");
	db_install_execute("update graph_template_input set hash='a463dd46862605c90ea60ccad74188db' where id=48;");
	db_install_execute("update graph_template_input set hash='9977dd7a41bcf0f0c02872b442c7492e' where id=49;");
	db_install_execute("update graph_templates set hash='b18a3742ebea48c6198412b392d757fc' where id=20;");
	db_install_execute("update graph_templates_item set hash='6877a2a5362a9390565758b08b9b37f7' where id=159;");
	db_install_execute("update graph_templates_item set hash='a978834f3d02d833d3d2def243503bf2' where id=160;");
	db_install_execute("update graph_templates_item set hash='7422d87bc82de20a4333bd2f6460b2d4' where id=161;");
	db_install_execute("update graph_templates_item set hash='4d52762859a3fec297ebda0e7fd760d9' where id=162;");
	db_install_execute("update graph_templates_item set hash='999d4ed1128ff03edf8ea47e56d361dd' where id=163;");
	db_install_execute("update graph_templates_item set hash='3dfcd7f8c7a760ac89d34398af79b979' where id=164;");
	db_install_execute("update graph_templates_item set hash='217be75e28505c8f8148dec6b71b9b63' where id=165;");
	db_install_execute("update graph_templates_item set hash='69b89e1c5d6fc6182c93285b967f970a' where id=166;");
	db_install_execute("update graph_template_input set hash='a7a69bbdf6890d6e6eaa7de16e815ec6' where id=51;");
	db_install_execute("update graph_template_input set hash='0072b613a33f1fae5ce3e5903dec8fdb' where id=52;");
	db_install_execute("update graph_templates set hash='8e7c8a511652fe4a8e65c69f3d34779d' where id=21;");
	db_install_execute("update graph_templates_item set hash='a751838f87068e073b95be9555c57bde' where id=171;");
	db_install_execute("update graph_templates_item set hash='3b13eb2e542fe006c9bf86947a6854fa' where id=170;");
	db_install_execute("update graph_templates_item set hash='8ef3e7fb7ce962183f489725939ea40f' where id=169;");
	db_install_execute("update graph_templates_item set hash='6ca2161c37b0118786dbdb46ad767e5d' where id=167;");
	db_install_execute("update graph_templates_item set hash='5d6dff9c14c71dc1ebf83e87f1c25695' where id=172;");
	db_install_execute("update graph_templates_item set hash='b27cb9a158187d29d17abddc6fdf0f15' where id=173;");
	db_install_execute("update graph_templates_item set hash='6c0555013bb9b964e51d22f108dae9b0' where id=174;");
	db_install_execute("update graph_templates_item set hash='42ce58ec17ef5199145fbf9c6ee39869' where id=175;");
	db_install_execute("update graph_templates_item set hash='9bdff98f2394f666deea028cbca685f3' where id=176;");
	db_install_execute("update graph_templates_item set hash='fb831fefcf602bc31d9d24e8e456c2e6' where id=177;");
	db_install_execute("update graph_templates_item set hash='5a958d56785a606c08200ef8dbf8deef' where id=178;");
	db_install_execute("update graph_templates_item set hash='5ce67a658cec37f526dc84ac9e08d6e7' where id=179;");
	db_install_execute("update graph_template_input set hash='940beb0f0344e37f4c6cdfc17d2060bc' where id=53;");
	db_install_execute("update graph_template_input set hash='7b0674dd447a9badf0d11bec688028a8' where id=54;");
	db_install_execute("update graph_templates set hash='06621cd4a9289417cadcb8f9b5cfba80' where id=22;");
	db_install_execute("update graph_templates_item set hash='7e04a041721df1f8828381a9ea2f2154' where id=180;");
	db_install_execute("update graph_templates_item set hash='afc8bca6b1b3030a6d71818272336c6c' where id=181;");
	db_install_execute("update graph_templates_item set hash='6ac169785f5aeaf1cc5cdfd38dfcfb6c' where id=182;");
	db_install_execute("update graph_templates_item set hash='178c0a0ce001d36a663ff6f213c07505' where id=183;");
	db_install_execute("update graph_templates_item set hash='8e3268c0abde7550616bff719f10ee2f' where id=184;");
	db_install_execute("update graph_templates_item set hash='18891392b149de63b62c4258a68d75f8' where id=185;");
	db_install_execute("update graph_templates_item set hash='dfc9d23de0182c9967ae3dabdfa55a16' where id=186;");
	db_install_execute("update graph_templates_item set hash='c47ba64e2e5ea8bf84aceec644513176' where id=187;");
	db_install_execute("update graph_templates_item set hash='617d10dff9bbc3edd9d733d9c254da76' where id=204;");
	db_install_execute("update graph_templates_item set hash='9269a66502c34d00ac3c8b1fcc329ac6' where id=205;");
	db_install_execute("update graph_templates_item set hash='d45deed7e1ad8350f3b46b537ae0a933' where id=206;");
	db_install_execute("update graph_templates_item set hash='2f64cf47dc156e8c800ae03c3b893e3c' where id=207;");
	db_install_execute("update graph_templates_item set hash='57434bef8cb21283c1a73f055b0ada19' where id=208;");
	db_install_execute("update graph_templates_item set hash='660a1b9365ccbba356fd142faaec9f04' where id=209;");
	db_install_execute("update graph_templates_item set hash='28c5297bdaedcca29acf245ef4bbed9e' where id=210;");
	db_install_execute("update graph_templates_item set hash='99098604fd0c78fd7dabac8f40f1fb29' where id=211;");
	db_install_execute("update graph_template_input set hash='fa83cd3a3b4271b644cb6459ea8c35dc' where id=55;");
	db_install_execute("update graph_template_input set hash='7946e8ee1e38a65462b85e31a15e35e5' where id=56;");
	db_install_execute("update graph_template_input set hash='e5acdd5368137c408d56ecf55b0e077c' where id=61;");
	db_install_execute("update graph_template_input set hash='a028e586e5fae667127c655fe0ac67f0' where id=62;");
	db_install_execute("update graph_templates set hash='e0d1625a1f4776a5294583659d5cee15' where id=23;");
	db_install_execute("update graph_templates_item set hash='9d052e7d632c479737fbfaced0821f79' where id=188;");
	db_install_execute("update graph_templates_item set hash='9b9fa6268571b6a04fa4411d8e08c730' where id=189;");
	db_install_execute("update graph_templates_item set hash='8e8f2fbeb624029cbda1d2a6ddd991ba' where id=190;");
	db_install_execute("update graph_templates_item set hash='c76495beb1ed01f0799838eb8a893124' where id=191;");
	db_install_execute("update graph_templates_item set hash='d4e5f253f01c3ea77182c5a46418fc44' where id=192;");
	db_install_execute("update graph_templates_item set hash='526a96add143da021c5f00d8764a6c12' where id=193;");
	db_install_execute("update graph_templates_item set hash='81eeb46f451212f00fd7caee42a81c0b' where id=194;");
	db_install_execute("update graph_templates_item set hash='089e4d1c3faeb00fd5dcc9622b06d656' where id=195;");
	db_install_execute("update graph_template_input set hash='00ae916640272f5aca54d73ae34c326b' where id=57;");
	db_install_execute("update graph_template_input set hash='1bc1652f82488ebfb7242c65d2ffa9c7' where id=58;");
	db_install_execute("update graph_templates set hash='10ca5530554da7b73dc69d291bf55d38' where id=24;");
	db_install_execute("update graph_templates_item set hash='fe66cb973966d22250de073405664200' where id=196;");
	db_install_execute("update graph_templates_item set hash='1ba3fc3466ad32fdd2669cac6cad6faa' where id=197;");
	db_install_execute("update graph_templates_item set hash='f810154d3a934c723c21659e66199cdf' where id=198;");
	db_install_execute("update graph_templates_item set hash='98a161df359b01304346657ff1a9d787' where id=199;");
	db_install_execute("update graph_templates_item set hash='d5e55eaf617ad1f0516f6343b3f07c5e' where id=200;");
	db_install_execute("update graph_templates_item set hash='9fde6b8c84089b9f9044e681162e7567' where id=201;");
	db_install_execute("update graph_templates_item set hash='9a3510727c3d9fa7e2e7a015783a99b3' where id=202;");
	db_install_execute("update graph_templates_item set hash='451afd23f2cb59ab9b975fd6e2735815' where id=203;");
	db_install_execute("update graph_template_input set hash='e3177d0e56278de320db203f32fb803d' where id=59;");
	db_install_execute("update graph_template_input set hash='4f20fba2839764707f1c3373648c5fef' where id=60;");
	db_install_execute("update graph_templates set hash='df244b337547b434b486662c3c5c7472' where id=25;");
	db_install_execute("update graph_templates_item set hash='de3eefd6d6c58afabdabcaf6c0168378' where id=212;");
	db_install_execute("update graph_templates_item set hash='1a80fa108f5c46eecb03090c65bc9a12' where id=213;");
	db_install_execute("update graph_templates_item set hash='fe458892e7faa9d232e343d911e845f3' where id=214;");
	db_install_execute("update graph_templates_item set hash='175c0a68689bebc38aad2fbc271047b3' where id=215;");
	db_install_execute("update graph_templates_item set hash='1bf2283106510491ddf3b9c1376c0b31' where id=216;");
	db_install_execute("update graph_templates_item set hash='c5202f1690ffe45600c0d31a4a804f67' where id=217;");
	db_install_execute("update graph_templates_item set hash='eb9794e3fdafc2b74f0819269569ed40' where id=218;");
	db_install_execute("update graph_templates_item set hash='6bcedd61e3ccf7518ca431940c93c439' where id=219;");
	db_install_execute("update graph_template_input set hash='2764a4f142ba9fd95872106a1b43541e' where id=63;");
	db_install_execute("update graph_template_input set hash='f73f7ddc1f4349356908122093dbfca2' where id=64;");
	db_install_execute("update graph_templates set hash='7489e44466abee8a7d8636cb2cb14a1a' where id=26;");
	db_install_execute("update graph_templates_item set hash='b7b381d47972f836785d338a3bef6661' where id=303;");
	db_install_execute("update graph_templates_item set hash='36fa8063df3b07cece878d54443db727' where id=304;");
	db_install_execute("update graph_templates_item set hash='2c35b5cae64c5f146a55fcb416dd14b5' where id=305;");
	db_install_execute("update graph_templates_item set hash='16d6a9a7f608762ad65b0841e5ef4e9c' where id=306;");
	db_install_execute("update graph_templates_item set hash='d80e4a4901ab86ee39c9cc613e13532f' where id=307;");
	db_install_execute("update graph_templates_item set hash='567c2214ee4753aa712c3d101ea49a5d' where id=308;");
	db_install_execute("update graph_templates_item set hash='ba0b6a9e316ef9be66abba68b80f7587' where id=309;");
	db_install_execute("update graph_templates_item set hash='4b8e4a6bf2757f04c3e3a088338a2f7a' where id=310;");
	db_install_execute("update graph_template_input set hash='86bd8819d830a81d64267761e1fd8ec4' where id=65;");
	db_install_execute("update graph_template_input set hash='6c8967850102202de166951e4411d426' where id=66;");
	db_install_execute("update graph_templates set hash='c6bb62bedec4ab97f9db9fd780bd85a6' where id=27;");
	db_install_execute("update graph_templates_item set hash='8536e034ab5268a61473f1ff2f6bd88f' where id=317;");
	db_install_execute("update graph_templates_item set hash='d478a76de1df9edf896c9ce51506c483' where id=316;");
	db_install_execute("update graph_templates_item set hash='42537599b5fb8ea852240b58a58633de' where id=315;");
	db_install_execute("update graph_templates_item set hash='87e10f9942b625aa323a0f39b60058e7' where id=318;");
	db_install_execute("update graph_template_input set hash='bdad718851a52b82eca0a310b0238450' where id=67;");
	db_install_execute("update graph_template_input set hash='e7b578e12eb8a82627557b955fd6ebd4' where id=68;");
	db_install_execute("update graph_templates set hash='e8462bbe094e4e9e814d4e681671ea82' where id=28;");
	db_install_execute("update graph_templates_item set hash='38f6891b0db92aa8950b4ce7ae902741' where id=319;");
	db_install_execute("update graph_templates_item set hash='af13152956a20aa894ef4a4067b88f63' where id=320;");
	db_install_execute("update graph_templates_item set hash='1b2388bbede4459930c57dc93645284e' where id=321;");
	db_install_execute("update graph_templates_item set hash='6407dc226db1d03be9730f4d6f3eeccf' where id=322;");
	db_install_execute("update graph_template_input set hash='37d09fb7ce88ecec914728bdb20027f3' where id=69;");
	db_install_execute("update graph_template_input set hash='699bd7eff7ba0c3520db3692103a053d' where id=70;");
	db_install_execute("update graph_templates set hash='62205afbd4066e5c4700338841e3901e' where id=29;");
	db_install_execute("update graph_templates_item set hash='fca6a530c8f37476b9004a90b42ee988' where id=323;");
	db_install_execute("update graph_templates_item set hash='5acebbde3dc65e02f8fda03955852fbe' where id=324;");
	db_install_execute("update graph_templates_item set hash='311079ffffac75efaab2837df8123122' where id=325;");
	db_install_execute("update graph_templates_item set hash='724d27007ebf31016cfa5530fee1b867' where id=326;");
	db_install_execute("update graph_template_input set hash='df905e159d13a5abed8a8a7710468831' where id=71;");
	db_install_execute("update graph_template_input set hash='8ca9e3c65c080dbf74a59338d64b0c14' where id=72;");
	db_install_execute("update graph_templates set hash='e3780a13b0f7a3f85a44b70cd4d2fd36' where id=30;");
	db_install_execute("update graph_templates_item set hash='5258970186e4407ed31cca2782650c45' where id=360;");
	db_install_execute("update graph_templates_item set hash='da26dd92666cb840f8a70e2ec5e90c07' where id=359;");
	db_install_execute("update graph_templates_item set hash='803b96bcaec33148901b4b562d9d2344' where id=358;");
	db_install_execute("update graph_templates_item set hash='7d08b996bde9cdc7efa650c7031137b4' where id=361;");
	db_install_execute("update graph_template_input set hash='69ad68fc53af03565aef501ed5f04744' where id=73;");
	db_install_execute("update graph_templates set hash='1742b2066384637022d178cc5072905a' where id=31;");
	db_install_execute("update graph_templates_item set hash='918e6e7d41bb4bae0ea2937b461742a4' where id=362;");
	db_install_execute("update graph_templates_item set hash='f19fbd06c989ea85acd6b4f926e4a456' where id=363;");
	db_install_execute("update graph_templates_item set hash='fc150a15e20c57e11e8d05feca557ef9' where id=364;");
	db_install_execute("update graph_templates_item set hash='ccbd86e03ccf07483b4d29e63612fb18' where id=365;");
	db_install_execute("update graph_templates_item set hash='964c5c30cd05eaf5a49c0377d173de86' where id=366;");
	db_install_execute("update graph_templates_item set hash='b1a6fb775cf62e79e1c4bc4933c7e4ce' where id=367;");
	db_install_execute("update graph_templates_item set hash='721038182a872ab266b5cf1bf7f7755c' where id=368;");
	db_install_execute("update graph_templates_item set hash='2302f80c2c70b897d12182a1fc11ecd6' where id=369;");
	db_install_execute("update graph_templates_item set hash='4ffc7af8533d103748316752b70f8e3c' where id=370;");
	db_install_execute("update graph_templates_item set hash='64527c4b6eeeaf627acc5117ff2180fd' where id=371;");
	db_install_execute("update graph_templates_item set hash='d5bbcbdbf83ae858862611ac6de8fc62' where id=372;");
	db_install_execute("update graph_template_input set hash='562726cccdb67d5c6941e9e826ef4ef5' where id=74;");
	db_install_execute("update graph_template_input set hash='82426afec226f8189c8928e7f083f80f' where id=75;");
	db_install_execute("update graph_templates set hash='13b47e10b2d5db45707d61851f69c52b' where id=32;");
	db_install_execute("update graph_templates_item set hash='0e715933830112c23c15f7e3463f77b6' where id=380;");
	db_install_execute("update graph_templates_item set hash='979fff9d691ca35e3f4b3383d9cae43f' where id=379;");
	db_install_execute("update graph_templates_item set hash='5bff63207c7bf076d76ff3036b5dad54' where id=378;");
	db_install_execute("update graph_templates_item set hash='4a381a8e87d4db1ac99cf8d9078266d3' where id=377;");
	db_install_execute("update graph_templates_item set hash='88d3094d5dc2164cbf2f974aeb92f051' where id=376;");
	db_install_execute("update graph_templates_item set hash='54782f71929e7d1734ed5ad4b8dda50d' where id=375;");
	db_install_execute("update graph_templates_item set hash='55083351cd728b82cc4dde68eb935700' where id=374;");
	db_install_execute("update graph_templates_item set hash='1995d8c23e7d8e1efa2b2c55daf3c5a7' where id=373;");
	db_install_execute("update graph_templates_item set hash='db7c15d253ca666601b3296f2574edc9' where id=384;");
	db_install_execute("update graph_templates_item set hash='5b43e4102600ad75379c5afd235099c4' where id=383;");
	db_install_execute("update graph_template_input set hash='f28013abf8e5813870df0f4111a5e695' where id=77;");
	db_install_execute("update graph_template_input set hash='69a23877302e7d142f254b208c58b596' where id=76;");
	db_install_execute("update graph_templates set hash='8ad6790c22b693680e041f21d62537ac' where id=33;");
	db_install_execute("update graph_templates_item set hash='fdaec5b9227522c758ad55882c483a83' where id=385;");
	db_install_execute("update graph_templates_item set hash='6824d29c3f13fe1e849f1dbb8377d3f1' where id=386;");
	db_install_execute("update graph_templates_item set hash='54e3971b3dd751dd2509f62721c12b41' where id=387;");
	db_install_execute("update graph_templates_item set hash='cf8c9f69878f0f595d583eac109a9be1' where id=388;");
	db_install_execute("update graph_templates_item set hash='de265acbbfa99eb4b3e9f7e90c7feeda' where id=389;");
	db_install_execute("update graph_templates_item set hash='777aa88fb0a79b60d081e0e3759f1cf7' where id=390;");
	db_install_execute("update graph_templates_item set hash='66bfdb701c8eeadffe55e926d6e77e71' where id=391;");
	db_install_execute("update graph_templates_item set hash='3ff8dba1ca6279692b3fcabed0bc2631' where id=392;");
	db_install_execute("update graph_templates_item set hash='d6041d14f9c8fb9b7ddcf3556f763c03' where id=393;");
	db_install_execute("update graph_templates_item set hash='76ae747365553a02313a2d8a0dd55c8a' where id=394;");
	db_install_execute("update graph_template_input set hash='8644b933b6a09dde6c32ff24655eeb9a' where id=78;");
	db_install_execute("update graph_template_input set hash='49c4b4800f3e638a6f6bb681919aea80' where id=79;");
	db_install_execute("update snmp_query set hash='d75e406fdeca4fcef45b8be3a9a63cbc' where id=1;");
	db_install_execute("update snmp_query_graph set hash='a4b829746fb45e35e10474c36c69c0cf' where id=2;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='6537b3209e0697fbec278e94e7317b52' where id=49;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='6d3f612051016f48c951af8901720a1c' where id=50;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='62bc981690576d0b2bd0041ec2e4aa6f' where id=51;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='adb270d55ba521d205eac6a21478804a' where id=52;");
	db_install_execute("update snmp_query_graph_sv set hash='299d3434851fc0d5c0e105429069709d' where id=21;");
	db_install_execute("update snmp_query_graph_sv set hash='8c8860b17fd67a9a500b4cb8b5e19d4b' where id=22;");
	db_install_execute("update snmp_query_graph_sv set hash='d96360ae5094e5732e7e7496ceceb636' where id=23;");
	db_install_execute("update snmp_query_graph set hash='01e33224f8b15997d3d09d6b1bf83e18' where id=3;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='77065435f3bbb2ff99bc3b43b81de8fe' where id=54;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='240d8893092619c97a54265e8d0b86a1' where id=55;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='4b200ecf445bdeb4c84975b74991df34' where id=56;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='d6da3887646078e4d01fe60a123c2179' where id=57;");
	db_install_execute("update snmp_query_graph_sv set hash='750a290cadc3dc60bb682a5c5f47df16' where id=24;");
	db_install_execute("update snmp_query_graph_sv set hash='bde195eecc256c42ca9725f1f22c1dc0' where id=25;");
	db_install_execute("update snmp_query_graph_sv set hash='d9e97d22689e4ffddaca23b46f2aa306' where id=26;");
	db_install_execute("update snmp_query_graph set hash='1e6edee3115c42d644dbd014f0577066' where id=4;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='ce7769b97d80ca31d21f83dc18ba93c2' where id=59;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='1ee1f9717f3f4771f7f823ca5a8b83dd' where id=60;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='a7dbd54604533b592d4fae6e67587e32' where id=61;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='b148fa7199edcf06cd71c89e5c5d7b63' where id=62;");
	db_install_execute("update snmp_query_graph_sv set hash='48ceaba62e0c2671a810a7f1adc5f751' where id=27;");
	db_install_execute("update snmp_query_graph_sv set hash='d6258884bed44abe46d264198adc7c5d' where id=28;");
	db_install_execute("update snmp_query_graph_sv set hash='6eb58d9835b2b86222306d6ced9961d9' where id=29;");
	db_install_execute("update snmp_query_graph set hash='ab93b588c29731ab15db601ca0bc9dec' where id=9;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='e1be83d708ed3c0b8715ccb6517a0365' where id=88;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='c582d3b37f19e4a703d9bf4908dc6548' where id=86;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='57a9ae1f197498ca8dcde90194f61cbc' where id=89;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='0110e120981c7ff15304e4a85cb42cbe' where id=90;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='ce0b9c92a15759d3ddbd7161d26a98b7' where id=91;");
	db_install_execute("update snmp_query_graph_sv set hash='0a5eb36e98c04ad6be8e1ef66caeed3c' where id=34;");
	db_install_execute("update snmp_query_graph_sv set hash='4c4386a96e6057b7bd0b78095209ddfa' where id=35;");
	db_install_execute("update snmp_query_graph_sv set hash='fd3a384768b0388fa64119fe2f0cc113' where id=36;");
	db_install_execute("update snmp_query_graph set hash='ae34f5f385bed8c81a158bf3030f1089' where id=13;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='7e093c535fa3d810fa76fc3d8c80c94b' where id=75;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='084efd82bbddb69fb2ac9bd0b0f16ac6' where id=74;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='14aa2dead86bbad0f992f1514722c95e' where id=72;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='70390712158c3c5052a7d830fb456489' where id=73;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='87a659326af8c75158e5142874fd74b0' where id=70;");
	db_install_execute("update snmp_query_graph_sv set hash='49dca5592ac26ff149a4fbd18d690644' where id=15;");
	db_install_execute("update snmp_query_graph_sv set hash='bda15298139ad22bdc8a3b0952d4e3ab' where id=16;");
	db_install_execute("update snmp_query_graph_sv set hash='29e48483d0471fcd996bfb702a5960aa' where id=17;");
	db_install_execute("update snmp_query_graph set hash='1e16a505ddefb40356221d7a50619d91' where id=14;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='8d820d091ec1a9683cfa74a462f239ee' where id=82;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='2e8b27c63d98249096ad5bc320787f43' where id=81;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='e85ddc56efa677b70448f9e931360b77' where id=85;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='37bb8c5b38bb7e89ec88ea7ccacf44d4' where id=84;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='62a47c18be10f273a5f5a13a76b76f54' where id=83;");
	db_install_execute("update snmp_query_graph_sv set hash='3f42d358965cb94ce4f708b59e04f82b' where id=18;");
	db_install_execute("update snmp_query_graph_sv set hash='45f44b2f811ea8a8ace1cbed8ef906f1' where id=19;");
	db_install_execute("update snmp_query_graph_sv set hash='69c14fbcc23aecb9920b3cdad7f89901' where id=20;");
	db_install_execute("update snmp_query_graph set hash='d1e0d9b8efd4af98d28ce2aad81a87e7' where id=16;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='2347e9f53564a54d43f3c00d4b60040d' where id=79;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='27eb220995925e1a5e0e41b2582a2af6' where id=80;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='3a0f707d1c8fd0e061b70241541c7e2e' where id=78;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='8ef8ae2ef548892ab95bb6c9f0b3170e' where id=77;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='c7ee2110bf81639086d2da03d9d88286' where id=76;");
	db_install_execute("update snmp_query_graph_sv set hash='809c2e80552d56b65ca496c1c2fff398' where id=33;");
	db_install_execute("update snmp_query_graph_sv set hash='e403f5a733bf5c8401a110609683deb3' where id=32;");
	db_install_execute("update snmp_query_graph_sv set hash='7fb4a267065f960df81c15f9022cd3a4' where id=31;");
	db_install_execute("update snmp_query_graph set hash='ed7f68175d7bb83db8ead332fc945720' where id=20;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='7e87efd0075caba9908e2e6e569b25b0' where id=95;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='dd28d96a253ab86846aedb25d1cca712' where id=96;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='ce425fed4eb3174e4f1cde9713eeafa0' where id=97;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='d0d05156ddb2c65181588db4b64d3907' where id=98;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='3b018f789ff72cc5693ef79e3a794370' where id=99;");
	db_install_execute("update snmp_query_graph_sv set hash='f434ec853c479d424276f367e9806a75' where id=41;");
	db_install_execute("update snmp_query_graph_sv set hash='9b085245847444c5fb90ebbf4448e265' where id=42;");
	db_install_execute("update snmp_query_graph_sv set hash='5977863f28629bd8eb93a2a9cbc3e306' where id=43;");
	db_install_execute("update snmp_query_graph set hash='f85386cd2fc94634ef167c7f1e5fbcd0' where id=21;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='b225229dbbb48c1766cf90298674ceed' where id=100;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='c79248ddbbd195907260887b021a055d' where id=101;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='12a6750d973b7f14783f205d86220082' where id=102;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='25b151fcfe093812cb5c208e36dd697e' where id=103;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='e9ab404a294e406c20fdd30df766161f' where id=104;");
	db_install_execute("update snmp_query_graph_sv set hash='37b6711af3930c56309cf8956d8bbf14' where id=44;");
	db_install_execute("update snmp_query_graph_sv set hash='cc435c5884a75421329a9b08207c1c90' where id=45;");
	db_install_execute("update snmp_query_graph_sv set hash='82edeea1ec249c9818773e3145836492' where id=46;");
	db_install_execute("update snmp_query_graph set hash='7d309bf200b6e3cdb59a33493c2e58e0' where id=22;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='119578a4f01ab47e820b0e894e5e5bb3' where id=105;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='940e57d24b2623849c77b59ed05931b9' where id=106;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='0f045eab01bbc4437b30da568ed5cb03' where id=107;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='bd70bf71108d32f0bf91b24c85b87ff0' where id=108;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='fdc4cb976c4b9053bfa2af791a21c5b5' where id=109;");
	db_install_execute("update snmp_query_graph_sv set hash='87522150ee8a601b4d6a1f6b9e919c47' where id=47;");
	db_install_execute("update snmp_query_graph_sv set hash='993a87c04f550f1209d689d584aa8b45' where id=48;");
	db_install_execute("update snmp_query_graph_sv set hash='183bb486c92a566fddcb0585ede37865' where id=49;");
	db_install_execute("update snmp_query set hash='3c1b27d94ad208a0090f293deadde753' where id=2;");
	db_install_execute("update snmp_query_graph set hash='da43655bf1f641b07579256227806977' where id=6;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='5d3a8b2f4a454e5b0a1494e00fe7d424' where id=10;");
	db_install_execute("update snmp_query_graph_sv set hash='437918b8dcd66a64625c6cee481fff61' where id=7;");
	db_install_execute("update snmp_query set hash='59aab7b0feddc7860002ed9303085ba5' where id=3;");
	db_install_execute("update snmp_query_graph set hash='1cc468ef92a5779d37a26349e27ef3ba' where id=7;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='d0b49af67a83c258ef1eab3780f7b3dc' where id=11;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='bf6b966dc369f3df2ea640a90845e94c' where id=12;");
	db_install_execute("update snmp_query_graph_sv set hash='2ddc61ff4bd9634f33aedce9524b7690' where id=5;");
	db_install_execute("update snmp_query_graph set hash='bef2dc94bc84bf91827f45424aac8d2a' where id=8;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='5c3616603a7ac9d0c1cb9556b377a74f' where id=13;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='080f0022f77044a512b083e3a8304e8b' where id=14;");
	db_install_execute("update snmp_query_graph_sv set hash='c72e2da7af2cdbd6b44a5eb42c5b4758' where id=6;");
	db_install_execute("update snmp_query set hash='ad06f46e22e991cb47c95c7233cfaee8' where id=4;");
	db_install_execute("update snmp_query_graph set hash='5a5ce35edb4b195cbde99fd0161dfb4e' where id=10;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='8fc9a94a5f6ef902a3de0fa7549e7476' where id=29;");
	db_install_execute("update snmp_query_graph_sv set hash='a412c5dfa484b599ec0f570979fdbc9e' where id=11;");
	db_install_execute("update snmp_query_graph set hash='c1c2cfd33eaf5064300e92e26e20bc56' where id=11;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='8132fa9c446e199732f0102733cb1714' where id=30;");
	db_install_execute("update snmp_query_graph_sv set hash='48f4792dd49fefd7d640ec46b1d7bdb3' where id=12;");
	db_install_execute("update snmp_query set hash='8ffa36c1864124b38bcda2ae9bd61f46' where id=6;");
	db_install_execute("update snmp_query_graph set hash='a0b3e7b63c2e66f9e1ea24a16ff245fc' where id=15;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='cb09784ba05e401a3f1450126ed1e395' where id=69;");
	db_install_execute("update snmp_query_graph_sv set hash='f21b23df740bc4a2d691d2d7b1b18dba' where id=30;");
	db_install_execute("update snmp_query set hash='30ec734bc0ae81a3d995be82c73f46c1' where id=7;");
	db_install_execute("update snmp_query_graph set hash='f6db4151aa07efa401a0af6c9b871844' where id=17;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='42277993a025f1bfd85374d6b4deeb60' where id=92;");
	db_install_execute("update snmp_query_graph_sv set hash='d99f8db04fd07bcd2260d246916e03da' where id=40;");
	db_install_execute("update snmp_query set hash='9343eab1f4d88b0e61ffc9d020f35414' where id=8;");
	db_install_execute("update snmp_query_graph set hash='46c4ee688932cf6370459527eceb8ef3' where id=18;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='a3f280327b1592a1a948e256380b544f' where id=93;");
	db_install_execute("update snmp_query_graph_sv set hash='9852782792ede7c0805990e506ac9618' where id=38;");
	db_install_execute("update snmp_query set hash='0d1ab53fe37487a5d0b9e1d3ee8c1d0d' where id=9;");
	db_install_execute("update snmp_query_graph set hash='4a515b61441ea5f27ab7dee6c3cb7818' where id=19;");
	db_install_execute("update snmp_query_graph_rrd_sv set hash='b5a724edc36c10891fa2a5c370d55b6f' where id=94;");
	db_install_execute("update snmp_query_graph_sv set hash='fa2f07ab54fce72eea684ba893dd9c95' where id=39;");
	db_install_execute("update host_template set hash='4855b0e3e553085ed57219690285f91f' where id=1;");
	db_install_execute("update host_template set hash='07d3fe6a52915f99e642d22e27d967a4' where id=3;");
	db_install_execute("update host_template set hash='4e5dc8dd115264c2e9f3adb725c29413' where id=4;");
	db_install_execute("update host_template set hash='cae6a879f86edacb2471055783bec6d0' where id=5;");
	db_install_execute("update host_template set hash='9ef418b4251751e09c3c416704b01b01' where id=6;");
	db_install_execute("update host_template set hash='5b8300be607dce4f030b026a381b91cd' where id=7;");
	db_install_execute("update host_template set hash='2d3e47f416738c2d22c87c40218cc55e' where id=8;");

	if (db_table_exists('rra')) {
		db_install_execute("update rra set hash='c21df5178e5c955013591239eb0afd46' where id=1;");
		db_install_execute("update rra set hash='0d9c0af8b8acdc7807943937b3208e29' where id=2;");
		db_install_execute("update rra set hash='6fc2d038fb42950138b0ce3e9874cc60' where id=3;");
		db_install_execute("update rra set hash='e36f3adb9f152adfa5dc50fd2b23337e' where id=4;");
	}

	$item = db_fetch_assoc("select id from cdef");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update cdef set hash='" . get_hash_cdef($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");

			$item2 = db_fetch_assoc("select id from cdef_items where cdef_id=" . $item[$i]["id"]);
			if ($item2 !== false) {
				for ($j=0; $j<cacti_sizeof($item2); $j++) {
					db_install_execute("update cdef_items set hash='" . get_hash_cdef($item2[$j]["id"], "cdef_item") . "' where id=" . $item2[$j]["id"] . ";");
				}
			}
		}
	}

	$item = db_fetch_assoc("select id from graph_templates_gprint");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update graph_templates_gprint set hash='" . get_hash_gprint($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");
		}
	}

	$item = db_fetch_assoc("select id from data_input");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update data_input set hash='" . get_hash_data_input($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");

			$item2 = db_fetch_assoc("select id from data_input_fields where data_input_id=" . $item[$i]["id"]);
			if ($item2 !== false) {
				for ($j=0; $j<cacti_sizeof($item2); $j++) {
					db_install_execute("update data_input_fields set hash='" . get_hash_data_input($item2[$j]["id"], "data_input_field") . "' where id=" . $item2[$j]["id"] . ";");
				}
			}
		}
	}

	$item = db_fetch_assoc("select id from data_template");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update data_template set hash='" . get_hash_data_template($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");

			$item2 = db_fetch_assoc("select id from data_template_rrd where data_template_id=" . $item[$i]["id"] . " and local_data_id=0");
			if ($item2 !== false) {
				for ($j=0; $j<cacti_sizeof($item2); $j++) {
					db_install_execute("update data_template_rrd set hash='" . get_hash_data_template($item2[$j]["id"], "data_template_item") . "' where id=" . $item2[$j]["id"] . ";");
				}
			}
		}
	}

	$item = db_fetch_assoc("select id from graph_templates");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update graph_templates set hash='" . get_hash_graph_template($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");

			$item2 = db_fetch_assoc("select id from graph_templates_item where graph_template_id=" . $item[$i]["id"] . " and local_graph_id=0");
			if ($item !== false) {
				for ($j=0; $j<cacti_sizeof($item2); $j++) {
					db_install_execute("update graph_templates_item set hash='" . get_hash_graph_template($item2[$j]["id"], "graph_template_item") . "' where id=" . $item2[$j]["id"] . ";");
				}
			}

			$item2 = db_fetch_assoc("select id from graph_template_input where graph_template_id=" . $item[$i]["id"]);
			if ($item2 !== false) {
				for ($j=0; $j<cacti_sizeof($item2); $j++) {
					db_install_execute("update graph_template_input set hash='" . get_hash_graph_template($item2[$j]["id"], "graph_template_input") . "' where id=" . $item2[$j]["id"] . ";");
				}
			}
		}
	}

	$item = db_fetch_assoc("select id from snmp_query");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update snmp_query set hash='" . get_hash_data_query($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");

			$item2 = db_fetch_assoc("select id from snmp_query_graph where snmp_query_id=" . $item[$i]["id"]);
			if ($item2 !== false) {
				for ($j=0; $j<cacti_sizeof($item2); $j++) {
					db_install_execute("update snmp_query_graph set hash='" . get_hash_data_query($item2[$j]["id"], "data_query_graph") . "' where id=" . $item2[$j]["id"] . ";");

					$item3 = db_fetch_assoc("select id from snmp_query_graph_rrd_sv where snmp_query_graph_id=" . $item2[$j]["id"]);
					if ($item3 !== false) {
						for ($k=0; $k<cacti_sizeof($item3); $k++) {
							db_install_execute("update snmp_query_graph_rrd_sv set hash='" . get_hash_data_query($item3[$k]["id"], "data_query_sv_data_source") . "' where id=" . $item3[$k]["id"] . ";");
						}
					}

					$item3 = db_fetch_assoc("select id from snmp_query_graph_sv where snmp_query_graph_id=" . $item2[$j]["id"]);
					if ($item3 !== false) {
						for ($k=0; $k<cacti_sizeof($item3); $k++) {
							db_install_execute("update snmp_query_graph_sv set hash='" . get_hash_data_query($item3[$k]["id"], "data_query_sv_graph") . "' where id=" . $item3[$k]["id"] . ";");
						}
					}
				}
			}
		}
	}

	$item = db_fetch_assoc("select id from host_template");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update host_template set hash='" . get_hash_host_template($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");
		}
	}

	$item = db_fetch_assoc("select id from rra");
	if ($item !== false) {
		for ($i=0; $i<cacti_sizeof($item); $i++) {
			db_install_execute("update rra set hash='" . get_hash_round_robin_archive($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");
		}
	}
}
