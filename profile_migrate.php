<?php

$no_http_headers = true;

include('./include/global.php');

$tables_exists = db_fetch_assoc('SHOW TABLES LIKE "data_template_data_rra"');

if ($tables_exist) {
	db_execute("ALTER TABLE data_template_data 
		ADD COLUMN t_data_source_profile_id CHAR(2) default '', 
		ADD COLUMN data_source_profile_id mediumint(8) not null default '0'");

	db_execute("CREATE TABLE IF NOT EXISTS `data_source_profiles` (
		`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
		`hash` varchar(32) NOT NULL DEFAULT '',
		`name` varchar(255) NOT NULL DEFAULT '',
		`step` int(10) unsigned NOT NULL DEFAULT '300',
		`heartbeat` int(10) unsigned NOT NULL DEFAULT '600',
		`x_files_factor` double DEFAULT '0.5',
		`default` char(2) DEFAULT '',
		PRIMARY KEY (`id`))
		ENGINE=MyISAM 
		COMMENT='Stores Data Source Profiles'");

	db_execute("CREATE TABLE IF NOT EXISTS `data_source_profiles_cf` (
		`data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`consolidation_function_id` smallint(5) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`data_source_profile_id`,`consolidation_function_id`),
		KEY `data_source_profile_id` (`data_source_profile_id`))
		ENGINE=MyISAM 
		COMMENT='Maps the Data Source Profile Consolidation Functions'");

	db_execute("CREATE TABLE IF NOT EXISTS `data_source_profiles_rra` (
		`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
		`data_source_profile_id` mediumint(8) unsigned not null default '0',
		`name` varchar(255) NOT NULL DEFAULT '',
		`steps` int(10) unsigned DEFAULT '1',
		`rows` int(10) unsigned NOT NULL DEFAULT '700',
		PRIMARY KEY (`id`),
		KEY `data_source_profile_id` (`data_source_profile_id`))
		ENGINE=MyISAM 
		COMMENT='Stores RRA Definitions for Data Source Profiles'");

	/* get the current data source profiles */
	$profiles = db_fetch_assoc("SELECT pattern, rrd_step, rrd_heartbeat, x_files_factor
		FROM (
			SELECT data_template_data_id, GROUP_CONCAT(rra_id) AS pattern 
			FROM data_template_data_rra 
			GROUP BY data_template_data_id
		) AS dtdr 
		INNER JOIN data_template_data AS dtd 
		ON dtd.id=dtdr.data_template_data_id 
		INNER JOIN data_template_rrd AS dtr 
		ON dtd.id=dtr.local_data_template_rrd_id 
		INNER JOIN rra AS r
		ON r.id IN(pattern)
		GROUP BY pattern, rrd_step, rrd_heartbeat, x_files_factor");

	$i = 1;
	if (sizeof($profiles)) {
		foreach($profiles as $profile) {
			$pattern = $profile['pattern'];

			$save = array();
			$save['id'] = 0;
			$save['name']           = 'Detected Profile ' . $i;
			$save['hash']           = get_hash_data_source_profile($save['name']);
			$save['step']           = $profile['rrd_step'];
			$save['heartbeat']      = $profile['rrd_heartbeat'];
			$save['x_files_factor'] = $profile['x_files_factor'];

			$id = sql_save($save, 'data_source_profiles');

			$rras = explode(',', $pattern);

			foreach($rras as $r) {
				db_execute("INSERT INTO data_source_profiles_rra 
					(data_source_profile_id, name, steps, rows) 
					SELECT '$id' AS data_source_profile_id, name, steps, rows FROM rra WHERE id=" . $r);

				db_execute("REPLACE INTO data_source_profiles_cf
					(data_source_profile_id, consolidation_function_id)
					SELECT '$id' AS data_source_profile_id, consolidation_function_id FROM rra_cf WHERE rra_id=" . $r);
			}

			db_execute("UPDATE data_template_data 
				SET data_source_profile_id=$id 
				WHERE data_template_data.id IN(
					SELECT data_template_data_id 
					FROM (
						SELECT data_template_data_id, GROUP_CONCAT(rra_id) AS pattern
						FROM data_template_data_rra
						GROUP BY data_template_data_id 
						HAVING pattern='" . $pattern . "'
					) AS rs);");
		}
	
		$i++;
	}

	db_execute('DROP TABLE rra');
	db_execute('DROP TABLE rra_cf');
	db_execute('DROP TABLE data_template_data_rra');
	db_execute('ALTER TABLE data_template_data DROP COLUMN t_rra_id');
	db_execute('ALTER TABLE automation_tree_rule_items DROP COLUMN rra_id');
	db_execute('ALTER TABLE automation_tree_rules DROP COLUMN rra_id');
	db_execute('ALTER TABLE graph_tree_items DROP COLUMN rra_id');
}else{
	print "Data Source Profile Migration Already Completed\n";
}

