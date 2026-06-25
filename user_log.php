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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require('./include/auth.php');

// set default action
set_default_action();

switch (grv('action')) {
	case 'purge_execute':
		clear_user_log();
		raise_message('purge_user_log', __('User Log Purged.'), MESSAGE_LEVEL_INFO);
		header('location: user_log.php');

		break;
	case 'purge':
		top_header();
		purge_user_log();
		bottom_footer();

		break;
	default:
		top_header();
		view_user_log();
		bottom_footer();

		break;
}

function view_user_log() : void {
	global $auth_realms, $item_rows;

	draw_user_log_filter(true);

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// filter by username
	if (grv('user_id') == '-2') {
		$sql_where    = 'WHERE ul.user_id NOT IN (SELECT DISTINCT id FROM user_auth)';
	} elseif (grv('user_id') != '-1') {
		$sql_where    = 'WHERE ul.user_id = ?';
		$sql_params[] = grv('user_id');
	}

	// filter by result
	if (grv('result') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' ul.result = ?';
		$sql_params[] = grv('result');
	}

	// filter by search string
	if (grv('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (
			ul.username LIKE ? OR ul.time LIKE ? OR ua.full_name LIKE ? OR ul.ip LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username = ul.username
		$sql_where",
		$sql_params);

	$user_log_sql = "SELECT ul.username, ua.full_name, ua.realm,
		ul.time, ul.result, ul.ip
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username = ul.username
		AND ua.id = ul.user_id
		$sql_where
		ORDER BY " . sanitize_sql_column(grv('sort_column'), 'time') . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') . '
		LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$user_log = db_fetch_assoc_prepared($user_log_sql, $sql_params);

	$display_text = [
		'username'  => [__('User'), 'ASC'],
		'full_name' => [__('Full Name'), 'ASC'],
		'realm'     => [__('Authentication Realm'), 'ASC'],
		'time'      => [__('Date'), 'DESC'],
		'result'    => [__('Result'), 'DESC'],
		'ip'        => [__('IP Address'), 'DESC']
	];

	$nav = html_nav_bar('user_log.php?user_id=' . grv('user_id') . '&filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 6, __('Login Attempts'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort($display_text, grv('sort_column'), grv('sort_direction'), 1, 'user_log.php');

	$i = 0;

	if (cacti_sizeof($user_log)) {
		foreach ($user_log as $item) {
			form_alternate_row('line' . $i, true);

			form_selectable_cell(filter_value($item['username'], grv('filter')), $i, '', 'nowrap');

			if (isset($item['full_name'])) {
				form_selectable_cell(filter_value($item['full_name'], grv('filter')), $i);
			} else {
				form_selectable_cell(__('(User Removed)'), $i);
			}

			if (isset($auth_realms[$item['realm']]['name'])) {
				form_selectable_cell(filter_value($auth_realms[$item['realm']]['name'], grv('filter')), $i);
			} else {
				form_selectable_cell(__('N/A'), $i);
			}

			form_selectable_cell(filter_value($item['time'], grv('filter')), $i);

			form_selectable_cell(($item['result'] == 0 ? __('Failed') : ($item['result'] == 1 ? __('Success - Password') : ($item['result'] == 3 ? __('Success - Password Change') : __('Success - Token')))), $i);

			form_selectable_cell(filter_value($item['ip'], grv('filter')), $i);

			$i++;
		}
	}

	html_end_box();

	if (cacti_sizeof($user_log)) {
		print $nav;
	}
}

function clear_user_log() : void {
	$users = db_fetch_assoc('SELECT DISTINCT id, username FROM user_auth');

	if (cacti_sizeof($users)) {
		// remove active users
		foreach ($users as $user) {
			// Check how many rows for the current user with a valid token
			foreach ([1, 2] as $result) {
				$total_rows = db_fetch_cell_prepared('SELECT COUNT(username)
					FROM user_log
					WHERE username = ?
					AND user_id = ?
					AND result = ?',
					[$user['username'], $user['id'], $result]);

				if ($total_rows > 1) {
					db_execute_prepared('DELETE
						FROM user_log
						WHERE username = ?
						AND user_id = ?
						AND result = ?
						ORDER BY time LIMIT ' . ($total_rows - 1),
						[$user['username'], $user['id'], $result]);
				}
			}

			db_execute_prepared('DELETE
				FROM user_log
				WHERE username = ?
				AND user_id = ?
				AND result = 0',
				[$user['username'], $user['id']]);
		}

		// delete inactive users
		db_execute('DELETE
			FROM user_log
			WHERE user_id NOT IN (SELECT id FROM user_auth)
			OR username NOT IN (SELECT username FROM user_auth)');
	}
}

function purge_user_log() : void {
	form_start('user_log.php');

	html_start_box(__('Purge User Log'), '60%', false, 3, 'center', '');

	print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to purge the User Log.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.') . "</p>
			</td>
		</tr>
		<tr class='saveRow'>
			<td colspan='2' class='right'>
				<button type='button' class='ui-button ui-corner-all ui-widget' id='cancel'>" . __esc('Cancel') . "</button>
				<button type='button' class='ui-button ui-corner-all ui-widget' id='pc' name='purge_continue' title='" . __esc('Purge Log') . "'>" . __esc('Continue') . "</button>
				<script type='text/javascript'>
				$(function() {
					$('#pc').click(function() {
						strURL = location.pathname+'?action=purge_execute';
						loadUrl({url:strURL})
					});

					$('#cancel').click(function() {
						strURL = location.pathname;
						loadUrl({url:strURL})
					});
				});
				</script>
			</td>
		</tr>";

	html_end_box();
}

function create_user_log_filter() : array {
	global $item_rows;

	$all     = ['-1' => __('All')];
	$deleted = ['-2' => __('Deleted/Invalid')];
	$users   = db_fetch_assoc('SELECT DISTINCT id,
		IF(ud.domain_name != "",
			CONCAT(ua.username, " (", ud.domain_name, ")"),
			IF(ua.realm = 0,
				CONCAT(ua.username, " (' . __esc('Local Auth') . ')"),
				CONCAT(ua.username, " (' . __esc('Basic Auth') . ')")
			)
		) AS name
		FROM user_auth AS ua
		LEFT JOIN user_domains AS ud
		ON ua.realm = ud.domain_id+1000
		ORDER BY username, realm');

	if (cacti_sizeof($users)) {
		$users = array_rekey($users, 'id', 'name');
	}

	$users = $all + $deleted + $users;

	$results = [
		'-1' => __('Any'),
		'1'  => __('Success - Password'),
		'2'  => __('Success - Token'),
		'3'  => __('Success - Password Change'),
		'0'  => __('Failed')
	];

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'user_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('User'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $users,
					'value'         => '-1'
				],
				'result' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Result'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $results,
					'value'         => '-1'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Attempts'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			],
			'purge' => [
				'method'  => 'button',
				'display' => __('Purge'),
				'action'  => 'default',
				'title'   => __('Purge User log of all but the last login attempt'),
			]
		],
		'sort' => [
			'sort_column'    => 'time',
			'sort_direction' => 'DESC'
		]
	];
}

function draw_user_log_filter(bool $render = false) : void {
	$filters = create_user_log_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('User Login History'), 'user_log.php', 'form_userlog', 'sess_userlog');

	$pageFilter->rows_label = __('Attempts');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}
