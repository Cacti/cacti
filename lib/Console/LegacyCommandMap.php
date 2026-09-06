<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace Cacti\Console;

final class LegacyCommandMap {
	/** @var array<string, string> Legacy script basename to command name. */
	private const COMMANDS = [
		'add_data_query'                    => 'data-query:add',
		'add_datasource'                    => 'data-source:add',
		'add_device'                        => 'device:add',
		'add_graph_template'                => 'graph-template:add',
		'add_graphs'                        => 'graph:add',
		'add_group'                         => 'group:add',
		'add_group_perms'                   => 'group:permissions:add',
		'add_perms'                         => 'permissions:add',
		'add_site'                          => 'site:add',
		'add_tree'                          => 'tree:add',
		'analyze_database'                  => 'database:analyze',
		'apply_automation_rules'            => 'automation:apply-rules',
		'audit_database'                    => 'database:audit',
		'audit_graph_template_inputs'       => 'graph-template:audit-inputs',
		'batchgapfix'                       => 'rrd:batch-gap-fix',
		'change_device'                     => 'device:change',
		'clone_device_template'             => 'device-template:clone',
		'convert_tables'                    => 'database:convert-tables',
		'copy_user'                         => 'user:copy',
		'fetch_plugins'                     => 'plugin:fetch',
		'fix_mediumint'                     => 'database:fix-mediumint',
		'float_rrdfiles'                    => 'rrd:float-files',
		'genkey'                            => 'security:generate-key',
		'genmanifest'                       => 'package:generate-manifest',
		'host_update_template'              => 'device:update-template',
		'import_package'                    => 'package:import',
		'import_template'                   => 'template:import',
		'input_whitelist'                   => 'security:input-whitelist',
		'install_cacti'                     => 'system:install',
		'md5sum'                            => 'integrity:md5',
		'migrate_poller'                    => 'poller:migrate',
		'plugin_manage'                     => 'plugin:manage',
		'poller_data_sources_reapply_names' => 'poller:data-sources:reapply-names',
		'poller_graphs_reapply_names'       => 'poller:graphs:reapply-names',
		'poller_output_empty'               => 'poller:clear-empty-output',
		'poller_reindex_hosts'              => 'poller:reindex',
		'poller_replicate'                  => 'poller:replicate',
		'push_out_hosts'                    => 'poller:push-hosts',
		'rebuild_poller_cache'              => 'poller:rebuild-cache',
		'refresh_csrf'                      => 'security:refresh-csrf',
		'remove_broken_graphs'              => 'graph:remove-broken',
		'remove_device'                     => 'device:remove',
		'remove_graphs'                     => 'graph:remove',
		'removespikes'                      => 'rrd:remove-spikes',
		'reorder_data_query'                => 'data-query:reorder',
		'repair_database'                   => 'database:repair',
		'repair_graphs'                     => 'graph:repair',
		'repair_templates'                  => 'template:repair',
		'rrdresize'                         => 'rrd:resize',
		'show_perms'                        => 'permissions:show',
		'splice_rrd'                        => 'rrd:splice',
		'sqltable_to_php'                   => 'developer:sql-table-to-php',
		'structure_rra_paths'               => 'rrd:structure-paths',
		'update_heartbeat'                  => 'rrd:update-heartbeat',
		'upgrade_database'                  => 'database:upgrade',
		'version'                           => 'system:version',
	];

	/** @return array<string, string> Legacy script basename to command name. */
	public static function commands(): array {
		return self::COMMANDS;
	}
}
