<?php
function get_hosts($params = []) {
    $sql = "SELECT 
                id as host_id, 
                description as host_description, 
                hostname, 
                host_template_id, 
                poller_id,
                site_id,
                disabled,
                status,
                status_fail_date AS last_fail_date, 
                status_rec_date AS last_recovery_date,
                status_last_error AS last_error,
                availability,
                polling_time,
                total_polls,
                failed_polls,
                snmp_sysDescr AS snmp_description, 
                snmp_sysUpTimeInstance AS snmp_uptime, 
                snmp_sysLocation AS snmp_location,
                last_updated
            FROM host";

    $conditions = [];
    $values = [];


    if (isset($params['host_id']) && $params['host_id'] !== '') {
        if (!is_array($params['host_id'])) {
            $params['host_id'] = explode(',', $params['host_id']);
        }
        $params['host_id'] = array_map('trim', $params['host_id']);
        $params['host_id'] = array_filter($params['host_id'], fn($v) => $v !== '');
        if (!empty($params['host_id'])) {
            $placeholders = implode(',', array_fill(0, count($params['host_id']), '?'));
            $conditions[] = "id IN ($placeholders)";
            $values = array_merge($values, $params['host_id']);
        }
    }


    if (isset($params['poller_id']) && $params['poller_id'] !== '') {
        if (!is_array($params['poller_id'])) {
            $params['poller_id'] = explode(',', $params['poller_id']);
        }
        $params['poller_id'] = array_map('trim', $params['poller_id']);
        $params['poller_id'] = array_filter($params['poller_id'], fn($v) => $v !== '');
        if (!empty($params['poller_id'])) {
            $placeholders = implode(',', array_fill(0, count($params['poller_id']), '?'));
            $conditions[] = "poller_id IN ($placeholders)";
            $values = array_merge($values, $params['poller_id']);
        }
    }


    if (isset($params['site_id']) && $params['site_id'] !== '') {
        if (!is_array($params['site_id'])) {
            $params['site_id'] = explode(',', $params['site_id']);
        }
        $params['site_id'] = array_map('trim', $params['site_id']);
        $params['site_id'] = array_filter($params['site_id'], fn($v) => $v !== '');
        if (!empty($params['site_id'])) {
            $placeholders = implode(',', array_fill(0, count($params['site_id']), '?'));
            $conditions[] = "site_id IN ($placeholders)";
            $values = array_merge($values, $params['site_id']);
        }
    }


    if (isset($params['template_id']) && $params['template_id'] !== '') {
        if (!is_array($params['template_id'])) {
            $params['template_id'] = explode(',', $params['template_id']);
        }
        $params['template_id'] = array_map('trim', $params['template_id']);
        $params['template_id'] = array_filter($params['template_id'], fn($v) => $v !== '');
        if (!empty($params['template_id'])) {
            $placeholders = implode(',', array_fill(0, count($params['template_id']), '?'));
            $conditions[] = "host_template_id IN ($placeholders)";
            $values = array_merge($values, $params['template_id']);
        }
    }


    if (isset($params['status'])) {
        $conditions[] = "status = ?";
        $values[] = $params['status'];
    }


    if (isset($params['snmp_location'])) {
        $conditions[] = "snmp_sysLocation LIKE ?";
        $values[] = '%' . $params['snmp_location'] . '%';
    }

    // Apply WHERE clause if needed
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    return db_fetch_assoc($sql, $values);
}



/**
 * Retrieves a list of graphs associated with a specific host.
 *
 * This function queries the database to fetch graph details for a given host ID.
 * It returns an array of graphs, each containing the host ID, graph ID, and RRD name.
 *
 * @param int $host_id The ID of the host for which to retrieve the graph list. Defaults to 0.
 * 
 * @return array An associative array with the key "graphs" containing a list of graphs.
 *               Each graph is represented as an associative array with the following keys:
 *               - "host_id" (int): The ID of the host.
 *               - "graph_id" (int): The ID of the graph.
 *               - "rrd_name" (string): The name of the RRD file.
 */
function get_graph_list($host_id = 0) {
    $sql = "SELECT DISTINCT h.id as host_id, dl.id as graph_id, dtd.name_cache as rrd_name, pi.rrd_name as rrd_path
            FROM poller_item AS pi
            INNER JOIN data_local AS dl ON dl.id = pi.local_data_id
            INNER JOIN data_template_data AS dtd ON dtd.local_data_id = pi.local_data_id
            INNER JOIN host AS h ON pi.host_id = h.id
            WHERE rrd_name != '' AND h.id = ?";
    $rows = db_fetch_assoc($sql, [$host_id]);
    $graphs = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $graphs[] = [
                "host_id" => $row['host_id'],
                "graph_id" => $row['graph_id'],
                "rrd_name" => $row['rrd_name']
            ];
        }
    }
    return ["graphs" => $graphs];
}


function get_poller_status($poller_id = 0) {
    $sql = "SELECT id, name, disabled, status, hostname, total_time, max_time, min_time, avg_time, total_polls, processes, threads, last_update, last_status
             FROM poller";
    $values = [];
    
    if ($poller_id > 0) {
        $sql .= " WHERE id = ?";
        $values[] = $poller_id;
    }

    return db_fetch_assoc($sql, $values);
}




function get_host_templates($template_id = 0) {
    $sql = "SELECT id,name,class FROM host_template";
    $values = [];

    if ($template_id > 0) {
        $sql .= " WHERE id = ?";
        $values[] = $template_id;
    }

    return db_fetch_assoc($sql, $values);
}



function get_cacti_status() {
    // Get Cacti version
    $cacti_version = db_fetch_cell("SELECT cacti AS version FROM version LIMIT 1");
    $poller_type = read_config_option('poller_type');
    $poller_enabled = read_config_option('poller_enabled');
    $total_hosts = db_fetch_cell("SELECT COUNT(*) as count FROM host");
    $total_data_sources = db_fetch_cell("SELECT COUNT(*) as count FROM data_local");
    $poller_output_size = db_fetch_cell("SELECT COUNT(*) as count FROM poller_output");
    $boost_table_size = db_fetch_cell("SELECT COUNT(*) as count FROM poller_output_boost");

    if ($poller_enabled === "on") {
        $poller_enabled = "Global Polling is enabled";
    } else {
        $poller_enabled = "Global polling is disabled";
    }

    $poller_type = ($poller_type === "2") ? "Spine" : (($poller_type === "1") ? "cmd.php" : $poller_type);

    // Get installed plugins
    $plugins = db_fetch_assoc("SELECT name, status, version FROM plugin_config");
    $installed_plugins = [];
    if (is_array($plugins)) {
        foreach ($plugins as $plugin) {
            $status = ($plugin['status'] == 1) ? "enabled" : "disabled";
            $installed_plugins[] = [
                "name" => $plugin['name'],
                "status" => $status,
                "version" => $plugin['version']
            ];
        }
    }

    $cacti_status = [
        [
            "cacti_version" => $cacti_version,
            "poller_type" => $poller_type,
            "poller_enabled" => $poller_enabled,
            "total_hosts" => $total_hosts['count'] ?? 0,
            "poller_output_size" => $poller_output_size['count'] ?? 0,
            "boost_table_size" => $boost_table_size['count'] ?? 0,
            "total_data_sources" => $total_data_sources['count'] ?? 0,
            "installed_plugins" => $installed_plugins

        ]
    ];

    return ["cacti_status" => $cacti_status];
}






function get_boost_status() {
    $boost_rrd_update_enable = read_config_option('boost_rrd_update_enable');
    if ($boost_rrd_update_enable !== 'on') {
        return ['boost_rrd_update_enable' => 'Boost is disabled'];

    }

    $boost_last_end_time = read_config_option('boost_last_end_time');
    $boost_last_start_time = read_config_option('boost_last_start_time');
    $boost_next_run_time = read_config_option('boost_next_run_time');
    $stats_detail_boost = read_config_option('stats_detail_boost');
    $total_boost_records = db_fetch_assoc("SELECT COUNT(*) as count FROM poller_output_boost");

    $boost_status = [
        "boost_rrd_update_enable" => $boost_rrd_update_enable,
        "boost_last_end_time" => $boost_last_end_time,
        "boost_last_start_time" => $boost_last_start_time,
        "boost_next_run_time" => $boost_next_run_time,
        "stats_detail_boost" => $stats_detail_boost,
        "total_boost_records" => $total_boost_records['count'] ?? 0
    ];

    return ["boost_status" => $boost_status];
}



function get_cacti_db_status() {

    $status_rows = db_fetch_assoc("SHOW STATUS");
    $status_assoc = [];
    foreach ($status_rows as $row) {
        if (isset($row['Variable_name']) && isset($row['Value'])) {
            $status_assoc[$row['Variable_name']] = $row['Value'];
        }
    }

    $db_status = [
        "max_used_connections" => $status_assoc['Max_used_connections'] ?? 0,
        "threads_connected" => $status_assoc['Threads_connected'] ?? 0,
        "threads_running" => $status_assoc['Threads_running'] ?? 0,
        "Aborted_clients" => $status_assoc['Aborted_clients'] ?? 0,
        "Aborted_connects" => $status_assoc['Aborted_connects'] ?? 0,
        "Uptime" => $status_assoc['Uptime'] ?? 0,
        "innodb_buffer_pool_size" => $status_assoc['Innodb_buffer_pool_size'] ?? 0,
        "innodb_buffer_pool_pages_total" => $status_assoc['Innodb_buffer_pool_pages_total'] ?? 0,
        "innodb_buffer_pool_pages_free" => $status_assoc['Innodb_buffer_pool_pages_free'] ?? 0,
        "innodb_buffer_pool_pages_data" => $status_assoc['Innodb_buffer_pool_pages_data'] ?? 0,
        "innodb_buffer_pool_pages_dirty" => $status_assoc['Innodb_buffer_pool_pages_dirty'] ?? 0
    ];

    return ["db_status" => $db_status];
}



function get_thresholds($params = []) {
    $check_thold_installed = db_fetch_assoc("SHOW TABLES LIKE 'thold_data'");
    if (empty($check_thold_installed)) {
        return ["thold_status" => "Thresholds plugin is not installed"];
    }

    $sql = "SELECT 
    h.id as HOST_ID ,description AS HOST_DESCRIPTION,h.hostname,data_source_name,thold_hi,thold_low,lastread,lastchanged,thold_fail_count,thold_enabled
    FROM thold_data td inner join host h ON td.host_id = h.id";
    $conditions = [];
    $values = [];

    if (isset($params['host_id']) && $params['host_id'] !== '') {
        if (!is_array($params['host_id'])) {
            $params['host_id'] = explode(',', $params['host_id']);
        }
        $params['host_id'] = array_map('trim', $params['host_id']);
        $params['host_id'] = array_filter($params['host_id'], fn($v) => $v !== '');
        if (!empty($params['host_id'])) {
            $placeholders = implode(',', array_fill(0, count($params['host_id']), '?'));
            $conditions[] = "h.id IN ($placeholders)";
            $values = array_merge($values, $params['host_id']);
        }
    }

    if (isset($params["host_description"])) {
        $conditions[] = "h.description LIKE ?";
        $values[] = '%' . $params['host_description'] . '%';
    }
    if (isset($params["hostname"])) {
        $conditions[] = "h.hostname LIKE ?";
        $values[] = '%' . $params['hostname'] . '%';
    }
    if (isset($params['data_source_name'])) {
        $conditions[] = "td.data_source_name LIKE ?";
        $values[] = '%' . $params['data_source_name'] . '%';
    }
    if (isset($params['template_id']) && $params['template_id'] !== '') {
        if (!is_array($params['template_id'])) {
            $params['template_id'] = explode(',', $params['template_id']);
        }
        $params['template_id'] = array_map('trim', $params['template_id']);
        $params['template_id'] = array_filter($params['template_id'], fn($v) => $v !== '');
        if (!empty($params['template_id'])) {
            $placeholders = implode(',', array_fill(0, count($params['template_id']), '?'));
            $conditions[] = "host_template_id IN ($placeholders)";
            $values = array_merge($values, $params['template_id']);
        }
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $rows = db_fetch_assoc($sql, $values);
    $thresholds = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $thresholds[] = [
                "host_id" => $row['HOST_ID'],
                "host_description" => $row['HOST_DESCRIPTION'],
                "hostname" => $row['hostname'],
                "data_source_name" => $row['data_source_name'],
                "thold_hi" => $row['thold_hi'],
                "thold_low" => $row['thold_low'],
                "lastread" => $row['lastread'],
                "lastchanged" => $row['lastchanged'],
                "thold_fail_count" => $row['thold_fail_count'],
                "thold_enabled" => $row['thold_enabled']
            ];
        }
    }
    return ["thresholds" => $thresholds];
}



function get_threshold_status() {
    $check_thold_installed = db_fetch_assoc("SHOW TABLES LIKE 'thold_data'");
    if (empty($check_thold_installed)) {
        return ["thold_status" => "Thresholds plugin is not installed"];
    }

    $check_daemon_enabled = read_config_option('thold_daemon_enable');
    $daemon_status = [];
    if (empty($check_daemon_enabled)) {
        $daemon_status = ["thold_status" => "Thold daemon is not enabled"];
    } else {
        $daemon_status = [
            "thold_daemon_dead_notification" => read_config_option('thold_daemon_dead_notification'),
            "thold_daemon_debug" => (read_config_option('thold_daemon_debug') === 'on') ? 'enabled' : 'disabled',
            "thold_daemon_down_notify_time" => read_config_option('thold_daemon_down_notify_time'),
            "thold_daemon_enable" => ($check_daemon_enabled === 'on') ? 'enabled' : 'disabled'
        ];
    }

    $poller_stats = read_config_option('stats_thold');
    $total_thresholds = db_fetch_assoc("SELECT COUNT(*) as cnt FROM thold_data")['cnt'] ?? 0;
    $enabled_thresholds = db_fetch_assoc("SELECT COUNT(*) as cnt FROM thold_data WHERE thold_enabled = 1")['cnt'] ?? 0;
    $disabled_thresholds = db_fetch_assoc("SELECT COUNT(*) as cnt FROM thold_data WHERE thold_enabled = 0")['cnt'] ?? 0;

    return [
        "thold_status" => [
            "daemon" => $daemon_status,
            "poller_stats" => $poller_stats['value'] ?? null,
            "total_thresholds" => $total_thresholds,
            "enabled_thresholds" => $enabled_thresholds,
            "disabled_thresholds" => $disabled_thresholds
        ]
    ];
}