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
$g2 = db_fetch_assoc('SELECT * FROM host WHERE ' . array_to_sql_or(gnrv('ids'), 'id'));

$unsafe_column = gnrv('column');
// ruleid: cacti-request-var-in-sql
$g3 = db_fetch_assoc('SELECT * FROM host WHERE ' . array_to_sql_or([1], $unsafe_column));

// ---- cacti-request-var-in-shell-or-rrdtool ----

// ruleid: cacti-request-var-in-shell-or-rrdtool
$h = shell_exec('snmpget ' . gnrv('community') . ' host');

// ruleid: cacti-request-var-in-shell-or-rrdtool
rrdtool_execute('tune ' . grv('max'), false, 1, $pipe, 'POLLER');

// ok: cacti-request-var-in-shell-or-rrdtool
$i = shell_exec('snmpget ' . cacti_escapeshellarg(gnrv('community')) . ' host');

// ok: cacti-request-var-in-shell-or-rrdtool
$j = exec('rrdtool tune ' . intval(grv('max')));

// ---- cacti-request-var-echoed-unescaped ----

// ruleid: cacti-request-var-echoed-unescaped
print "<option value='" . gnrv('location') . "'>";

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
