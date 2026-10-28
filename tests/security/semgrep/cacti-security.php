<?php
/*
 * Self-test fixtures for cacti-security.yml. Lines marked "ruleid:" MUST match;
 * lines marked "ok:" must NOT. Run:  semgrep --test tests/security/semgrep/
 */

// ---- cacti-request-var-in-sql ----

// ruleid: cacti-request-var-in-sql
$a = db_fetch_assoc('SELECT * FROM host WHERE id = ' . grv('id'));

// ruleid: cacti-request-var-in-sql
$b = db_fetch_cell("SELECT x FROM t WHERE name = '" . gnrv('name') . "'");

// ruleid: cacti-request-var-in-sql
$b2 = db_fetch_row('SELECT x FROM t WHERE name = ' . get_request_var('name'));

$host_id = grv('host_id');
// ruleid: cacti-request-var-in-sql
$c = get_allowed_graph_templates_normalized('gl.host_id=' . $host_id, '', '', $rows);

// ok: cacti-request-var-in-sql
$d = db_fetch_assoc_prepared('SELECT * FROM host WHERE id = ?', [grv('id')]);

// ok: cacti-request-var-in-sql
$e = db_fetch_cell('SELECT x FROM t WHERE id = ' . (int) grv('id'));

// ok: cacti-request-var-in-sql
$f = db_fetch_row('SELECT x FROM t WHERE name = ' . db_qstr(gnrv('name')));

// ok: cacti-request-var-in-sql
$g = db_fetch_assoc('SELECT * FROM host WHERE id = ' . gfrv('id'));

// ok: cacti-request-var-in-sql
$g2 = db_fetch_assoc('SELECT * FROM host WHERE ' . array_to_sql_or((array) gnrv('ids'), 'id'));

$unsafe_column = gnrv('column');
// ruleid: cacti-request-var-in-sql
$g3 = db_fetch_assoc('SELECT * FROM host WHERE ' . array_to_sql_or([1], $unsafe_column));

// ---- cacti-request-var-in-shell-or-rrdtool ----

// ruleid: cacti-request-var-in-shell-or-rrdtool
$h = shell_exec('snmpget ' . gnrv('community') . ' host');

// ruleid: cacti-request-var-in-shell-or-rrdtool
rrdtool_execute('tune ' . grv('max'), false, 1, $pipe, 'POLLER');

// ruleid: cacti-request-var-in-shell-or-rrdtool
$h2 = exec('snmpwalk ' . get_request_var('community'));

// ok: cacti-request-var-in-shell-or-rrdtool
$i = shell_exec('snmpget ' . cacti_escapeshellarg(gnrv('community')) . ' host');

// ok: cacti-request-var-in-shell-or-rrdtool
$j = exec('rrdtool tune ' . intval(grv('max')));

// ---- cacti-request-var-echoed-unescaped ----

// ruleid: cacti-request-var-echoed-unescaped
print "<option value='" . gnrv('location') . "'>";

// ruleid: cacti-request-var-echoed-unescaped
echo '<td>' . get_request_var('name') . '</td>';

// ok: cacti-request-var-echoed-unescaped
print "<option value='" . html_escape(gnrv('location')) . "'>";

// ok: cacti-request-var-echoed-unescaped
print '<div>' . htmle(grv('name')) . '</div>';

// ok: cacti-request-var-echoed-unescaped
print '<td>' . filter_value(grv('name'), grv('filter')) . '</td>';

// ok: cacti-request-var-echoed-unescaped
print html_hidden_input('action', gnrv('action'));

// ok: cacti-request-var-echoed-unescaped
print html_nav_bar('graphs.php', 10, grv('page'), 30, 300);

// ok: cacti-request-var-echoed-unescaped
print "<div id='" . clean_up_name(gnrv('tab')) . "'>";

// ---- cacti-stored-value-echoed-unescaped ----

$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$id]);

// ruleid: cacti-stored-value-echoed-unescaped
print '<td>' . $host['description'] . '</td>';

// ok: cacti-stored-value-echoed-unescaped
print '<td>' . html_escape($host['description']) . '</td>';

// ok: cacti-stored-value-echoed-unescaped
print '<td>' . $host['id'] . '</td>';

$graphs = db_fetch_assoc('SELECT title_cache FROM graph_templates_graph');
foreach ($graphs as $graph) {
	// ruleid: cacti-stored-value-echoed-unescaped
	print "<option value='1'>" . $graph['title_cache'] . '</option>';
}

$notes = db_fetch_cell_prepared('SELECT notes FROM host WHERE id = ?', [$id]);
// ruleid: cacti-stored-value-echoed-unescaped
print "<div class='notes'>$notes</div>";

$sysdescr = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.1.0');
// ruleid: cacti-stored-value-echoed-unescaped
print '<span>' . $sysdescr . '</span>';

// ok: cacti-stored-value-echoed-unescaped
print '<span>' . __esc('System: %s', $sysdescr) . '</span>';

$extra = api_plugin_hook_function('device_table_cell', $host);
// ruleid: cacti-stored-value-echoed-unescaped
print $extra;

$lines = tail_file($logfile, 100);
foreach ($lines as $line) {
	// ruleid: cacti-stored-value-echoed-unescaped
	print '<td>' . $line . '</td>';
}

// ok: cacti-stored-value-echoed-unescaped
print '<td>' . number_format_i18n($host['value']) . '</td>';

// ---- cacti-unescaped-form-selectable-cell ----

foreach ($graphs as $graph) {
	// ruleid: cacti-unescaped-form-selectable-cell
	form_selectable_cell($graph['title_cache'], $graph['id']);

	// ok: cacti-unescaped-form-selectable-cell
	form_selectable_ecell($graph['title_cache'], $graph['id']);

	// ok: cacti-unescaped-form-selectable-cell
	form_selectable_cell(html_escape($graph['title_cache']), $graph['id']);

	// ok: cacti-unescaped-form-selectable-cell
	form_selectable_cell(filter_value($graph['title_cache'], grv('filter'), 'graphs.php?id=1'), $graph['id']);

	// ok: cacti-unescaped-form-selectable-cell
	form_selectable_cell($graph['id'], $graph['id']);
}

// ruleid: cacti-unescaped-form-selectable-cell
form_selectable_cell("<a href='host.php?id=1'>" . gnrv('name') . '</a>', 1);

// ruleid: cacti-request-var-in-sql
$groups = db_fetch_assoc('SELECT * FROM user_auth_group WHERE user_id = ' . grv('id'));
foreach ($groups as $g) {
	// ok: cacti-unescaped-form-selectable-cell
	form_selectable_cell($g['id'], $g['id']);

	// ok: cacti-unescaped-form-selectable-cell
	form_selectable_cell($status_labels[$g['enabled']], $g['id']);

	// ruleid: cacti-unescaped-form-selectable-cell
	form_selectable_cell($g['description'], $g['id']);
}

$total_rows = db_fetch_cell('SELECT COUNT(*) FROM host');
$nav = html_nav_bar('host.php', MAX_DISPLAY_PAGES, grv('page'), 30, $total_rows, 10, 'Devices');
// ok: cacti-stored-value-echoed-unescaped
print $nav;
