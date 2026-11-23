<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * These handlers purge logs, change templates, enable or disable data
 * sources, reorder items or reset profile settings, and change devices, trees,
 * external links and reports.  They must only run on a POST, which csrf-magic
 * validates, and the page scripts must send a POST.
 */

$root = dirname(__DIR__, 4);

/**
 * Return the source between a marker and the next case label or closing
 * brace at the same depth, enough to see the guard that opens a handler.
 *
 * @param string $source The file contents
 * @param string $marker The text that starts the handler
 *
 * @return string The handler opening, or an empty string when not found
 */
function stateChangeHandler($source, $marker) {
	$start = strpos($source, $marker);

	if ($start === false) {
		return '';
	}

	return substr($source, $start, 160);
}

dataset('post only handlers', array(
	'data debug purge'          => array('data_debug.php', "if (isset_request_var('purge')) {"),
	'snmpagent events purge'    => array('utilities.php', "if (isset_request_var('purge')) {\n\t\tcsrf_require_post();\n\n\t\tdb_execute('TRUNCATE table snmpagent_notifications_log');"),
	'manager log purge'         => array('managers.php', "if (isset_request_var('purge')) {"),
	'device template add gt'    => array('host_templates.php', "case 'item_add_gt':"),
	'device template remove gt' => array('host_templates.php', "case 'item_remove_gt':"),
	'device template add dq'    => array('host_templates.php', "case 'item_add_dq':"),
	'device template remove dq' => array('host_templates.php', "case 'item_remove_dq':"),
	'data input field remove'   => array('data_input.php', "case 'field_remove':"),
	'data source disable'       => array('data_sources.php', "case 'ds_disable':"),
	'data source enable'        => array('data_sources.php', "case 'ds_enable':"),
	'profile logout everywhere' => array('auth_profile.php', "case 'logout_everywhere':"),
	'profile clear settings'    => array('auth_profile.php', "case 'clear_user_settings':"),
	'profile reset default'     => array('auth_profile.php', "case 'reset_default':"),
	'cdef item reorder'         => array('cdef.php', "case 'ajax_dnd':"),
	'vdef item reorder'         => array('vdef.php', "case 'ajax_dnd':"),
	'device reindex'            => array('host.php', "case 'reindex':"),
	'device add graph template' => array('host.php', "case 'gt_add':"),
	'device add data query'     => array('host.php', "case 'query_add':"),
	'device change data query'  => array('host.php', "case 'query_change':"),
	'device reload data query'  => array('host.php', "case 'query_reload':"),
	'device verbose data query' => array('host.php', "case 'query_verbose':"),
	'device enable debug'       => array('host.php', "case 'enable_debug':"),
	'device disable debug'      => array('host.php', "case 'disable_debug':"),
	'device repopulate cache'   => array('host.php', "case 'repopulate':"),
	'tree sort ascending'       => array('tree.php', "case 'sortasc':"),
	'tree sort descending'      => array('tree.php', "case 'sortdesc':"),
	'tree reorder'              => array('tree.php', "case 'ajax_dnd':"),
	'tree lock'                 => array('tree.php', "case 'lock':"),
	'tree unlock'               => array('tree.php', "case 'unlock':"),
	'tree device sort'          => array('tree.php', "case 'set_host_sort':"),
	'tree branch sort'          => array('tree.php', "case 'set_branch_sort':"),
	'external link delete'      => array('links.php', "case 'delete_page':"),
	'user report send'          => array('reports_user.php', "case 'send':"),
	'user report reorder'       => array('reports_user.php', "case 'ajax_dnd':"),
	'admin report send'         => array('reports_admin.php', "case 'send':"),
	'admin report reorder'      => array('reports_admin.php', "case 'ajax_dnd':"),
	'new graphs reload query'   => array('graphs_new.php', "case 'query_reload':"),
	'new graphs save default'   => array('graphs_new.php', "case 'ajax_save':"),
	'new graphs save filter'    => array('graphs_new.php', "case 'ajax_save_filter':"),
	'color template reorder'    => array('color_templates_items.php', "case 'ajax_dnd':"),
	'automation snmp reorder'   => array('automation_snmp.php', "case 'ajax_dnd':"),
	'data debug run all'        => array('data_debug.php', "case 'runall':"),
	'settings test email'       => array('settings.php', "case 'send_test':"),
	'domain bulk actions'       => array('user_domains.php', "if (isset_request_var('selected_items')) {"),
));

test('the handler requires a POST before it changes anything', function ($file, $marker) use ($root) {
	$handler = stateChangeHandler(file_get_contents($root . '/' . $file), $marker);

	expect($handler)->not->toBe('', "$file: handler not found")
		->and(preg_match('/^[^\n]*\n\s*csrf_require_post\(\);/', $handler))->toBe(1, "$file: $marker");
})->with('post only handlers');

test('the page scripts send these actions as a POST', function () use ($root) {
	$debug     = file_get_contents($root . '/data_debug.php');
	$utilities = file_get_contents($root . '/utilities.php');
	$sources   = file_get_contents($root . '/data_sources.php');
	$profile   = file_get_contents($root . '/auth_profile.php');

	expect($debug)->toContain("strURL = 'data_debug.php?purge=1&debug=-1&header=false';\n\t\t\tloadPageUsingPostUrl(strURL);");
	expect($utilities)->toContain("strURL = 'utilities.php?action=view_snmpagent_events&purge=1&header=false';\n\t\tloadPageUsingPostUrl(strURL);");

	expect($sources)->toContain("<a class='dsActiveToggle' href='<?php print html_escape('data_sources.php?action=ds_'")
		->toContain("on('click.dsActiveToggle', 'a.dsActiveToggle', function(event) {")
		->not->toContain("<a class='hyperLink' href='<?php print html_escape('data_sources.php?action=ds_'");

	expect($profile)->toContain("$.post('auth_profile.php?action=clear_user_settings', {__csrf_magic: csrfMagicToken}")
		->toContain("$.post('auth_profile.php?action=logout_everywhere', {__csrf_magic: csrfMagicToken}")
		->toContain("&action=reset_default', {__csrf_magic: csrfMagicToken, name: id}")
		->not->toContain("$.get('auth_profile.php");

	foreach (array('cdef.php', 'vdef.php') as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->toContain("loadPageUsingPostUrl('$file?action=ajax_dnd&id=")
			->not->toContain("loadPageNoHeader('$file?action=ajax_dnd&id=");
	}
});

test('the tree node actions are refused without a POST token', function () use ($root) {
	$global = file_get_contents($root . '/include/global.php');
	$start  = strpos($global, '$bad_actions = array(');

	expect($start)->not->toBeFalse();

	$list = substr($global, $start, strpos($global, ');', $start) - $start);

	foreach (array('create_node', 'rename_node', 'move_node', 'copy_node', 'delete_node') as $action) {
		expect($list)->toContain("'$action'");
	}
});

test('read-only device, tree and report actions stay reachable by GET', function () use ($root) {
	$open = array(
		'host.php'          => array("case 'export':", "case 'edit':", "case 'ping_host':"),
		'tree.php'          => array("case 'edit':", "case 'sites':", "case 'hosts':", "case 'graphs':", "case 'get_node':", "case 'get_host_sort':", "case 'get_branch_sort':"),
		'reports_user.php'  => array("case 'setvar':", "case 'ajax_get_branches':", "case 'ajax_hosts':"),
		'reports_admin.php' => array("case 'setvar':", "case 'ajax_get_branches':", "case 'ajax_hosts':"),
		'graphs_new.php'    => array("case 'ajax_hosts':", "case 'ajax_hosts_noany':"),
		'automation_snmp.php'       => array("case 'item_edit':", "case 'edit':"),
		'color_templates_items.php' => array("case 'item_edit':", "case 'item_remove_confirm':"),
		'data_debug.php'    => array("case 'view':", "case 'ajax_hosts':"),
	);

	$global = file_get_contents($root . '/include/global.php');
	$start  = strpos($global, '$bad_actions = array(');
	$list   = substr($global, $start, strpos($global, ');', $start) - $start);

	expect($list)->not->toContain("'get_node'");

	foreach ($open as $file => $markers) {
		$source = file_get_contents($root . '/' . $file);

		foreach ($markers as $marker) {
			$handler = stateChangeHandler($source, $marker);

			expect($handler)->not->toBe('', "$file: $marker not found")
				->and(preg_match('/^[^\n]*\n\s*csrf_require_post\(\);/', $handler))->toBe(0, "$file: $marker");
		}
	}
});

test('no page requests a device, tree, link or report action by GET', function () use ($root) {
	$actions = array(
		'host.php'    => 'reindex|gt_add|query_add|query_change|query_reload|query_verbose|enable_debug|disable_debug|repopulate',
		'tree.php'    => 'sortasc|sortdesc|ajax_dnd|lock|unlock|set_host_sort|set_branch_sort|create_node|rename_node|move_node|copy_node',
		'links.php'   => 'delete_page',
		'reports'     => 'send|ajax_dnd',
		'graphs_new.php'            => 'query_reload|ajax_save|ajax_save_filter',
		'color_templates_items.php' => 'ajax_dnd',
		'automation_snmp.php'       => 'ajax_dnd',
		'data_debug.php'            => 'runall',
		'settings.php'              => 'send_test',
	);

	$it        = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$offenders = array();

	foreach ($it as $file) {
		$path = $file->getPathname();

		if (!preg_match('/\.(php|js)$/', $path) || preg_match('#/(include/vendor|include/js|tests|node_modules|\.git)/#', $path)) {
			continue;
		}

		$source = file_get_contents($path);
		$name   = substr($path, strlen($root) + 1);

		foreach ($actions as $page => $names) {
			if ($page === 'reports') {
				$target = "(?:reports_user\.php|reports_admin\.php|get_reports_page\(\) \. '|reportsPage\+')";
			} else {
				$target = preg_quote($page, '/');
			}

			$url = $target . "\?(?:[a-z_]+=[^&'\"]*&)*action=(?:$names)(?![a-z_])";

			/* a link that a browser or crawler would follow */
			if (preg_match("/href=['\"](?!#)[^>\n]*?$url/", $source)) {
				$offenders[] = "$name: href to $page";
			}

			/* a script that fetches the URL */
			if (preg_match("/(?:loadPageNoHeader|loadPage|loadTopTab|\\$\.get|\\$\.getJSON)\([^\n]*?$url/", $source)) {
				$offenders[] = "$name: GET of $page";
			}
		}

		if (basename($path) === 'tree.php' && preg_match("/\\$\.get\('\?action=(?:create_node|rename_node|move_node|copy_node|delete_node)'/", $source)) {
			$offenders[] = "$name: GET of a tree node action";
		}
	}

	expect($offenders)->toBe(array());
});

test('the device page sends its state changing actions as a POST', function () use ($root) {
	$host = file_get_contents($root . '/host.php');

	foreach (array('disable_debug', 'enable_debug', 'repopulate', 'reindex') as $action) {
		expect(preg_match("/<a class='hyperLink devicePostAction' href='#' data-url='[^>]*host\.php\?action=$action&host_id=/", $host))->toBe(1, $action);
	}

	expect($host)->toContain("\$('.devicePostAction').on('click', function(event) {\n\t\t\tevent.preventDefault();\n\t\t\thostPageLoad(\$(this).attr('data-url'));")
		->toContain("function hostPageLoad(strURL) {\n\t\tvar scrollTop = \$(window).scrollTop();\n\t\tloadPageUsingPost(strURL);")
		->toContain("strURL = 'host.php?action=query_reload&id='+\$(this).attr('data-id')+'&host_id='+\$('#id').val()+'&nostate=true';\n\t\t\thostPageLoad(strURL);")
		->toContain("var strURL = 'host.php?action=query_verbose&id='+\$(this).attr('data-id')+'&host_id='+\$('#id').val()+'&nostate=true';\n\t\t\thostPageLoad(strURL);")
		->toContain("\$.post('host.php?action=query_add', {")
		->toContain("\$.post('host.php?action=gt_add', {");

	$change = substr($host, strpos($host, "strURL  = urlPath+'host.php?action=query_change&header=false';"), 400);

	expect($change)->toContain('hostPageLoad(strURL);')
		->not->toContain('loadPageNoHeader');

	$graphs_new = file_get_contents($root . '/graphs_new.php');

	expect($graphs_new)->toContain("<a class='cactiPostAction' href='#' data-url='\" . html_escape('host.php?action=query_verbose&header=true&id='");
});

test('the tree pages send their state changing actions as a POST', function () use ($root) {
	$tree = file_get_contents($root . '/tree.php');

	expect($tree)->toContain("\$.post('tree.php', {action: 'set_branch_sort', type: type, nodeid: nodeid, __csrf_magic: csrfMagicToken})")
		->toContain("\$.post('tree.php', {action: 'set_host_sort', type: type, nodeid: nodeid, __csrf_magic: csrfMagicToken})")
		->toContain("loadPageUsingPostUrl('tree.php?action=sortasc');")
		->toContain("loadPageUsingPostUrl('tree.php?action=sortdesc');")
		->toContain("\$('#sorta, #sorta_icon').on('click', function(event) {")
		->toContain("\$('#sortd, #sortd_icon').on('click', function(event) {")
		->toContain("'id'       => 'sorta_icon',")
		->toContain("'id'       => 'sortd_icon',")
		->toContain("loadPageUsingPostUrl('tree.php?action=ajax_dnd&'+\$.tableDnD.serialize());")
		->toContain("strURL = 'tree.php?action=lock&id=<?php print \$tree['id'];?>';\n\n\t\t\t\tloadTreeEdit(strURL);")
		->toContain("strURL = 'tree.php?action=unlock&id=<?php print \$tree['id'];?>';\n\n\t\t\t\tloadTreeEdit(strURL);")
		->toContain("function loadTreeEdit(url) {\n\t\t\tvar myMagic = csrfMagicToken;\n\n\t\t\t\$.post(url, { __csrf_magic: csrfMagicToken })");

	foreach (array('create_node', 'rename_node', 'move_node', 'copy_node', 'delete_node') as $action) {
		expect(preg_match("/\\$\.post\('\?action=$action', \{[^}\n]*'__csrf_magic' : csrfMagicToken \}\)/", $tree))->toBe(1, $action);
	}
});

test('the report pages send their state changing actions as a POST', function () use ($root) {
	$reports = file_get_contents($root . '/lib/html_reports.php');

	expect($reports)->toContain("<a class='tab cactiPostAction' href='#' data-url='\" . html_escape(get_reports_page() . '?action=send&id='")
		->toContain("var request = cactiPreparePostRequestFromUrl(reportsPage+'?action=ajax_dnd&id='+reportId+'&'+\$.tableDnD.serialize());\n\n\t\t\t\t\t\t\$.post(request.url, request.data)")
		->toContain("loadPage(reportsPage+'?action=edit&tab=items&id='+reportId);");
});

test('the new graphs, color template, automation, troubleshooter and settings pages send their state changing actions as a POST', function () use ($root) {
	$graphs_new = file_get_contents($root . '/graphs_new.php');

	expect($graphs_new)->toContain("loadPageUsingPostUrl('graphs_new.php?action=query_reload&header=false&id='+\$(this).attr('data-id')+'&host_id='+\$('#host_id').val());")
		->toContain("'&graph_type=' + \$('#graph_type').val();\n\n\t\t\$.post(strURL, {__csrf_magic: csrfMagicToken})")
		->toContain("\$.post('graphs_new.php?action=ajax_save&query=")
		->toContain("+\$(\\\".dqselect\\\").val(), {__csrf_magic: csrfMagicToken})");

	expect(file_get_contents($root . '/color_templates.php'))->toContain("loadPageUsingPostUrl('color_templates_items.php?action=ajax_dnd&id=");
	expect(file_get_contents($root . '/automation_snmp.php'))->toContain("loadPageUsingPostUrl('automation_snmp.php?action=ajax_dnd&id=");
	expect(file_get_contents($root . '/data_debug.php'))->toContain("strURL = 'data_debug.php?action=runall&debug=-1&header=false';\n\t\t\tloadPageUsingPostUrl(strURL);");
	expect(file_get_contents($root . '/settings.php'))->toContain("\$.post('settings.php?action=send_test', {__csrf_magic: csrfMagicToken})");
});
