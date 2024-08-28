<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
		db_install_execute('UPDATE `host` SET hostname=management_ip;');
		db_install_execute("UPDATE `data_input_fields` SET type_code='hostname' WHERE type_code='management_ip';");
	}

	if (db_column_exists('data_input_data_cache', 'management_ip')) {
		db_install_execute("ALTER TABLE `data_input_data_cache` CHANGE `management_ip` `hostname` VARCHAR( 250 ) NOT NULL, ADD `snmp_port` MEDIUMINT( 5 ) UNSIGNED DEFAULT '161' NOT NULL AFTER `snmp_password`, ADD `snmp_timeout` MEDIUMINT( 8 ) UNSIGNED NOT NULL AFTER `snmp_port`;");
	}

	db_install_add_column('host', array('name' => 'snmp_port', 'type' => 'mediumint(5)', 'NULL' => false, 'after' => 'snmp_password', 'default' => '161'));

	db_install_drop_column('host', 'management_ip');
	db_install_drop_column('data_input', 'output_string');

	db_install_add_key('host_snmp_cache', 'KEY', 'PRIMARY',  array('host_id', 'snmp_query_id', 'field_name', 'snmp_index' ));

	/* hash columns for xml export/import code */
	$field_data = array(
		'name'  => 'hash',
		'type'  => 'varchar(32)',
		'NULL'  => false,
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
	$users_results = db_install_fetch_assoc('SELECT id FROM user_auth');
	$users         = $users_results['data'];

	if ($users !== false && cacti_sizeof($users) > 0) {
		foreach ($users as $user) {
			$realms = db_install_fetch_assoc("SELECT realm_id FROM user_auth_realm WHERE user_id=?", array($user["id"]), false);
			if ($realms !== false && cacti_sizeof($realms) == 13) {
				db_install_execute('INSERT INTO user_auth_realm (user_id,realm_id) VALUES (?,4)', array($user['id']));
				db_install_execute('INSERT INTO user_auth_realm (user_id,realm_id) VALUES (?,16)', array($user['id']));
				db_install_execute('INSERT INTO user_auth_realm (user_id,realm_id) VALUES (?,17)', array($user['id']));
			}
		}
	}

	/* there are a LOT of hashes to SET */
	db_install_execute("UPDATE cdef SET hash='73f95f8b77b5508157d64047342c421e' WHERE id=2;");
	db_install_execute("UPDATE cdef_items SET hash='9bbf6b792507bb9bb17d2af0970f9be9' WHERE id=7;");
	db_install_execute("UPDATE cdef_items SET hash='a4b8eb2c3bf4920a3ef571a7a004be53' WHERE id=9;");
	db_install_execute("UPDATE cdef_items SET hash='caa4e023ac2d7b1c4b4c8c4adfd55dfe' WHERE id=8;");
	db_install_execute("UPDATE cdef SET hash='3d352eed9fa8f7b2791205b3273708c7' WHERE id=3;");
	db_install_execute("UPDATE cdef_items SET hash='c888c9fe6b62c26c4bfe23e18991731d' WHERE id=10;");
	db_install_execute("UPDATE cdef_items SET hash='1e1d0b29a94e08b648c8f053715442a0' WHERE id=11;");
	db_install_execute("UPDATE cdef_items SET hash='4355c197998c7f8b285be7821ddc6da4' WHERE id=12;");
	db_install_execute("UPDATE cdef SET hash='e961cc8ec04fda6ed4981cf5ad501aa5' WHERE id=4;");
	db_install_execute("UPDATE cdef_items SET hash='40bb7a1143b0f2e2efca14eb356236de' WHERE id=13;");
	db_install_execute("UPDATE cdef_items SET hash='42686ea0925c0220924b7d333599cd67' WHERE id=14;");
	db_install_execute("UPDATE cdef_items SET hash='faf1b148b2c0e0527362ed5b8ca1d351' WHERE id=15;");
	db_install_execute("UPDATE cdef SET hash='f1ac79f05f255c02f914c920f1038c54' WHERE id=12;");
	db_install_execute("UPDATE cdef_items SET hash='0ef6b8a42dc83b4e43e437960fccd2ea' WHERE id=16;");
	db_install_execute("UPDATE cdef SET hash='634a23af5e78af0964e8d33b1a4ed26b' WHERE id=14;");
	db_install_execute("UPDATE cdef_items SET hash='86370cfa0008fe8c56b28be80ee39a40' WHERE id=18;");
	db_install_execute("UPDATE cdef_items SET hash='9a35cc60d47691af37f6fddf02064e20' WHERE id=19;");
	db_install_execute("UPDATE cdef_items SET hash='5d7a7941ec0440b257e5598a27dd1688' WHERE id=20;");
	db_install_execute("UPDATE cdef SET hash='068984b5ccdfd2048869efae5166f722' WHERE id=15;");
	db_install_execute("UPDATE cdef_items SET hash='44fd595c60539ff0f5817731d9f43a85' WHERE id=21;");
	db_install_execute("UPDATE cdef_items SET hash='aa38be265e5ac31783e57ce6f9314e9a' WHERE id=22;");
	db_install_execute("UPDATE cdef_items SET hash='204423d4b2598f1f7252eea19458345c' WHERE id=23;");
	db_install_execute("UPDATE graph_templates_gprint SET hash='e9c43831e54eca8069317a2ce8c6f751' WHERE id=2;");
	db_install_execute("UPDATE graph_templates_gprint SET hash='19414480d6897c8731c7dc6c5310653e' WHERE id=3;");
	db_install_execute("UPDATE graph_templates_gprint SET hash='304a778405392f878a6db435afffc1e9' WHERE id=4;");
	db_install_execute("UPDATE data_input SET hash='3eb92bb845b9660a7445cf9740726522' WHERE id=1;");
	db_install_execute("UPDATE data_input_fields SET hash='92f5906c8dc0f964b41f4253df582c38' WHERE id=1;");
	db_install_execute("UPDATE data_input_fields SET hash='32285d5bf16e56c478f5e83f32cda9ef' WHERE id=2;");
	db_install_execute("UPDATE data_input_fields SET hash='ad14ac90641aed388139f6ba86a2e48b' WHERE id=3;");
	db_install_execute("UPDATE data_input_fields SET hash='9c55a74bd571b4f00a96fd4b793278c6' WHERE id=4;");
	db_install_execute("UPDATE data_input_fields SET hash='012ccb1d3687d3edb29c002ea66e72da' WHERE id=5;");
	db_install_execute("UPDATE data_input_fields SET hash='4276a5ec6e3fe33995129041b1909762' WHERE id=6;");
	db_install_execute("UPDATE data_input SET hash='bf566c869ac6443b0c75d1c32b5a350e' WHERE id=2;");
	db_install_execute("UPDATE data_input_fields SET hash='617cdc8a230615e59f06f361ef6e7728' WHERE id=7;");
	db_install_execute("UPDATE data_input_fields SET hash='acb449d1451e8a2a655c2c99d31142c7' WHERE id=8;");
	db_install_execute("UPDATE data_input_fields SET hash='f4facc5e2ca7ebee621f09bc6d9fc792' WHERE id=9;");
	db_install_execute("UPDATE data_input_fields SET hash='1cc1493a6781af2c478fa4de971531cf' WHERE id=10;");
	db_install_execute("UPDATE data_input_fields SET hash='b5c23f246559df38662c255f4aa21d6b' WHERE id=11;");
	db_install_execute("UPDATE data_input_fields SET hash='6027a919c7c7731fbe095b6f53ab127b' WHERE id=12;");
	db_install_execute("UPDATE data_input_fields SET hash='cbbe5c1ddfb264a6e5d509ce1c78c95f' WHERE id=13;");
	db_install_execute("UPDATE data_input_fields SET hash='e6deda7be0f391399c5130e7c4a48b28' WHERE id=14;");
	db_install_execute("UPDATE data_input SET hash='274f4685461170b9eb1b98d22567ab5e' WHERE id=3;");
	db_install_execute("UPDATE data_input_fields SET hash='edfd72783ad02df128ff82fc9324b4b9' WHERE id=15;");
	db_install_execute("UPDATE data_input_fields SET hash='8b75fb61d288f0b5fc0bd3056af3689b' WHERE id=16;");
	db_install_execute("UPDATE data_input SET hash='95ed0993eb3095f9920d431ac80f4231' WHERE id=4;");
	db_install_execute("UPDATE data_input_fields SET hash='363588d49b263d30aecb683c52774f39' WHERE id=17;");
	db_install_execute("UPDATE data_input_fields SET hash='ad139a9e1d69881da36fca07889abf58' WHERE id=18;");
	db_install_execute("UPDATE data_input_fields SET hash='5db9fee64824c08258c7ff6f8bc53337' WHERE id=19;");
	db_install_execute("UPDATE data_input SET hash='79a284e136bb6b061c6f96ec219ac448' WHERE id=5;");
	db_install_execute("UPDATE data_input_fields SET hash='c0cfd0beae5e79927c5a360076706820' WHERE id=20;");
	db_install_execute("UPDATE data_input_fields SET hash='52c58ad414d9a2a83b00a7a51be75a53' WHERE id=21;");
	db_install_execute("UPDATE data_input SET hash='362e6d4768937c4f899dd21b91ef0ff8' WHERE id=6;");
	db_install_execute("UPDATE data_input_fields SET hash='05eb5d710f0814871b8515845521f8d7' WHERE id=22;");
	db_install_execute("UPDATE data_input_fields SET hash='86cb1cbfde66279dbc7f1144f43a3219' WHERE id=23;");
	db_install_execute("UPDATE data_input SET hash='a637359e0a4287ba43048a5fdf202066' WHERE id=7;");
	db_install_execute("UPDATE data_input_fields SET hash='d5a8dd5fbe6a5af11667c0039af41386' WHERE id=24;");
	db_install_execute("UPDATE data_input SET hash='47d6bfe8be57a45171afd678920bd399' WHERE id=8;");
	db_install_execute("UPDATE data_input_fields SET hash='8848cdcae831595951a3f6af04eec93b' WHERE id=25;");
	db_install_execute("UPDATE data_input_fields SET hash='3d1288d33008430ce354e8b9c162f7ff' WHERE id=26;");
	db_install_execute("UPDATE data_input SET hash='cc948e4de13f32b6aea45abaadd287a3' WHERE id=9;");
	db_install_execute("UPDATE data_input_fields SET hash='c6af570bb2ed9c84abf32033702e2860' WHERE id=27;");
	db_install_execute("UPDATE data_input_fields SET hash='f9389860f5c5340c9b27fca0b4ee5e71' WHERE id=28;");
	db_install_execute("UPDATE data_input SET hash='8bd153aeb06e3ff89efc73f35849a7a0' WHERE id=10;");
	db_install_execute("UPDATE data_input_fields SET hash='5fbadb91ad66f203463c1187fe7bd9d5' WHERE id=29;");
	db_install_execute("UPDATE data_input_fields SET hash='6ac4330d123c69067d36a933d105e89a' WHERE id=30;");
	db_install_execute("UPDATE data_input SET hash='80e9e4c4191a5da189ae26d0e237f015' WHERE id=11;");
	db_install_execute("UPDATE data_input_fields SET hash='d39556ecad6166701bfb0e28c5a11108' WHERE id=31;");
	db_install_execute("UPDATE data_input_fields SET hash='3b7caa46eb809fc238de6ef18b6e10d5' WHERE id=32;");
	db_install_execute("UPDATE data_input_fields SET hash='74af2e42dc12956c4817c2ef5d9983f9' WHERE id=33;");
	db_install_execute("UPDATE data_input_fields SET hash='8ae57f09f787656bf4ac541e8bd12537' WHERE id=34;");
	db_install_execute("UPDATE data_template SET hash='c8a8f50f5f4a465368222594c5709ede' WHERE id=3;");
	db_install_execute("UPDATE data_template_rrd SET hash='2d53f9c76767a2ae8909f4152fd473a4' WHERE id=3;");
	db_install_execute("UPDATE data_template_rrd SET hash='93d91aa7a3cc5473e7b195d5d6e6e675' WHERE id=4;");
	db_install_execute("UPDATE data_template SET hash='cdfed2d401723d2f41fc239d4ce249c7' WHERE id=4;");
	db_install_execute("UPDATE data_template_rrd SET hash='7bee7987bbf30a3bc429d2a67c6b2595' WHERE id=5;");
	db_install_execute("UPDATE data_template SET hash='a27e816377d2ac6434a87c494559c726' WHERE id=5;");
	db_install_execute("UPDATE data_template_rrd SET hash='ddccd7fbdece499da0235b4098b87f9e' WHERE id=6;");
	db_install_execute("UPDATE data_template SET hash='c06c3d20eccb9598939dc597701ff574' WHERE id=6;");
	db_install_execute("UPDATE data_template_rrd SET hash='122ab2097f8c6403b7b90cde7b9e2bc2' WHERE id=7;");
	db_install_execute("UPDATE data_template SET hash='a14f2d6f233b05e64263ff03a5b0b386' WHERE id=7;");
	db_install_execute("UPDATE data_template_rrd SET hash='34f50c820092ea0fecba25b4b94a7946' WHERE id=8;");
	db_install_execute("UPDATE data_template SET hash='def1a9019d888ed2ad2e106aa9595ede' WHERE id=8;");
	db_install_execute("UPDATE data_template_rrd SET hash='830b811d1834e5ba0e2af93bd92db057' WHERE id=9;");
	db_install_execute("UPDATE data_template SET hash='513a99ae3c9c4413609c1534ffc36eab' WHERE id=9;");
	db_install_execute("UPDATE data_template_rrd SET hash='2f1b016a2465eef3f7369f6313cd4a94' WHERE id=10;");
	db_install_execute("UPDATE data_template SET hash='77404ae93c9cc410f1c2c717e7117378' WHERE id=10;");
	db_install_execute("UPDATE data_template_rrd SET hash='28ffcecaf8b50e49f676f2d4a822685d' WHERE id=11;");
	db_install_execute("UPDATE data_template SET hash='9e72511e127de200733eb502eb818e1d' WHERE id=11;");
	db_install_execute("UPDATE data_template_rrd SET hash='8175ca431c8fe50efff5a1d3ae51b55d' WHERE id=12;");
	db_install_execute("UPDATE data_template_rrd SET hash='a2eeb8acd6ea01cd0e3ac852965c0eb6' WHERE id=13;");
	db_install_execute("UPDATE data_template_rrd SET hash='9f951b7fb3b19285a411aebb5254a831' WHERE id=14;");
	db_install_execute("UPDATE data_template SET hash='dc33aa9a8e71fb7c61ec0e7a6da074aa' WHERE id=13;");
	db_install_execute("UPDATE data_template_rrd SET hash='a4df3de5238d3beabee1a2fe140d3d80' WHERE id=16;");
	db_install_execute("UPDATE data_template SET hash='41f55087d067142d702dd3c73c98f020' WHERE id=15;");
	db_install_execute("UPDATE data_template_rrd SET hash='7fea6acc9b1a19484b4cb4cef2b6c5da' WHERE id=18;");
	db_install_execute("UPDATE data_template SET hash='9b8c92d3c32703900ff7dd653bfc9cd8' WHERE id=16;");
	db_install_execute("UPDATE data_template_rrd SET hash='f1ba3a5b17b95825021241398bb0f277' WHERE id=19;");
	db_install_execute("UPDATE data_template SET hash='c221c2164c585b6da378013a7a6a2c13' WHERE id=17;");
	db_install_execute("UPDATE data_template_rrd SET hash='46a5afe8e6c0419172c76421dc9e304a' WHERE id=20;");
	db_install_execute("UPDATE data_template SET hash='a30a81cb1de65b52b7da542c8df3f188' WHERE id=18;");
	db_install_execute("UPDATE data_template_rrd SET hash='962fd1994fe9cae87fb36436bdb8a742' WHERE id=21;");
	db_install_execute("UPDATE data_template SET hash='0de466a1b81dfe581d44ac014b86553a' WHERE id=19;");
	db_install_execute("UPDATE data_template_rrd SET hash='7a8dd1111a8624369906bf2cd6ea9ca9' WHERE id=22;");
	db_install_execute("UPDATE data_template SET hash='bbe2da0708103029fbf949817d3a4537' WHERE id=20;");
	db_install_execute("UPDATE data_template_rrd SET hash='ddb6e74d34d2f1969ce85f809dbac23d' WHERE id=23;");
	db_install_execute("UPDATE data_template SET hash='e4ac5d5fe73e3c773671c6d0498a8d9d' WHERE id=22;");
	db_install_execute("UPDATE data_template_rrd SET hash='289311d10336941d33d9a1c48a7b11ee' WHERE id=25;");
	db_install_execute("UPDATE data_template SET hash='f29f8c998425eedd249be1e7caf90ceb' WHERE id=23;");
	db_install_execute("UPDATE data_template_rrd SET hash='02216f036cca04655ee2f67fedb6f4f0' WHERE id=26;");
	db_install_execute("UPDATE data_template SET hash='7a6216a113e19881e35565312db8a371' WHERE id=24;");
	db_install_execute("UPDATE data_template_rrd SET hash='9e402c0f29131ef7139c20bd500b4e8a' WHERE id=27;");
	db_install_execute("UPDATE data_template SET hash='1dbd1251c8e94b334c0e6aeae5ca4b8d' WHERE id=25;");
	db_install_execute("UPDATE data_template_rrd SET hash='46717dfe3c8c030d8b5ec0874f9dbdca' WHERE id=28;");
	db_install_execute("UPDATE data_template SET hash='1a4c5264eb27b5e57acd3160af770a61' WHERE id=26;");
	db_install_execute("UPDATE data_template_rrd SET hash='7a88a60729af62561812c43bde61dfc1' WHERE id=29;");
	db_install_execute("UPDATE data_template SET hash='e9def3a0e409f517cb804dfeba4ccd90' WHERE id=27;");
	db_install_execute("UPDATE data_template_rrd SET hash='3c0fd1a188b64a662dfbfa985648397b' WHERE id=30;");
	db_install_execute("UPDATE data_template SET hash='4e00eeac4938bc51985ad71ae85c0df5' WHERE id=28;");
	db_install_execute("UPDATE data_template_rrd SET hash='82b26f81d81fa14e9159618cfa6b4ba8' WHERE id=31;");
	db_install_execute("UPDATE data_template SET hash='1fd08cfd5825b8273ffaa2c4ac0d7919' WHERE id=29;");
	db_install_execute("UPDATE data_template_rrd SET hash='f888bdbbe48a17cbb343fefabbc74064' WHERE id=32;");
	db_install_execute("UPDATE data_template SET hash='9b82d44eb563027659683765f92c9757' WHERE id=30;");
	db_install_execute("UPDATE data_template_rrd SET hash='ed44c2438ef7e46e2aeed2b6c580815c' WHERE id=33;");
	db_install_execute("UPDATE data_template SET hash='87847714d19f405ff3c74f3341b3f940' WHERE id=31;");
	db_install_execute("UPDATE data_template_rrd SET hash='9b3a00c9e3530d9e58895ac38271361e' WHERE id=34;");
	db_install_execute("UPDATE data_template SET hash='308ac157f24e2763f8cd828a80b3e5ff' WHERE id=32;");
	db_install_execute("UPDATE data_template_rrd SET hash='6746c2ed836ecc68a71bbddf06b0e5d9' WHERE id=35;");
	db_install_execute("UPDATE data_template SET hash='797a3e92b0039841b52e441a2823a6fb' WHERE id=33;");
	db_install_execute("UPDATE data_template_rrd SET hash='9835d9e1a8c78aa2475d752e8fa74812' WHERE id=36;");
	db_install_execute("UPDATE data_template SET hash='fa15932d3cab0da2ab94c69b1a9f5ca7' WHERE id=34;");
	db_install_execute("UPDATE data_template_rrd SET hash='9c78dc1981bcea841b8c827c6dc0d26c' WHERE id=37;");
	db_install_execute("UPDATE data_template SET hash='6ce4ab04378f9f3b03ee0623abb6479f' WHERE id=35;");
	db_install_execute("UPDATE data_template_rrd SET hash='62a56dc76fe4cd8566a31b5df0274cc3' WHERE id=38;");
	db_install_execute("UPDATE data_template_rrd SET hash='2e366ab49d0e0238fb4e3141ea5a88c3' WHERE id=39;");
	db_install_execute("UPDATE data_template_rrd SET hash='dceedc84718dd93a5affe4b190bca810' WHERE id=40;");
	db_install_execute("UPDATE data_template SET hash='03060555fab086b8412bbf9951179cd9' WHERE id=36;");
	db_install_execute("UPDATE data_template_rrd SET hash='93330503f1cf67db00d8fe636035e545' WHERE id=42;");
	db_install_execute("UPDATE data_template_rrd SET hash='6b0fe4aa6aaf22ef9cfbbe96d87fa0d7' WHERE id=43;");
	db_install_execute("UPDATE data_template SET hash='e4ac6919d4f6f21ec5b281a1d6ac4d4e' WHERE id=37;");
	db_install_execute("UPDATE data_template_rrd SET hash='4c82df790325d789d304e6ee5cd4ab7d' WHERE id=44;");
	db_install_execute("UPDATE data_template_rrd SET hash='07175541991def89bd02d28a215f6fcc' WHERE id=56;");
	db_install_execute("UPDATE data_template SET hash='36335cd98633963a575b70639cd2fdad' WHERE id=38;");
	db_install_execute("UPDATE data_template_rrd SET hash='c802e2fd77f5b0a4c4298951bf65957c' WHERE id=46;");
	db_install_execute("UPDATE data_template_rrd SET hash='4e2a72240955380dc8ffacfcc8c09874' WHERE id=47;");
	db_install_execute("UPDATE data_template_rrd SET hash='13ebb33f9cbccfcba828db1075a8167c' WHERE id=50;");
	db_install_execute("UPDATE data_template_rrd SET hash='31399c3725bee7e09ec04049e3d5cd17' WHERE id=51;");
	db_install_execute("UPDATE data_template SET hash='2f654f7d69ac71a5d56b1db8543ccad3' WHERE id=39;");
	db_install_execute("UPDATE data_template_rrd SET hash='636672962b5bb2f31d86985e2ab4bdfe' WHERE id=48;");
	db_install_execute("UPDATE data_template_rrd SET hash='18ce92c125a236a190ee9dd948f56268' WHERE id=49;");
	db_install_execute("UPDATE data_template SET hash='c84e511401a747409053c90ba910d0fe' WHERE id=40;");
	db_install_execute("UPDATE data_template_rrd SET hash='7be68cbc4ee0b2973eb9785f8c7a35c7' WHERE id=52;");
	db_install_execute("UPDATE data_template_rrd SET hash='93e2b6f59b10b13f2ddf2da3ae98b89a' WHERE id=53;");
	db_install_execute("UPDATE data_template SET hash='6632e1e0b58a565c135d7ff90440c335' WHERE id=41;");
	db_install_execute("UPDATE data_template_rrd SET hash='2df25c57022b0c7e7d0be4c035ada1a0' WHERE id=54;");
	db_install_execute("UPDATE data_template_rrd SET hash='721c0794526d1ac1c359f27dc56faa49' WHERE id=55;");
	db_install_execute("UPDATE data_template SET hash='1d17325f416b262921a0b55fe5f7e31d' WHERE id=42;");
	db_install_execute("UPDATE data_template_rrd SET hash='07492e5cace6d74e7db3cb1fc005a3f3' WHERE id=76;");
	db_install_execute("UPDATE data_template SET hash='d814fa3b79bd0f8933b6e0834d3f16d0' WHERE id=43;");
	db_install_execute("UPDATE data_template_rrd SET hash='165a0da5f461561c85d092dfe96b9551' WHERE id=92;");
	db_install_execute("UPDATE data_template_rrd SET hash='0ee6bb54957f6795a5369a29f818d860' WHERE id=78;");
	db_install_execute("UPDATE data_template SET hash='f6e7d21c19434666bbdac00ccef9932f' WHERE id=44;");
	db_install_execute("UPDATE data_template_rrd SET hash='9825aaf7c0bdf1554c5b4b86680ac2c0' WHERE id=79;");
	db_install_execute("UPDATE data_template SET hash='f383db441d1c246cff8482f15e184e5f' WHERE id=45;");
	db_install_execute("UPDATE data_template_rrd SET hash='50ccbe193c6c7fc29fb9f726cd6c48ee' WHERE id=80;");
	db_install_execute("UPDATE data_template SET hash='2ef027cc76d75720ee5f7a528f0f1fda' WHERE id=46;");
	db_install_execute("UPDATE data_template_rrd SET hash='9464c91bcff47f23085ae5adae6ab987' WHERE id=81;");
	db_install_execute("UPDATE graph_templates SET hash='5deb0d66c81262843dce5f3861be9966' WHERE id=2;");
	db_install_execute("UPDATE graph_templates_item SET hash='0470b2427dbfadb6b8346e10a71268fa' WHERE id=9;");
	db_install_execute("UPDATE graph_templates_item SET hash='84a5fe0db518550266309823f994ce9c' WHERE id=10;");
	db_install_execute("UPDATE graph_templates_item SET hash='2f222f28084085cd06a1f46e4449c793' WHERE id=11;");
	db_install_execute("UPDATE graph_templates_item SET hash='55acbcc33f46ee6d754e8e81d1b54808' WHERE id=12;");
	db_install_execute("UPDATE graph_templates_item SET hash='fdaf2321fc890e355711c2bffc07d036' WHERE id=13;");
	db_install_execute("UPDATE graph_templates_item SET hash='768318f42819217ed81196d2179d3e1b' WHERE id=14;");
	db_install_execute("UPDATE graph_templates_item SET hash='cb3aa6256dcb3acd50d4517b77a1a5c3' WHERE id=15;");
	db_install_execute("UPDATE graph_templates_item SET hash='671e989be7cbf12c623b4e79d91c7bed' WHERE id=16;");
	db_install_execute("UPDATE graph_template_input SET hash='e9d4191277fdfd7d54171f153da57fb0' WHERE id=3;");
	db_install_execute("UPDATE graph_template_input SET hash='7b361722a11a03238ee8ab7ce44a1037' WHERE id=4;");
	db_install_execute("UPDATE graph_templates SET hash='abb5e813c9f1e8cd6fc1e393092ef8cb' WHERE id=3;");
	db_install_execute("UPDATE graph_templates_item SET hash='b561ed15b3ba66d277e6d7c1640b86f7' WHERE id=17;");
	db_install_execute("UPDATE graph_templates_item SET hash='99ef051057fa6adfa6834a7632e9d8a2' WHERE id=18;");
	db_install_execute("UPDATE graph_templates_item SET hash='3986695132d3f4716872df4c6fbccb65' WHERE id=19;");
	db_install_execute("UPDATE graph_templates_item SET hash='0444300017b368e6257f010dca8bbd0d' WHERE id=20;");
	db_install_execute("UPDATE graph_templates_item SET hash='4d6a0b9063124ca60e2d1702b3e15e41' WHERE id=21;");
	db_install_execute("UPDATE graph_templates_item SET hash='181b08325e4d00cd50b8cdc8f8ae8e77' WHERE id=22;");
	db_install_execute("UPDATE graph_templates_item SET hash='bba0a9ff1357c990df50429d64314340' WHERE id=23;");
	db_install_execute("UPDATE graph_templates_item SET hash='d4a67883d53bc1df8aead21c97c0bc52' WHERE id=24;");
	db_install_execute("UPDATE graph_templates_item SET hash='253c9ec2d66905245149c1c2dc8e536e' WHERE id=25;");
	db_install_execute("UPDATE graph_templates_item SET hash='ea9ea883383f4eb462fec6aa309ba7b5' WHERE id=26;");
	db_install_execute("UPDATE graph_templates_item SET hash='83b746bcaba029eeca170a9f77ec4864' WHERE id=27;");
	db_install_execute("UPDATE graph_templates_item SET hash='82e01dd92fd37887c0696192efe7af65' WHERE id=28;");
	db_install_execute("UPDATE graph_template_input SET hash='b33eb27833614056e06ee5952c3e0724' WHERE id=5;");
	db_install_execute("UPDATE graph_template_input SET hash='ef8799e63ee00e8904bcc4228015784a' WHERE id=6;");
	db_install_execute("UPDATE graph_templates SET hash='e334bdcf821cd27270a4cc945e80915e' WHERE id=4;");
	db_install_execute("UPDATE graph_templates_item SET hash='ff0a6125acbb029b814ed1f271ad2d38' WHERE id=29;");
	db_install_execute("UPDATE graph_templates_item SET hash='f0776f7d6638bba76c2c27f75a424f0f' WHERE id=30;");
	db_install_execute("UPDATE graph_templates_item SET hash='39f4e021aa3fed9207b5f45a82122b21' WHERE id=31;");
	db_install_execute("UPDATE graph_templates_item SET hash='800f0b067c06f4ec9c2316711ea83c1e' WHERE id=32;");
	db_install_execute("UPDATE graph_templates_item SET hash='9419dd5dbf549ba4c5dc1462da6ee321' WHERE id=33;");
	db_install_execute("UPDATE graph_templates_item SET hash='e461dd263ae47657ea2bf3fd82bec096' WHERE id=34;");
	db_install_execute("UPDATE graph_templates_item SET hash='f2d1fbb8078a424ffc8a6c9d44d8caa0' WHERE id=35;");
	db_install_execute("UPDATE graph_templates_item SET hash='e70a5de639df5ba1705b5883da7fccfc' WHERE id=36;");
	db_install_execute("UPDATE graph_templates_item SET hash='85fefb25ce9fd0317da2706a5463fc42' WHERE id=37;");
	db_install_execute("UPDATE graph_templates_item SET hash='a1cb26878776999db16f1de7577b3c2a' WHERE id=38;");
	db_install_execute("UPDATE graph_templates_item SET hash='7d0f9bf64a0898a0095f099674754273' WHERE id=39;");
	db_install_execute("UPDATE graph_templates_item SET hash='b2879248a522d9679333e1f29e9a87c3' WHERE id=40;");
	db_install_execute("UPDATE graph_templates_item SET hash='d800aa59eee45383b3d6d35a11cdc864' WHERE id=41;");
	db_install_execute("UPDATE graph_templates_item SET hash='cab4ae79a546826288e273ca1411c867' WHERE id=42;");
	db_install_execute("UPDATE graph_templates_item SET hash='d44306ae85622fec971507460be63f5c' WHERE id=43;");
	db_install_execute("UPDATE graph_templates_item SET hash='aa5c2118035bb83be497d4e099afcc0d' WHERE id=44;");
	db_install_execute("UPDATE graph_template_input SET hash='4d52e112a836d4c9d451f56602682606' WHERE id=32;");
	db_install_execute("UPDATE graph_template_input SET hash='f0310b066cc919d2f898b8d1ebf3b518' WHERE id=33;");
	db_install_execute("UPDATE graph_template_input SET hash='d9eb6b9eb3d7dd44fd14fdefb4096b54' WHERE id=34;");
	db_install_execute("UPDATE graph_templates SET hash='280e38336d77acde4672879a7db823f3' WHERE id=5;");
	db_install_execute("UPDATE graph_templates_item SET hash='4aa34ea1b7542b770ace48e8bc395a22' WHERE id=45;");
	db_install_execute("UPDATE graph_templates_item SET hash='22f118a9d81d0a9c8d922efbbc8a9cc1' WHERE id=46;");
	db_install_execute("UPDATE graph_templates_item SET hash='229de0c4b490de9d20d8f8d41059f933' WHERE id=47;");
	db_install_execute("UPDATE graph_templates_item SET hash='cd17feb30c02fd8f21e4d4dcde04e024' WHERE id=48;");
	db_install_execute("UPDATE graph_templates_item SET hash='8723600cfd0f8a7b3f7dc1361981aabd' WHERE id=49;");
	db_install_execute("UPDATE graph_templates_item SET hash='cb06be2601b5abfb7a42fc07586de1c2' WHERE id=50;");
	db_install_execute("UPDATE graph_templates_item SET hash='55a2ee0fd511e5210ed85759171de58f' WHERE id=51;");
	db_install_execute("UPDATE graph_templates_item SET hash='704459564c84e42462e106eef20db169' WHERE id=52;");
	db_install_execute("UPDATE graph_template_input SET hash='2662ef4fbb0bf92317ffd42c7515af37' WHERE id=7;");
	db_install_execute("UPDATE graph_template_input SET hash='a6edef6624c796d3a6055305e2e3d4bf' WHERE id=8;");
	db_install_execute("UPDATE graph_template_input SET hash='b0e902db1875e392a9d7d69bfbb13515' WHERE id=9;");
	db_install_execute("UPDATE graph_template_input SET hash='24632b1d4a561e937225d0a5fbe65e41' WHERE id=10;");
	db_install_execute("UPDATE graph_templates SET hash='3109d88e6806d2ce50c025541b542499' WHERE id=6;");
	db_install_execute("UPDATE graph_templates_item SET hash='aaebb19ec522497eaaf8c87a631b7919' WHERE id=53;");
	db_install_execute("UPDATE graph_templates_item SET hash='8b54843ac9d41bce2fcedd023560ed64' WHERE id=54;");
	db_install_execute("UPDATE graph_templates_item SET hash='05927dc83e07c7d9cffef387d68f35c9' WHERE id=55;");
	db_install_execute("UPDATE graph_templates_item SET hash='d11e62225a7e7a0cdce89242002ca547' WHERE id=56;");
	db_install_execute("UPDATE graph_templates_item SET hash='6397b92032486c476b0e13a35b727041' WHERE id=57;");
	db_install_execute("UPDATE graph_templates_item SET hash='cdfa5f8f82f4c479ff7f6f54160703f6' WHERE id=58;");
	db_install_execute("UPDATE graph_templates_item SET hash='ce2a309fb9ef64f83f471895069a6f07' WHERE id=59;");
	db_install_execute("UPDATE graph_templates_item SET hash='9cbfbf57ebde435b27887f27c7d3caea' WHERE id=60;");
	db_install_execute("UPDATE graph_template_input SET hash='6d078f1d58b70ad154a89eb80fe6ab75' WHERE id=11;");
	db_install_execute("UPDATE graph_template_input SET hash='878241872dd81c68d78e6ff94871d97d' WHERE id=12;");
	db_install_execute("UPDATE graph_template_input SET hash='f8fcdc3a3f0e8ead33bd9751895a3462' WHERE id=13;");
	db_install_execute("UPDATE graph_template_input SET hash='394ab4713a34198dddb5175aa40a2b4a' WHERE id=14;");
	db_install_execute("UPDATE graph_templates SET hash='cf96dfb22b58e08bf101ca825377fa4b' WHERE id=7;");
	db_install_execute("UPDATE graph_templates_item SET hash='80e0aa956f50c261e5143273da58b8a3' WHERE id=61;");
	db_install_execute("UPDATE graph_templates_item SET hash='48fdcae893a7b7496e1a61efc3453599' WHERE id=62;");
	db_install_execute("UPDATE graph_templates_item SET hash='22f43e5fa20f2716666ba9ed9a7d1727' WHERE id=63;");
	db_install_execute("UPDATE graph_templates_item SET hash='3e86d497bcded7af7ab8408e4908e0d8' WHERE id=64;");
	db_install_execute("UPDATE graph_template_input SET hash='433f328369f9569446ddc59555a63eb8' WHERE id=15;");
	db_install_execute("UPDATE graph_template_input SET hash='a1a91c1514c65152d8cb73522ea9d4e6' WHERE id=16;");
	db_install_execute("UPDATE graph_template_input SET hash='2fb4deb1448379b27ddc64e30e70dc42' WHERE id=17;");
	db_install_execute("UPDATE graph_templates SET hash='9fe8b4da353689d376b99b2ea526cc6b' WHERE id=8;");
	db_install_execute("UPDATE graph_templates_item SET hash='ba00ecd28b9774348322ff70a96f2826' WHERE id=65;");
	db_install_execute("UPDATE graph_templates_item SET hash='8d76de808efd73c51e9a9cbd70579512' WHERE id=66;");
	db_install_execute("UPDATE graph_templates_item SET hash='304244ca63d5b09e62a94c8ec6fbda8d' WHERE id=67;");
	db_install_execute("UPDATE graph_templates_item SET hash='da1ba71a93d2ed4a2a00d54592b14157' WHERE id=68;");
	db_install_execute("UPDATE graph_template_input SET hash='592cedd465877bc61ab549df688b0b2a' WHERE id=18;");
	db_install_execute("UPDATE graph_template_input SET hash='1d51dbabb200fcea5c4b157129a75410' WHERE id=19;");
	db_install_execute("UPDATE graph_templates SET hash='fe5edd777a76d48fc48c11aded5211ef' WHERE id=9;");
	db_install_execute("UPDATE graph_templates_item SET hash='93ad2f2803b5edace85d86896620b9da' WHERE id=69;");
	db_install_execute("UPDATE graph_templates_item SET hash='e28736bf63d3a3bda03ea9f1e6ecb0f1' WHERE id=70;");
	db_install_execute("UPDATE graph_templates_item SET hash='bbdfa13adc00398eed132b1ccb4337d2' WHERE id=71;");
	db_install_execute("UPDATE graph_templates_item SET hash='2c14062c7d67712f16adde06132675d6' WHERE id=72;");
	db_install_execute("UPDATE graph_templates_item SET hash='9cf6ed48a6a54b9644a1de8c9929bd4e' WHERE id=73;");
	db_install_execute("UPDATE graph_templates_item SET hash='c9824064305b797f38feaeed2352e0e5' WHERE id=74;");
	db_install_execute("UPDATE graph_templates_item SET hash='fa1bc4eff128c4da70f5247d55b8a444' WHERE id=75;");
	db_install_execute("UPDATE graph_template_input SET hash='8cb8ed3378abec21a1819ea52dfee6a3' WHERE id=20;");
	db_install_execute("UPDATE graph_template_input SET hash='5dfcaf9fd771deb8c5430bce1562e371' WHERE id=21;");
	db_install_execute("UPDATE graph_template_input SET hash='6f3cc610315ee58bc8e0b1f272466324' WHERE id=22;");
	db_install_execute("UPDATE graph_templates SET hash='63610139d44d52b195cc375636653ebd' WHERE id=10;");
	db_install_execute("UPDATE graph_templates_item SET hash='5c94ac24bc0d6d2712cc028fa7d4c7d2' WHERE id=76;");
	db_install_execute("UPDATE graph_templates_item SET hash='8bc7f905526f62df7d5c2d8c27c143c1' WHERE id=77;");
	db_install_execute("UPDATE graph_templates_item SET hash='cd074cd2b920aab70d480c020276d45b' WHERE id=78;");
	db_install_execute("UPDATE graph_templates_item SET hash='415630f25f5384ba0c82adbdb05fe98b' WHERE id=79;");
	db_install_execute("UPDATE graph_template_input SET hash='b457a982bf46c6760e6ef5f5d06d41fb' WHERE id=23;");
	db_install_execute("UPDATE graph_template_input SET hash='bd4a57adf93c884815b25a8036b67f98' WHERE id=24;");
	db_install_execute("UPDATE graph_templates SET hash='5107ec0206562e77d965ce6b852ef9d4' WHERE id=11;");
	db_install_execute("UPDATE graph_templates_item SET hash='d77d2050be357ab067666a9485426e6b' WHERE id=80;");
	db_install_execute("UPDATE graph_templates_item SET hash='13d22f5a0eac6d97bf6c97d7966f0a00' WHERE id=81;");
	db_install_execute("UPDATE graph_templates_item SET hash='8580230d31d2851ec667c296a665cbf9' WHERE id=82;");
	db_install_execute("UPDATE graph_templates_item SET hash='b5b7d9b64e7640aa51dbf58c69b86d15' WHERE id=83;");
	db_install_execute("UPDATE graph_templates_item SET hash='2ec10edf4bfaa866b7efd544d4c3f446' WHERE id=84;");
	db_install_execute("UPDATE graph_templates_item SET hash='b65666f0506c0c70966f493c19607b93' WHERE id=85;");
	db_install_execute("UPDATE graph_templates_item SET hash='6c73575c74506cfc75b89c4276ef3455' WHERE id=86;");
	db_install_execute("UPDATE graph_template_input SET hash='d7cdb63500c576e0f9f354de42c6cf3a' WHERE id=25;");
	db_install_execute("UPDATE graph_template_input SET hash='a23152f5ec02e7762ca27608c0d89f6c' WHERE id=26;");
	db_install_execute("UPDATE graph_template_input SET hash='2cc5d1818da577fba15115aa18f64d85' WHERE id=27;");
	db_install_execute("UPDATE graph_templates SET hash='6992ed4df4b44f3d5595386b8298f0ec' WHERE id=12;");
	db_install_execute("UPDATE graph_templates_item SET hash='5fa7c2317f19440b757ab2ea1cae6abc' WHERE id=95;");
	db_install_execute("UPDATE graph_templates_item SET hash='b1d18060bfd3f68e812c508ff4ac94ed' WHERE id=96;");
	db_install_execute("UPDATE graph_templates_item SET hash='780b6f0850aaf9431d1c246c55143061' WHERE id=97;");
	db_install_execute("UPDATE graph_templates_item SET hash='2d54a7e7bb45e6c52d97a09e24b7fba7' WHERE id=98;");
	db_install_execute("UPDATE graph_templates_item SET hash='40206367a3c192b836539f49801a0b15' WHERE id=99;");
	db_install_execute("UPDATE graph_templates_item SET hash='7ee72e2bb3722d4f8a7f9c564e0dd0d0' WHERE id=100;");
	db_install_execute("UPDATE graph_templates_item SET hash='c8af33b949e8f47133ee25e63c91d4d0' WHERE id=101;");
	db_install_execute("UPDATE graph_templates_item SET hash='568128a16723d1195ce6a234d353ce00' WHERE id=102;");
	db_install_execute("UPDATE graph_template_input SET hash='6273c71cdb7ed4ac525cdbcf6180918c' WHERE id=30;");
	db_install_execute("UPDATE graph_template_input SET hash='5e62dbea1db699f1bda04c5863e7864d' WHERE id=31;");
	db_install_execute("UPDATE graph_templates SET hash='be275639d5680e94c72c0ebb4e19056d' WHERE id=13;");
	db_install_execute("UPDATE graph_templates_item SET hash='7517a40d478e28ed88ba2b2a65e16b57' WHERE id=103;");
	db_install_execute("UPDATE graph_templates_item SET hash='df0c8b353d26c334cb909dc6243957c5' WHERE id=104;");
	db_install_execute("UPDATE graph_templates_item SET hash='c41a4cf6fefaf756a24f0a9510580724' WHERE id=105;");
	db_install_execute("UPDATE graph_templates_item SET hash='9efa8f01c6ed11364a21710ff170f422' WHERE id=106;");
	db_install_execute("UPDATE graph_templates_item SET hash='95d6e4e5110b456f34324f7941d08318' WHERE id=107;");
	db_install_execute("UPDATE graph_templates_item SET hash='0c631bfc0785a9cca68489ea87a6c3da' WHERE id=108;");
	db_install_execute("UPDATE graph_templates_item SET hash='3468579d3b671dfb788696df7dcc1ec9' WHERE id=109;");
	db_install_execute("UPDATE graph_templates_item SET hash='c3ddfdaa65449f99b7f1a735307f9abe' WHERE id=110;");
	db_install_execute("UPDATE graph_template_input SET hash='f45def7cad112b450667aa67262258cb' WHERE id=35;");
	db_install_execute("UPDATE graph_template_input SET hash='f8c361a8c8b7ad80e8be03ba7ea5d0d6' WHERE id=36;");
	db_install_execute("UPDATE graph_templates SET hash='f17e4a77b8496725dc924b8c35b60036' WHERE id=14;");
	db_install_execute("UPDATE graph_templates_item SET hash='4c64d5c1ce8b5d8b94129c23b46a5fd6' WHERE id=111;");
	db_install_execute("UPDATE graph_templates_item SET hash='5c1845c9bd1af684a3c0ad843df69e3e' WHERE id=112;");
	db_install_execute("UPDATE graph_templates_item SET hash='e5169563f3f361701902a8da3ac0c77f' WHERE id=113;");
	db_install_execute("UPDATE graph_templates_item SET hash='35e87262efa521edbb1fd27f09c036f5' WHERE id=114;");
	db_install_execute("UPDATE graph_templates_item SET hash='53069d7dba4c31b338f609bea4cd16f3' WHERE id=115;");
	db_install_execute("UPDATE graph_templates_item SET hash='d9c102579839c5575806334d342b50de' WHERE id=116;");
	db_install_execute("UPDATE graph_templates_item SET hash='dc1897c3249dbabe269af49cee92f8c0' WHERE id=117;");
	db_install_execute("UPDATE graph_templates_item SET hash='ccd21fe0b5a8c24057f1eff4a6b66391' WHERE id=118;");
	db_install_execute("UPDATE graph_template_input SET hash='03d11dce695963be30bd744bd6cbac69' WHERE id=37;");
	db_install_execute("UPDATE graph_template_input SET hash='9cbc515234779af4bf6cdf71a81c556a' WHERE id=38;");
	db_install_execute("UPDATE graph_templates SET hash='46bb77f4c0c69671980e3c60d3f22fa9' WHERE id=15;");
	db_install_execute("UPDATE graph_templates_item SET hash='ab09d41c358f6b8a9d0cad4eccc25529' WHERE id=119;");
	db_install_execute("UPDATE graph_templates_item SET hash='5d5b8d8fbe751dc9c86ee86f85d7433b' WHERE id=120;");
	db_install_execute("UPDATE graph_templates_item SET hash='4822a98464c6da2afff10c6d12df1831' WHERE id=121;");
	db_install_execute("UPDATE graph_templates_item SET hash='fc6fbf2a964bea0b3c88ed0f18616aa7' WHERE id=122;");
	db_install_execute("UPDATE graph_template_input SET hash='2c4d561ee8132a8dda6de1104336a6ec' WHERE id=39;");
	db_install_execute("UPDATE graph_template_input SET hash='31fed1f9e139d4897d0460b10fb7be94' WHERE id=44;");
	db_install_execute("UPDATE graph_templates SET hash='8e77a3036312fd0fda32eaea2b5f141b' WHERE id=16;");
	db_install_execute("UPDATE graph_templates_item SET hash='e4094625d5443b4c87f9a87ba616a469' WHERE id=123;");
	db_install_execute("UPDATE graph_templates_item SET hash='ae68425cd10e8a6623076b2e6859a6aa' WHERE id=124;");
	db_install_execute("UPDATE graph_templates_item SET hash='40b8e14c6568b3f6be6a5d89d6a9f061' WHERE id=125;");
	db_install_execute("UPDATE graph_templates_item SET hash='4afbdc3851c03e206672930746b1a5e2' WHERE id=126;");
	db_install_execute("UPDATE graph_templates_item SET hash='ea47d2b5516e334bc5f6ce1698a3ae76' WHERE id=127;");
	db_install_execute("UPDATE graph_templates_item SET hash='899c48a2f79ea3ad4629aff130d0f371' WHERE id=128;");
	db_install_execute("UPDATE graph_templates_item SET hash='ab474d7da77e9ec1f6a1d45c602580cd' WHERE id=129;");
	db_install_execute("UPDATE graph_templates_item SET hash='e143f8b4c6d4eeb6a28b052e6b8ce5a9' WHERE id=130;");
	db_install_execute("UPDATE graph_template_input SET hash='6e1cf7addc0cc419aa903552e3eedbea' WHERE id=40;");
	db_install_execute("UPDATE graph_template_input SET hash='7ea2aa0656f7064d25a36135dd0e9082' WHERE id=41;");
	db_install_execute("UPDATE graph_templates SET hash='5892c822b1bb2d38589b6c27934b9936' WHERE id=17;");
	db_install_execute("UPDATE graph_templates_item SET hash='facfeeb6fc2255ba2985b2d2f695d78a' WHERE id=131;");
	db_install_execute("UPDATE graph_templates_item SET hash='2470e43034a5560260d79084432ed14f' WHERE id=132;");
	db_install_execute("UPDATE graph_templates_item SET hash='e9e645f07bde92b52d93a7a1f65efb30' WHERE id=133;");
	db_install_execute("UPDATE graph_templates_item SET hash='bdfe0d66103211cfdaa267a44a98b092' WHERE id=134;");
	db_install_execute("UPDATE graph_template_input SET hash='63480bca78a38435f24a5b5d5ed050d7' WHERE id=42;");
	db_install_execute("UPDATE graph_templates SET hash='9a5e6d7781cc1bd6cf24f64dd6ffb423' WHERE id=18;");
	db_install_execute("UPDATE graph_templates_item SET hash='098b10c13a5701ddb7d4d1d2e2b0fdb7' WHERE id=139;");
	db_install_execute("UPDATE graph_templates_item SET hash='1dbda412a9926b0ee5c025aa08f3b230' WHERE id=140;");
	db_install_execute("UPDATE graph_templates_item SET hash='725c45917146807b6a4257fc351f2bae' WHERE id=141;");
	db_install_execute("UPDATE graph_templates_item SET hash='4e336fdfeb84ce65f81ded0e0159a5e0' WHERE id=142;");
	db_install_execute("UPDATE graph_template_input SET hash='bb9d83a02261583bc1f92d9e66ea705d' WHERE id=45;");
	db_install_execute("UPDATE graph_template_input SET hash='51196222ed37b44236d9958116028980' WHERE id=46;");
	db_install_execute("UPDATE graph_templates SET hash='0dd0438d5e6cad6776f79ecaa96fb708' WHERE id=19;");
	db_install_execute("UPDATE graph_templates_item SET hash='7dab7a3ceae2addd1cebddee6c483e7c' WHERE id=143;");
	db_install_execute("UPDATE graph_templates_item SET hash='aea239f3ceea8c63d02e453e536190b8' WHERE id=144;");
	db_install_execute("UPDATE graph_templates_item SET hash='a0efae92968a6d4ae099b676e0f1430e' WHERE id=145;");
	db_install_execute("UPDATE graph_templates_item SET hash='4fd5ba88be16e3d513c9231b78ccf0e1' WHERE id=146;");
	db_install_execute("UPDATE graph_templates_item SET hash='d2e98e51189e1d9be8888c3d5c5a4029' WHERE id=147;");
	db_install_execute("UPDATE graph_templates_item SET hash='12829294ee3958f4a31a58a61228e027' WHERE id=148;");
	db_install_execute("UPDATE graph_templates_item SET hash='4b7e8755b0f2253723c1e9fb21fd37b1' WHERE id=149;");
	db_install_execute("UPDATE graph_templates_item SET hash='cbb19ffd7a0ead2bf61512e86d51ee8e' WHERE id=150;");
	db_install_execute("UPDATE graph_templates_item SET hash='37b4cbed68f9b77e49149343069843b4' WHERE id=151;");
	db_install_execute("UPDATE graph_templates_item SET hash='5eb7532200f2b5cc93e13743a7db027c' WHERE id=152;");
	db_install_execute("UPDATE graph_templates_item SET hash='b0f9f602fbeaaff090ea3f930b46c1c7' WHERE id=153;");
	db_install_execute("UPDATE graph_templates_item SET hash='06477f7ea46c63272cee7253e7cd8760' WHERE id=154;");
	db_install_execute("UPDATE graph_template_input SET hash='fd26b0f437b75715d6dff983e7efa710' WHERE id=47;");
	db_install_execute("UPDATE graph_template_input SET hash='a463dd46862605c90ea60ccad74188db' WHERE id=48;");
	db_install_execute("UPDATE graph_template_input SET hash='9977dd7a41bcf0f0c02872b442c7492e' WHERE id=49;");
	db_install_execute("UPDATE graph_templates SET hash='b18a3742ebea48c6198412b392d757fc' WHERE id=20;");
	db_install_execute("UPDATE graph_templates_item SET hash='6877a2a5362a9390565758b08b9b37f7' WHERE id=159;");
	db_install_execute("UPDATE graph_templates_item SET hash='a978834f3d02d833d3d2def243503bf2' WHERE id=160;");
	db_install_execute("UPDATE graph_templates_item SET hash='7422d87bc82de20a4333bd2f6460b2d4' WHERE id=161;");
	db_install_execute("UPDATE graph_templates_item SET hash='4d52762859a3fec297ebda0e7fd760d9' WHERE id=162;");
	db_install_execute("UPDATE graph_templates_item SET hash='999d4ed1128ff03edf8ea47e56d361dd' WHERE id=163;");
	db_install_execute("UPDATE graph_templates_item SET hash='3dfcd7f8c7a760ac89d34398af79b979' WHERE id=164;");
	db_install_execute("UPDATE graph_templates_item SET hash='217be75e28505c8f8148dec6b71b9b63' WHERE id=165;");
	db_install_execute("UPDATE graph_templates_item SET hash='69b89e1c5d6fc6182c93285b967f970a' WHERE id=166;");
	db_install_execute("UPDATE graph_template_input SET hash='a7a69bbdf6890d6e6eaa7de16e815ec6' WHERE id=51;");
	db_install_execute("UPDATE graph_template_input SET hash='0072b613a33f1fae5ce3e5903dec8fdb' WHERE id=52;");
	db_install_execute("UPDATE graph_templates SET hash='8e7c8a511652fe4a8e65c69f3d34779d' WHERE id=21;");
	db_install_execute("UPDATE graph_templates_item SET hash='a751838f87068e073b95be9555c57bde' WHERE id=171;");
	db_install_execute("UPDATE graph_templates_item SET hash='3b13eb2e542fe006c9bf86947a6854fa' WHERE id=170;");
	db_install_execute("UPDATE graph_templates_item SET hash='8ef3e7fb7ce962183f489725939ea40f' WHERE id=169;");
	db_install_execute("UPDATE graph_templates_item SET hash='6ca2161c37b0118786dbdb46ad767e5d' WHERE id=167;");
	db_install_execute("UPDATE graph_templates_item SET hash='5d6dff9c14c71dc1ebf83e87f1c25695' WHERE id=172;");
	db_install_execute("UPDATE graph_templates_item SET hash='b27cb9a158187d29d17abddc6fdf0f15' WHERE id=173;");
	db_install_execute("UPDATE graph_templates_item SET hash='6c0555013bb9b964e51d22f108dae9b0' WHERE id=174;");
	db_install_execute("UPDATE graph_templates_item SET hash='42ce58ec17ef5199145fbf9c6ee39869' WHERE id=175;");
	db_install_execute("UPDATE graph_templates_item SET hash='9bdff98f2394f666deea028cbca685f3' WHERE id=176;");
	db_install_execute("UPDATE graph_templates_item SET hash='fb831fefcf602bc31d9d24e8e456c2e6' WHERE id=177;");
	db_install_execute("UPDATE graph_templates_item SET hash='5a958d56785a606c08200ef8dbf8deef' WHERE id=178;");
	db_install_execute("UPDATE graph_templates_item SET hash='5ce67a658cec37f526dc84ac9e08d6e7' WHERE id=179;");
	db_install_execute("UPDATE graph_template_input SET hash='940beb0f0344e37f4c6cdfc17d2060bc' WHERE id=53;");
	db_install_execute("UPDATE graph_template_input SET hash='7b0674dd447a9badf0d11bec688028a8' WHERE id=54;");
	db_install_execute("UPDATE graph_templates SET hash='06621cd4a9289417cadcb8f9b5cfba80' WHERE id=22;");
	db_install_execute("UPDATE graph_templates_item SET hash='7e04a041721df1f8828381a9ea2f2154' WHERE id=180;");
	db_install_execute("UPDATE graph_templates_item SET hash='afc8bca6b1b3030a6d71818272336c6c' WHERE id=181;");
	db_install_execute("UPDATE graph_templates_item SET hash='6ac169785f5aeaf1cc5cdfd38dfcfb6c' WHERE id=182;");
	db_install_execute("UPDATE graph_templates_item SET hash='178c0a0ce001d36a663ff6f213c07505' WHERE id=183;");
	db_install_execute("UPDATE graph_templates_item SET hash='8e3268c0abde7550616bff719f10ee2f' WHERE id=184;");
	db_install_execute("UPDATE graph_templates_item SET hash='18891392b149de63b62c4258a68d75f8' WHERE id=185;");
	db_install_execute("UPDATE graph_templates_item SET hash='dfc9d23de0182c9967ae3dabdfa55a16' WHERE id=186;");
	db_install_execute("UPDATE graph_templates_item SET hash='c47ba64e2e5ea8bf84aceec644513176' WHERE id=187;");
	db_install_execute("UPDATE graph_templates_item SET hash='617d10dff9bbc3edd9d733d9c254da76' WHERE id=204;");
	db_install_execute("UPDATE graph_templates_item SET hash='9269a66502c34d00ac3c8b1fcc329ac6' WHERE id=205;");
	db_install_execute("UPDATE graph_templates_item SET hash='d45deed7e1ad8350f3b46b537ae0a933' WHERE id=206;");
	db_install_execute("UPDATE graph_templates_item SET hash='2f64cf47dc156e8c800ae03c3b893e3c' WHERE id=207;");
	db_install_execute("UPDATE graph_templates_item SET hash='57434bef8cb21283c1a73f055b0ada19' WHERE id=208;");
	db_install_execute("UPDATE graph_templates_item SET hash='660a1b9365ccbba356fd142faaec9f04' WHERE id=209;");
	db_install_execute("UPDATE graph_templates_item SET hash='28c5297bdaedcca29acf245ef4bbed9e' WHERE id=210;");
	db_install_execute("UPDATE graph_templates_item SET hash='99098604fd0c78fd7dabac8f40f1fb29' WHERE id=211;");
	db_install_execute("UPDATE graph_template_input SET hash='fa83cd3a3b4271b644cb6459ea8c35dc' WHERE id=55;");
	db_install_execute("UPDATE graph_template_input SET hash='7946e8ee1e38a65462b85e31a15e35e5' WHERE id=56;");
	db_install_execute("UPDATE graph_template_input SET hash='e5acdd5368137c408d56ecf55b0e077c' WHERE id=61;");
	db_install_execute("UPDATE graph_template_input SET hash='a028e586e5fae667127c655fe0ac67f0' WHERE id=62;");
	db_install_execute("UPDATE graph_templates SET hash='e0d1625a1f4776a5294583659d5cee15' WHERE id=23;");
	db_install_execute("UPDATE graph_templates_item SET hash='9d052e7d632c479737fbfaced0821f79' WHERE id=188;");
	db_install_execute("UPDATE graph_templates_item SET hash='9b9fa6268571b6a04fa4411d8e08c730' WHERE id=189;");
	db_install_execute("UPDATE graph_templates_item SET hash='8e8f2fbeb624029cbda1d2a6ddd991ba' WHERE id=190;");
	db_install_execute("UPDATE graph_templates_item SET hash='c76495beb1ed01f0799838eb8a893124' WHERE id=191;");
	db_install_execute("UPDATE graph_templates_item SET hash='d4e5f253f01c3ea77182c5a46418fc44' WHERE id=192;");
	db_install_execute("UPDATE graph_templates_item SET hash='526a96add143da021c5f00d8764a6c12' WHERE id=193;");
	db_install_execute("UPDATE graph_templates_item SET hash='81eeb46f451212f00fd7caee42a81c0b' WHERE id=194;");
	db_install_execute("UPDATE graph_templates_item SET hash='089e4d1c3faeb00fd5dcc9622b06d656' WHERE id=195;");
	db_install_execute("UPDATE graph_template_input SET hash='00ae916640272f5aca54d73ae34c326b' WHERE id=57;");
	db_install_execute("UPDATE graph_template_input SET hash='1bc1652f82488ebfb7242c65d2ffa9c7' WHERE id=58;");
	db_install_execute("UPDATE graph_templates SET hash='10ca5530554da7b73dc69d291bf55d38' WHERE id=24;");
	db_install_execute("UPDATE graph_templates_item SET hash='fe66cb973966d22250de073405664200' WHERE id=196;");
	db_install_execute("UPDATE graph_templates_item SET hash='1ba3fc3466ad32fdd2669cac6cad6faa' WHERE id=197;");
	db_install_execute("UPDATE graph_templates_item SET hash='f810154d3a934c723c21659e66199cdf' WHERE id=198;");
	db_install_execute("UPDATE graph_templates_item SET hash='98a161df359b01304346657ff1a9d787' WHERE id=199;");
	db_install_execute("UPDATE graph_templates_item SET hash='d5e55eaf617ad1f0516f6343b3f07c5e' WHERE id=200;");
	db_install_execute("UPDATE graph_templates_item SET hash='9fde6b8c84089b9f9044e681162e7567' WHERE id=201;");
	db_install_execute("UPDATE graph_templates_item SET hash='9a3510727c3d9fa7e2e7a015783a99b3' WHERE id=202;");
	db_install_execute("UPDATE graph_templates_item SET hash='451afd23f2cb59ab9b975fd6e2735815' WHERE id=203;");
	db_install_execute("UPDATE graph_template_input SET hash='e3177d0e56278de320db203f32fb803d' WHERE id=59;");
	db_install_execute("UPDATE graph_template_input SET hash='4f20fba2839764707f1c3373648c5fef' WHERE id=60;");
	db_install_execute("UPDATE graph_templates SET hash='df244b337547b434b486662c3c5c7472' WHERE id=25;");
	db_install_execute("UPDATE graph_templates_item SET hash='de3eefd6d6c58afabdabcaf6c0168378' WHERE id=212;");
	db_install_execute("UPDATE graph_templates_item SET hash='1a80fa108f5c46eecb03090c65bc9a12' WHERE id=213;");
	db_install_execute("UPDATE graph_templates_item SET hash='fe458892e7faa9d232e343d911e845f3' WHERE id=214;");
	db_install_execute("UPDATE graph_templates_item SET hash='175c0a68689bebc38aad2fbc271047b3' WHERE id=215;");
	db_install_execute("UPDATE graph_templates_item SET hash='1bf2283106510491ddf3b9c1376c0b31' WHERE id=216;");
	db_install_execute("UPDATE graph_templates_item SET hash='c5202f1690ffe45600c0d31a4a804f67' WHERE id=217;");
	db_install_execute("UPDATE graph_templates_item SET hash='eb9794e3fdafc2b74f0819269569ed40' WHERE id=218;");
	db_install_execute("UPDATE graph_templates_item SET hash='6bcedd61e3ccf7518ca431940c93c439' WHERE id=219;");
	db_install_execute("UPDATE graph_template_input SET hash='2764a4f142ba9fd95872106a1b43541e' WHERE id=63;");
	db_install_execute("UPDATE graph_template_input SET hash='f73f7ddc1f4349356908122093dbfca2' WHERE id=64;");
	db_install_execute("UPDATE graph_templates SET hash='7489e44466abee8a7d8636cb2cb14a1a' WHERE id=26;");
	db_install_execute("UPDATE graph_templates_item SET hash='b7b381d47972f836785d338a3bef6661' WHERE id=303;");
	db_install_execute("UPDATE graph_templates_item SET hash='36fa8063df3b07cece878d54443db727' WHERE id=304;");
	db_install_execute("UPDATE graph_templates_item SET hash='2c35b5cae64c5f146a55fcb416dd14b5' WHERE id=305;");
	db_install_execute("UPDATE graph_templates_item SET hash='16d6a9a7f608762ad65b0841e5ef4e9c' WHERE id=306;");
	db_install_execute("UPDATE graph_templates_item SET hash='d80e4a4901ab86ee39c9cc613e13532f' WHERE id=307;");
	db_install_execute("UPDATE graph_templates_item SET hash='567c2214ee4753aa712c3d101ea49a5d' WHERE id=308;");
	db_install_execute("UPDATE graph_templates_item SET hash='ba0b6a9e316ef9be66abba68b80f7587' WHERE id=309;");
	db_install_execute("UPDATE graph_templates_item SET hash='4b8e4a6bf2757f04c3e3a088338a2f7a' WHERE id=310;");
	db_install_execute("UPDATE graph_template_input SET hash='86bd8819d830a81d64267761e1fd8ec4' WHERE id=65;");
	db_install_execute("UPDATE graph_template_input SET hash='6c8967850102202de166951e4411d426' WHERE id=66;");
	db_install_execute("UPDATE graph_templates SET hash='c6bb62bedec4ab97f9db9fd780bd85a6' WHERE id=27;");
	db_install_execute("UPDATE graph_templates_item SET hash='8536e034ab5268a61473f1ff2f6bd88f' WHERE id=317;");
	db_install_execute("UPDATE graph_templates_item SET hash='d478a76de1df9edf896c9ce51506c483' WHERE id=316;");
	db_install_execute("UPDATE graph_templates_item SET hash='42537599b5fb8ea852240b58a58633de' WHERE id=315;");
	db_install_execute("UPDATE graph_templates_item SET hash='87e10f9942b625aa323a0f39b60058e7' WHERE id=318;");
	db_install_execute("UPDATE graph_template_input SET hash='bdad718851a52b82eca0a310b0238450' WHERE id=67;");
	db_install_execute("UPDATE graph_template_input SET hash='e7b578e12eb8a82627557b955fd6ebd4' WHERE id=68;");
	db_install_execute("UPDATE graph_templates SET hash='e8462bbe094e4e9e814d4e681671ea82' WHERE id=28;");
	db_install_execute("UPDATE graph_templates_item SET hash='38f6891b0db92aa8950b4ce7ae902741' WHERE id=319;");
	db_install_execute("UPDATE graph_templates_item SET hash='af13152956a20aa894ef4a4067b88f63' WHERE id=320;");
	db_install_execute("UPDATE graph_templates_item SET hash='1b2388bbede4459930c57dc93645284e' WHERE id=321;");
	db_install_execute("UPDATE graph_templates_item SET hash='6407dc226db1d03be9730f4d6f3eeccf' WHERE id=322;");
	db_install_execute("UPDATE graph_template_input SET hash='37d09fb7ce88ecec914728bdb20027f3' WHERE id=69;");
	db_install_execute("UPDATE graph_template_input SET hash='699bd7eff7ba0c3520db3692103a053d' WHERE id=70;");
	db_install_execute("UPDATE graph_templates SET hash='62205afbd4066e5c4700338841e3901e' WHERE id=29;");
	db_install_execute("UPDATE graph_templates_item SET hash='fca6a530c8f37476b9004a90b42ee988' WHERE id=323;");
	db_install_execute("UPDATE graph_templates_item SET hash='5acebbde3dc65e02f8fda03955852fbe' WHERE id=324;");
	db_install_execute("UPDATE graph_templates_item SET hash='311079ffffac75efaab2837df8123122' WHERE id=325;");
	db_install_execute("UPDATE graph_templates_item SET hash='724d27007ebf31016cfa5530fee1b867' WHERE id=326;");
	db_install_execute("UPDATE graph_template_input SET hash='df905e159d13a5abed8a8a7710468831' WHERE id=71;");
	db_install_execute("UPDATE graph_template_input SET hash='8ca9e3c65c080dbf74a59338d64b0c14' WHERE id=72;");
	db_install_execute("UPDATE graph_templates SET hash='e3780a13b0f7a3f85a44b70cd4d2fd36' WHERE id=30;");
	db_install_execute("UPDATE graph_templates_item SET hash='5258970186e4407ed31cca2782650c45' WHERE id=360;");
	db_install_execute("UPDATE graph_templates_item SET hash='da26dd92666cb840f8a70e2ec5e90c07' WHERE id=359;");
	db_install_execute("UPDATE graph_templates_item SET hash='803b96bcaec33148901b4b562d9d2344' WHERE id=358;");
	db_install_execute("UPDATE graph_templates_item SET hash='7d08b996bde9cdc7efa650c7031137b4' WHERE id=361;");
	db_install_execute("UPDATE graph_template_input SET hash='69ad68fc53af03565aef501ed5f04744' WHERE id=73;");
	db_install_execute("UPDATE graph_templates SET hash='1742b2066384637022d178cc5072905a' WHERE id=31;");
	db_install_execute("UPDATE graph_templates_item SET hash='918e6e7d41bb4bae0ea2937b461742a4' WHERE id=362;");
	db_install_execute("UPDATE graph_templates_item SET hash='f19fbd06c989ea85acd6b4f926e4a456' WHERE id=363;");
	db_install_execute("UPDATE graph_templates_item SET hash='fc150a15e20c57e11e8d05feca557ef9' WHERE id=364;");
	db_install_execute("UPDATE graph_templates_item SET hash='ccbd86e03ccf07483b4d29e63612fb18' WHERE id=365;");
	db_install_execute("UPDATE graph_templates_item SET hash='964c5c30cd05eaf5a49c0377d173de86' WHERE id=366;");
	db_install_execute("UPDATE graph_templates_item SET hash='b1a6fb775cf62e79e1c4bc4933c7e4ce' WHERE id=367;");
	db_install_execute("UPDATE graph_templates_item SET hash='721038182a872ab266b5cf1bf7f7755c' WHERE id=368;");
	db_install_execute("UPDATE graph_templates_item SET hash='2302f80c2c70b897d12182a1fc11ecd6' WHERE id=369;");
	db_install_execute("UPDATE graph_templates_item SET hash='4ffc7af8533d103748316752b70f8e3c' WHERE id=370;");
	db_install_execute("UPDATE graph_templates_item SET hash='64527c4b6eeeaf627acc5117ff2180fd' WHERE id=371;");
	db_install_execute("UPDATE graph_templates_item SET hash='d5bbcbdbf83ae858862611ac6de8fc62' WHERE id=372;");
	db_install_execute("UPDATE graph_template_input SET hash='562726cccdb67d5c6941e9e826ef4ef5' WHERE id=74;");
	db_install_execute("UPDATE graph_template_input SET hash='82426afec226f8189c8928e7f083f80f' WHERE id=75;");
	db_install_execute("UPDATE graph_templates SET hash='13b47e10b2d5db45707d61851f69c52b' WHERE id=32;");
	db_install_execute("UPDATE graph_templates_item SET hash='0e715933830112c23c15f7e3463f77b6' WHERE id=380;");
	db_install_execute("UPDATE graph_templates_item SET hash='979fff9d691ca35e3f4b3383d9cae43f' WHERE id=379;");
	db_install_execute("UPDATE graph_templates_item SET hash='5bff63207c7bf076d76ff3036b5dad54' WHERE id=378;");
	db_install_execute("UPDATE graph_templates_item SET hash='4a381a8e87d4db1ac99cf8d9078266d3' WHERE id=377;");
	db_install_execute("UPDATE graph_templates_item SET hash='88d3094d5dc2164cbf2f974aeb92f051' WHERE id=376;");
	db_install_execute("UPDATE graph_templates_item SET hash='54782f71929e7d1734ed5ad4b8dda50d' WHERE id=375;");
	db_install_execute("UPDATE graph_templates_item SET hash='55083351cd728b82cc4dde68eb935700' WHERE id=374;");
	db_install_execute("UPDATE graph_templates_item SET hash='1995d8c23e7d8e1efa2b2c55daf3c5a7' WHERE id=373;");
	db_install_execute("UPDATE graph_templates_item SET hash='db7c15d253ca666601b3296f2574edc9' WHERE id=384;");
	db_install_execute("UPDATE graph_templates_item SET hash='5b43e4102600ad75379c5afd235099c4' WHERE id=383;");
	db_install_execute("UPDATE graph_template_input SET hash='f28013abf8e5813870df0f4111a5e695' WHERE id=77;");
	db_install_execute("UPDATE graph_template_input SET hash='69a23877302e7d142f254b208c58b596' WHERE id=76;");
	db_install_execute("UPDATE graph_templates SET hash='8ad6790c22b693680e041f21d62537ac' WHERE id=33;");
	db_install_execute("UPDATE graph_templates_item SET hash='fdaec5b9227522c758ad55882c483a83' WHERE id=385;");
	db_install_execute("UPDATE graph_templates_item SET hash='6824d29c3f13fe1e849f1dbb8377d3f1' WHERE id=386;");
	db_install_execute("UPDATE graph_templates_item SET hash='54e3971b3dd751dd2509f62721c12b41' WHERE id=387;");
	db_install_execute("UPDATE graph_templates_item SET hash='cf8c9f69878f0f595d583eac109a9be1' WHERE id=388;");
	db_install_execute("UPDATE graph_templates_item SET hash='de265acbbfa99eb4b3e9f7e90c7feeda' WHERE id=389;");
	db_install_execute("UPDATE graph_templates_item SET hash='777aa88fb0a79b60d081e0e3759f1cf7' WHERE id=390;");
	db_install_execute("UPDATE graph_templates_item SET hash='66bfdb701c8eeadffe55e926d6e77e71' WHERE id=391;");
	db_install_execute("UPDATE graph_templates_item SET hash='3ff8dba1ca6279692b3fcabed0bc2631' WHERE id=392;");
	db_install_execute("UPDATE graph_templates_item SET hash='d6041d14f9c8fb9b7ddcf3556f763c03' WHERE id=393;");
	db_install_execute("UPDATE graph_templates_item SET hash='76ae747365553a02313a2d8a0dd55c8a' WHERE id=394;");
	db_install_execute("UPDATE graph_template_input SET hash='8644b933b6a09dde6c32ff24655eeb9a' WHERE id=78;");
	db_install_execute("UPDATE graph_template_input SET hash='49c4b4800f3e638a6f6bb681919aea80' WHERE id=79;");
	db_install_execute("UPDATE snmp_query SET hash='d75e406fdeca4fcef45b8be3a9a63cbc' WHERE id=1;");
	db_install_execute("UPDATE snmp_query_graph SET hash='a4b829746fb45e35e10474c36c69c0cf' WHERE id=2;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='6537b3209e0697fbec278e94e7317b52' WHERE id=49;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='6d3f612051016f48c951af8901720a1c' WHERE id=50;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='62bc981690576d0b2bd0041ec2e4aa6f' WHERE id=51;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='adb270d55ba521d205eac6a21478804a' WHERE id=52;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='299d3434851fc0d5c0e105429069709d' WHERE id=21;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='8c8860b17fd67a9a500b4cb8b5e19d4b' WHERE id=22;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='d96360ae5094e5732e7e7496ceceb636' WHERE id=23;");
	db_install_execute("UPDATE snmp_query_graph SET hash='01e33224f8b15997d3d09d6b1bf83e18' WHERE id=3;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='77065435f3bbb2ff99bc3b43b81de8fe' WHERE id=54;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='240d8893092619c97a54265e8d0b86a1' WHERE id=55;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='4b200ecf445bdeb4c84975b74991df34' WHERE id=56;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='d6da3887646078e4d01fe60a123c2179' WHERE id=57;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='750a290cadc3dc60bb682a5c5f47df16' WHERE id=24;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='bde195eecc256c42ca9725f1f22c1dc0' WHERE id=25;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='d9e97d22689e4ffddaca23b46f2aa306' WHERE id=26;");
	db_install_execute("UPDATE snmp_query_graph SET hash='1e6edee3115c42d644dbd014f0577066' WHERE id=4;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='ce7769b97d80ca31d21f83dc18ba93c2' WHERE id=59;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='1ee1f9717f3f4771f7f823ca5a8b83dd' WHERE id=60;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='a7dbd54604533b592d4fae6e67587e32' WHERE id=61;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='b148fa7199edcf06cd71c89e5c5d7b63' WHERE id=62;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='48ceaba62e0c2671a810a7f1adc5f751' WHERE id=27;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='d6258884bed44abe46d264198adc7c5d' WHERE id=28;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='6eb58d9835b2b86222306d6ced9961d9' WHERE id=29;");
	db_install_execute("UPDATE snmp_query_graph SET hash='ab93b588c29731ab15db601ca0bc9dec' WHERE id=9;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='e1be83d708ed3c0b8715ccb6517a0365' WHERE id=88;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='c582d3b37f19e4a703d9bf4908dc6548' WHERE id=86;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='57a9ae1f197498ca8dcde90194f61cbc' WHERE id=89;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='0110e120981c7ff15304e4a85cb42cbe' WHERE id=90;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='ce0b9c92a15759d3ddbd7161d26a98b7' WHERE id=91;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='0a5eb36e98c04ad6be8e1ef66caeed3c' WHERE id=34;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='4c4386a96e6057b7bd0b78095209ddfa' WHERE id=35;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='fd3a384768b0388fa64119fe2f0cc113' WHERE id=36;");
	db_install_execute("UPDATE snmp_query_graph SET hash='ae34f5f385bed8c81a158bf3030f1089' WHERE id=13;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='7e093c535fa3d810fa76fc3d8c80c94b' WHERE id=75;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='084efd82bbddb69fb2ac9bd0b0f16ac6' WHERE id=74;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='14aa2dead86bbad0f992f1514722c95e' WHERE id=72;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='70390712158c3c5052a7d830fb456489' WHERE id=73;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='87a659326af8c75158e5142874fd74b0' WHERE id=70;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='49dca5592ac26ff149a4fbd18d690644' WHERE id=15;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='bda15298139ad22bdc8a3b0952d4e3ab' WHERE id=16;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='29e48483d0471fcd996bfb702a5960aa' WHERE id=17;");
	db_install_execute("UPDATE snmp_query_graph SET hash='1e16a505ddefb40356221d7a50619d91' WHERE id=14;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='8d820d091ec1a9683cfa74a462f239ee' WHERE id=82;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='2e8b27c63d98249096ad5bc320787f43' WHERE id=81;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='e85ddc56efa677b70448f9e931360b77' WHERE id=85;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='37bb8c5b38bb7e89ec88ea7ccacf44d4' WHERE id=84;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='62a47c18be10f273a5f5a13a76b76f54' WHERE id=83;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='3f42d358965cb94ce4f708b59e04f82b' WHERE id=18;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='45f44b2f811ea8a8ace1cbed8ef906f1' WHERE id=19;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='69c14fbcc23aecb9920b3cdad7f89901' WHERE id=20;");
	db_install_execute("UPDATE snmp_query_graph SET hash='d1e0d9b8efd4af98d28ce2aad81a87e7' WHERE id=16;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='2347e9f53564a54d43f3c00d4b60040d' WHERE id=79;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='27eb220995925e1a5e0e41b2582a2af6' WHERE id=80;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='3a0f707d1c8fd0e061b70241541c7e2e' WHERE id=78;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='8ef8ae2ef548892ab95bb6c9f0b3170e' WHERE id=77;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='c7ee2110bf81639086d2da03d9d88286' WHERE id=76;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='809c2e80552d56b65ca496c1c2fff398' WHERE id=33;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='e403f5a733bf5c8401a110609683deb3' WHERE id=32;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='7fb4a267065f960df81c15f9022cd3a4' WHERE id=31;");
	db_install_execute("UPDATE snmp_query_graph SET hash='ed7f68175d7bb83db8ead332fc945720' WHERE id=20;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='7e87efd0075caba9908e2e6e569b25b0' WHERE id=95;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='dd28d96a253ab86846aedb25d1cca712' WHERE id=96;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='ce425fed4eb3174e4f1cde9713eeafa0' WHERE id=97;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='d0d05156ddb2c65181588db4b64d3907' WHERE id=98;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='3b018f789ff72cc5693ef79e3a794370' WHERE id=99;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='f434ec853c479d424276f367e9806a75' WHERE id=41;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='9b085245847444c5fb90ebbf4448e265' WHERE id=42;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='5977863f28629bd8eb93a2a9cbc3e306' WHERE id=43;");
	db_install_execute("UPDATE snmp_query_graph SET hash='f85386cd2fc94634ef167c7f1e5fbcd0' WHERE id=21;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='b225229dbbb48c1766cf90298674ceed' WHERE id=100;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='c79248ddbbd195907260887b021a055d' WHERE id=101;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='12a6750d973b7f14783f205d86220082' WHERE id=102;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='25b151fcfe093812cb5c208e36dd697e' WHERE id=103;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='e9ab404a294e406c20fdd30df766161f' WHERE id=104;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='37b6711af3930c56309cf8956d8bbf14' WHERE id=44;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='cc435c5884a75421329a9b08207c1c90' WHERE id=45;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='82edeea1ec249c9818773e3145836492' WHERE id=46;");
	db_install_execute("UPDATE snmp_query_graph SET hash='7d309bf200b6e3cdb59a33493c2e58e0' WHERE id=22;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='119578a4f01ab47e820b0e894e5e5bb3' WHERE id=105;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='940e57d24b2623849c77b59ed05931b9' WHERE id=106;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='0f045eab01bbc4437b30da568ed5cb03' WHERE id=107;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='bd70bf71108d32f0bf91b24c85b87ff0' WHERE id=108;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='fdc4cb976c4b9053bfa2af791a21c5b5' WHERE id=109;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='87522150ee8a601b4d6a1f6b9e919c47' WHERE id=47;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='993a87c04f550f1209d689d584aa8b45' WHERE id=48;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='183bb486c92a566fddcb0585ede37865' WHERE id=49;");
	db_install_execute("UPDATE snmp_query SET hash='3c1b27d94ad208a0090f293deadde753' WHERE id=2;");
	db_install_execute("UPDATE snmp_query_graph SET hash='da43655bf1f641b07579256227806977' WHERE id=6;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='5d3a8b2f4a454e5b0a1494e00fe7d424' WHERE id=10;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='437918b8dcd66a64625c6cee481fff61' WHERE id=7;");
	db_install_execute("UPDATE snmp_query SET hash='59aab7b0feddc7860002ed9303085ba5' WHERE id=3;");
	db_install_execute("UPDATE snmp_query_graph SET hash='1cc468ef92a5779d37a26349e27ef3ba' WHERE id=7;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='d0b49af67a83c258ef1eab3780f7b3dc' WHERE id=11;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='bf6b966dc369f3df2ea640a90845e94c' WHERE id=12;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='2ddc61ff4bd9634f33aedce9524b7690' WHERE id=5;");
	db_install_execute("UPDATE snmp_query_graph SET hash='bef2dc94bc84bf91827f45424aac8d2a' WHERE id=8;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='5c3616603a7ac9d0c1cb9556b377a74f' WHERE id=13;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='080f0022f77044a512b083e3a8304e8b' WHERE id=14;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='c72e2da7af2cdbd6b44a5eb42c5b4758' WHERE id=6;");
	db_install_execute("UPDATE snmp_query SET hash='ad06f46e22e991cb47c95c7233cfaee8' WHERE id=4;");
	db_install_execute("UPDATE snmp_query_graph SET hash='5a5ce35edb4b195cbde99fd0161dfb4e' WHERE id=10;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='8fc9a94a5f6ef902a3de0fa7549e7476' WHERE id=29;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='a412c5dfa484b599ec0f570979fdbc9e' WHERE id=11;");
	db_install_execute("UPDATE snmp_query_graph SET hash='c1c2cfd33eaf5064300e92e26e20bc56' WHERE id=11;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='8132fa9c446e199732f0102733cb1714' WHERE id=30;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='48f4792dd49fefd7d640ec46b1d7bdb3' WHERE id=12;");
	db_install_execute("UPDATE snmp_query SET hash='8ffa36c1864124b38bcda2ae9bd61f46' WHERE id=6;");
	db_install_execute("UPDATE snmp_query_graph SET hash='a0b3e7b63c2e66f9e1ea24a16ff245fc' WHERE id=15;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='cb09784ba05e401a3f1450126ed1e395' WHERE id=69;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='f21b23df740bc4a2d691d2d7b1b18dba' WHERE id=30;");
	db_install_execute("UPDATE snmp_query SET hash='30ec734bc0ae81a3d995be82c73f46c1' WHERE id=7;");
	db_install_execute("UPDATE snmp_query_graph SET hash='f6db4151aa07efa401a0af6c9b871844' WHERE id=17;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='42277993a025f1bfd85374d6b4deeb60' WHERE id=92;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='d99f8db04fd07bcd2260d246916e03da' WHERE id=40;");
	db_install_execute("UPDATE snmp_query SET hash='9343eab1f4d88b0e61ffc9d020f35414' WHERE id=8;");
	db_install_execute("UPDATE snmp_query_graph SET hash='46c4ee688932cf6370459527eceb8ef3' WHERE id=18;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='a3f280327b1592a1a948e256380b544f' WHERE id=93;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='9852782792ede7c0805990e506ac9618' WHERE id=38;");
	db_install_execute("UPDATE snmp_query SET hash='0d1ab53fe37487a5d0b9e1d3ee8c1d0d' WHERE id=9;");
	db_install_execute("UPDATE snmp_query_graph SET hash='4a515b61441ea5f27ab7dee6c3cb7818' WHERE id=19;");
	db_install_execute("UPDATE snmp_query_graph_rrd_sv SET hash='b5a724edc36c10891fa2a5c370d55b6f' WHERE id=94;");
	db_install_execute("UPDATE snmp_query_graph_sv SET hash='fa2f07ab54fce72eea684ba893dd9c95' WHERE id=39;");
	db_install_execute("UPDATE host_template SET hash='4855b0e3e553085ed57219690285f91f' WHERE id=1;");
	db_install_execute("UPDATE host_template SET hash='07d3fe6a52915f99e642d22e27d967a4' WHERE id=3;");
	db_install_execute("UPDATE host_template SET hash='4e5dc8dd115264c2e9f3adb725c29413' WHERE id=4;");
	db_install_execute("UPDATE host_template SET hash='cae6a879f86edacb2471055783bec6d0' WHERE id=5;");
	db_install_execute("UPDATE host_template SET hash='9ef418b4251751e09c3c416704b01b01' WHERE id=6;");
	db_install_execute("UPDATE host_template SET hash='5b8300be607dce4f030b026a381b91cd' WHERE id=7;");
	db_install_execute("UPDATE host_template SET hash='2d3e47f416738c2d22c87c40218cc55e' WHERE id=8;");

	if (db_table_exists('rra')) {
		db_install_execute("UPDATE rra SET hash='c21df5178e5c955013591239eb0afd46' WHERE id=1;");
		db_install_execute("UPDATE rra SET hash='0d9c0af8b8acdc7807943937b3208e29' WHERE id=2;");
		db_install_execute("UPDATE rra SET hash='6fc2d038fb42950138b0ce3e9874cc60' WHERE id=3;");
		db_install_execute("UPDATE rra SET hash='e36f3adb9f152adfa5dc50fd2b23337e' WHERE id=4;");
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM cdef');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE cdef SET hash=? WHERE id=?',
				array(get_hash_cdef($item[$i]['id']),$item[$i]['id']));

			$item2_results = db_install_fetch_assoc('SELECT id FROM cdef_items WHERE cdef_id=?', array($item[$i]['id']));
			$item2         = $item2_results['data'];

			if ($item2 !== false) {
				for ($j=0; $j < cacti_sizeof($item2); $j++) {
					db_install_execute('UPDATE cdef_items SET hash=? WHERE id=?',
						array(get_hash_cdef($item2[$j]['id'], 'cdef_item'), $item2[$j]['id']));
				}
			}
		}
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM graph_templates_gprint');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE graph_templates_gprint SET hash=? WHERE id=?');
			array(get_hash_gprint($item[$i]['id']),$item[$i]['id']);
		}
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM data_input');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE data_input SET hash=? WHERE id=?',
				array(get_hash_data_input($item[$i]['id']),$item[$i]['id']));

			$item2_results = db_install_fetch_assoc('SELECT id FROM data_input_fields WHERE data_input_id=?', array($item[$i]['id']));
			$item2         = $item2_results['data'];

			if ($item2 !== false) {
				for ($j=0; $j < cacti_sizeof($item2); $j++) {
					db_install_execute('UPDATE data_input_fields SET hash=? WHERE id=?',
						array(get_hash_data_input($item2[$j]['id'], 'data_input_field'),$item2[$j]['id']));
				}
			}
		}
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM data_template');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE data_template SET hash=? WHERE id=?',
				array(get_hash_data_template($item[$i]['id']), $item[$i]['id']));

			$item2_results = db_install_fetch_assoc('SELECT id FROM data_template_rrd WHERE data_template_id=? and local_data_id=0', array($item[$i]['id']));
			$item2         = $item2_results['data'];

			if ($item2 !== false) {
				for ($j=0; $j < cacti_sizeof($item2); $j++) {
					db_install_execute('UPDATE data_template_rrd SET hash=? WHERE id=?',
						array(get_hash_data_template($item2[$j]['id'], 'data_template_item'), $item2[$j]['id']));
				}
			}
		}
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM graph_templates');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE graph_templates SET hash=? WHERE id=?',
				array(get_hash_graph_template($item[$i]['id']), $item[$i]['id']));

			$item2_results = db_install_fetch_assoc('SELECT id FROM graph_templates_item WHERE graph_template_id=? and local_graph_id=0', array($item[$i]['id']));
			$item2         = $item2_results['data'];

			if ($item !== false) {
				for ($j=0; $j < cacti_sizeof($item2); $j++) {
					db_install_execute('UPDATE graph_templates_item SET hash=? WHERE id=?',
						array(get_hash_graph_template($item2[$j]['id'], 'graph_template_item'), $item2[$j]['id']));
				}
			}

			$item2_results = db_install_fetch_assoc('SELECT id FROM graph_template_input WHERE graph_template_id=?', array($item[$i]['id']));
			$item2         = $item2_results['data'];

			if ($item2 !== false) {
				for ($j=0; $j < cacti_sizeof($item2); $j++) {
					db_install_execute('UPDATE graph_template_input SET hash=? WHERE id=?',
						array(get_hash_graph_template($item2[$j]['id'], 'graph_template_input'), $item2[$j]['id']));
				}
			}
		}
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM snmp_query');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE snmp_query SET hash=? WHERE id=?',
				array(get_hash_data_query($item[$i]['id']),$item[$i]['id']));

			$item2_results = db_install_fetch_assoc('SELECT id FROM snmp_query_graph WHERE snmp_query_id=?', array($item[$i]['id']));
			$item2         = $item2_results['data'];

			if ($item2 !== false) {
				for ($j=0; $j < cacti_sizeof($item2); $j++) {
					db_install_execute('UPDATE snmp_query_graph SET hash=? WHERE id=?',
						array(get_hash_data_query($item2[$j]['id'], 'data_query_graph'), $item2[$j]['id']));

					$item3_results = db_install_fetch_assoc('SELECT id FROM snmp_query_graph_rrd_sv WHERE snmp_query_graph_id=?', array($item2[$j]['id']));
					$item3         = $item3_results['data'];

					if ($item3 !== false) {
						for ($k=0; $k < cacti_sizeof($item3); $k++) {
							db_install_execute('UPDATE snmp_query_graph_rrd_sv SET hash=? WHERE id=?',
								array(get_hash_data_query($item3[$k]['id'], 'data_query_sv_data_source'),$item3[$k]['id']));
						}
					}

					$item3_results = db_install_fetch_assoc('SELECT id FROM snmp_query_graph_sv WHERE snmp_query_graph_id=?', array($item2[$j]['id']));
					$item3         = $item3_results['data'];

					if ($item3 !== false) {
						for ($k=0; $k < cacti_sizeof($item3); $k++) {
							db_install_execute('UPDATE snmp_query_graph_sv SET hash=? WHERE id=?',
								array(get_hash_data_query($item3[$k]['id'], 'data_query_sv_graph'), $item3[$k]['id']));
						}
					}
				}
			}
		}
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM host_template');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE host_template SET hash=? WHERE id=?',
				array(get_hash_host_template($item[$i]['id']), $item[$i]['id']));
		}
	}

	$item_results = db_install_fetch_assoc('SELECT id FROM rra');
	$item         = $item_results['data'];

	if ($item !== false) {
		for ($i=0; $i < cacti_sizeof($item); $i++) {
			db_install_execute('UPDATE rra SET hash=? WHERE id=?',
				array(get_hash_round_robin_archive($item[$i]['id']), $item[$i]['id']));
		}
	}
}


function get_hash_round_robin_archive($rra_id) {
    $hash = db_fetch_cell_prepared('SELECT hash FROM rra WHERE id = ?', array($rra_id));
    if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
        return $hash;
    } else {
        return generate_hash();
    }
}
