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

include('./include/auth.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'clear':
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

function view_user_log() {
	global $auth_realms, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'time',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'username' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
			),
		'result' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_userlog');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function clearFilter() {
		strURL = urlPath+'user_log.php?clear=1';
		loadUrl({url:strURL})
	}

	function purgeLog() {
		strURL = urlPath+'user_log.php?action=purge';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeLog();
		});

		$('#form_userlog').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	function applyFilter() {
		strURL  = urlPath+'user_log.php?username=' + $('#username').val();
		strURL += '&result=' + $('#result').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		loadUrl({url:strURL})
	}
	</script>
	<?php

	html_start_box(__('User Login History'), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_userlog' action='user_log.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('User');?>
					</td>
					<td>
						<select id='username' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('username') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<option value='-2'<?php if (get_request_var('username') == '-2') {?> selected<?php }?>><?php print __('Deleted/Invalid');?></option>
							<?php
							$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth ORDER BY username');

	if (cacti_sizeof($users)) {
		foreach ($users as $user) {
			print "<option value='" . html_escape($user['username']) . "'";

			if (get_request_var('username') == $user['username']) {
				print ' selected';
			} print '>' . html_escape($user['username']) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<?php print __('Result');?>
					</td>
					<td>
						<select id='result' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('result') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='1'<?php if (get_request_var('result') == '1') {?> selected<?php }?>><?php print __('Success - Password');?></option>
							<option value='2'<?php if (get_request_var('result') == '2') {?> selected<?php }?>><?php print __('Success - Token');?></option>
							<option value='3'<?php if (get_request_var('result') == '3') {?> selected<?php }?>><?php print __('Success - Password Change');?></option>
							<option value='0'<?php if (get_request_var('result') == '0') {?> selected<?php }?>><?php print __('Failed');?></option>
						</select>
					</td>
					<td>
						<?php print __('Attempts');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
	if (cacti_sizeof($item_rows)) {
		foreach ($item_rows as $key => $value) {
			print "<option value='" . $key . "'";

			if (get_request_var('rows') == $key) {
				print ' selected';
			} print '>' . html_escape($value) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc_x('Button: use filter settings', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc_x('Button: reset filter settings', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc_x('Button: delete all table entries', 'Purge');?>' title='<?php print __esc('Purge User Log');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by username */
	if (get_request_var('username') == '-2') {
		$sql_where = 'WHERE ul.username NOT IN (SELECT DISTINCT username FROM user_auth)';
	} elseif (get_request_var('username') != '-1') {
		$sql_where = "WHERE ul.username='" . get_request_var('username') . "'";
	}

	/* filter by result */
	if (get_request_var('result') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' ul.result=' . get_request_var('result');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (
			ul.username LIKE '	 . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ul.time LIKE '	  . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ua.full_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ul.ip LIKE '		. db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username=ul.username
		$sql_where");

	$user_log_sql = "SELECT ul.username, ua.full_name, ua.realm,
		ul.time, ul.result, ul.ip
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username=ul.username
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$user_log = db_fetch_assoc($user_log_sql);

	$nav = html_nav_bar('user_log.php?username=' . get_request_var('username') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, __('User Logins'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'username'  => array(__('User'), 'ASC'),
		'full_name' => array(__('Full Name'), 'ASC'),
		'realm'     => array(__('Authentication Realm'), 'ASC'),
		'time'      => array(__('Date'), 'DESC'),
		'result'    => array(__('Result'), 'DESC'),
		'ip'        => array(__('IP Address'), 'DESC')
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'user_log.php');

	$i = 0;

	if (cacti_sizeof($user_log)) {
		foreach ($user_log as $item) {
			form_alternate_row('line' . $i, true);
			?>
			<td class='nowrap'>
				<?php print filter_value($item['username'], get_request_var('filter'));?>
			</td>
			<td class='nowrap'>
				<?php if (isset($item['full_name'])) {
					print filter_value($item['full_name'], get_request_var('filter'));
				} else {
					print __('(User Removed)');
				}
			?>
			</td>
			<td class='nowrap'>
				<?php if (isset($auth_realms[$item['realm']])) {
					print filter_value($auth_realms[$item['realm']], get_request_var('filter'));
				} else {
					print __('N/A');
				}
			?>
			</td>
			<td class='nowrap'>
				<?php print filter_value($item['time'], get_request_var('filter'));?>
			</td>
			<td class='nowrap'>
				<?php print($item['result'] == 0 ? __('Failed'):($item['result'] == 1 ? __('Success - Password'):($item['result'] == 3 ? __('Success - Password Change'):__('Success - Token'))));?>
			</td>
			<td class='nowrap'>
				<?php print filter_value($item['ip'], get_request_var('filter'));?>
			</td>
			</tr>
			<?php

			$i++;
		}
	}

	html_end_box();

	if (cacti_sizeof($user_log)) {
		print $nav;
	}
}

function clear_user_log() {
	$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth');

	if (cacti_sizeof($users)) {
		/* remove active users */
		foreach ($users as $user) {
			// Check how many rows for the current user with a valid token
			foreach (array(1, 2) as $result) {
				$total_rows = db_fetch_cell_prepared('SELECT COUNT(username)
					FROM user_log
					WHERE username = ?
					AND result = ?',
					array($user['username'], $result));

				if ($total_rows > 1) {
					db_execute_prepared('DELETE
						FROM user_log
						WHERE username = ?
						AND result = ?
						ORDER BY time LIMIT ' . ($total_rows - 1),
						array($user['username'], $result));
				}
			}

			db_execute_prepared('DELETE
				FROM user_log
				WHERE username = ?
				AND result = 0',
				array($user['username']));
		}

		/* delete inactive users */
		db_execute('DELETE
			FROM user_log
			WHERE user_id NOT IN (SELECT id FROM user_auth)
			OR username NOT IN (SELECT username FROM user_auth)');
	}
}

function purge_user_log() {
	form_start('user_log.php');

	html_start_box(__('Purge User Log'), '50%', '', '3', 'center', '');

	print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to purge the User Log.<br><br><br>Note: If logging is set to both Cacti and Syslog, the log information will remain in Syslog.') . "</p>
			</td>
		</tr>
		<tr class='saveRow'>
			<td colspan='2' class='right'>
				<input type='button' class='ui-button ui-corner-all ui-widget' id='cancel' value='" . __esc('Cancel') . "'>&nbsp
				<input type='button' class='ui-button ui-corner-all ui-widget' id='pc' name='purge_continue' value='" . __esc('Continue') . "' title='" . __esc('Purge Log') . "'>
				<script type='text/javascript'>
				$(function() {
					$('#pc').click(function() {
						strURL = location.pathname+'?action=clear';
						loadUrl({url:strURL})
					});

					$('#cancel').click(function() {
						strURL = location.pathname;
						loadUrl({url:strURL})
					});
				});
				</script>
			</td>
		</tr>\n";

	html_end_box();
}
