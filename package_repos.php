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
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
	4 => __('Default')
];

$types = [
	0 => __('GitHub Based'),
	1 => __('File Based'),
	2 => __('Direct URL')
];

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();

		repo_edit();

		bottom_footer();

		break;
	default:
		top_header();

		repos();

		bottom_footer();

		break;
}

function form_save() : void {
	global $registered_cacti_names;

	if (isrv('save_component_repo')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('repo_type');
		// ====================================================

		$save['id']            = gnrv('id');
		$save['name']          = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['repo_type']     = gnrv('repo_type');
		$save['repo_location'] = gnrv('repo_location');
		$save['repo_branch']   = gnrv('repo_branch');
		$save['repo_api_key']  = gnrv('repo_api_key');
		$save['enabled']       = (isrv('enabled') ? 'on' : '');
		$save['default']       = (isrv('default') ? 'on' : '');

		if (!is_error_message()) {
			$id = sql_save($save, 'package_repositories', 'id');

			if ($id) {
				if ($save['repo_type'] == 0) {
					$repoloc = str_replace('github.com', 'raw.githubusercontent.com', $save['repo_location']);

					$file = $repoloc . '/' . $save['repo_branch'] . '/package.manifest';

					if ($save['repo_api_key'] != '') {
						$options = [
							'https' => [
								'method' => 'GET',
								'header' => 'Authorization: Bearer ' . $save['repo_api_key']
							]
						];

						$context  = stream_context_create($options);
					} else {
						$options = [
							'https' => [
								'method'  => 'GET',
							]
						];

						$context  = stream_context_create($options);
					}

					$data = file_get_contents($file, false, $context);

					if ($data != '') {
						raise_message('repo_exists', __('The Repo \'%s\' is Reachable on GitHub.', $save['name']), MESSAGE_LEVEL_INFO);
					} else {
						raise_message('repo_missing', __('The Repo \'%s\' is NOT Reachable on GitHub or the package.manifest file is missing or it could be an invalid branch.  Valid Package Locations are normally: https://github.com/Author/RepoName/.', $save['name']), MESSAGE_LEVEL_WARN);
					}
				} elseif ($save['repo_type'] == 2) {
					$file = $save['repo_location'] . '/package.manifest';

					$context = [
						'ssl' => [
							'verify_peer'      => false,
							'verify_peer_name' => false,
						],
					];

					$data = file_get_contents($file, false, stream_context_create($context));

					if ($data != '') {
						raise_message('repo_exists', __('The Repo \'%s\' is Reachable at the URL Location.', $save['name']), MESSAGE_LEVEL_INFO);
					} else {
						raise_message('repo_missing', __('The Repo \'%s\' is NOT Reachable at the URL Location or the package.manifest file is missing.', $save['name']), MESSAGE_LEVEL_WARN);
					}
				} else {
					$file = $save['repo_location'] . '/package.manifest';

					if (file_exists($file)) {
						raise_message('repo_exists', __('The Repo \'%s\' is Reachable on the Local Cacti Server.', $save['name']), MESSAGE_LEVEL_INFO);
					} else {
						raise_message('repo_missing', __('The Repo \'%s\' is NOT Reachable on the Local Cacti Server or the package.manifest file is missing.', $save['name']), MESSAGE_LEVEL_WARN);
					}
				}

				raise_message(1);
			} else {
				raise_message(2);
			}
		}
	}

	header('Location: package_repos.php?header=false&action=edit&id=' . (empty($id) ? gnrv('id') : $id));
}

function form_actions() : void {
	global $actions;

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					repo_remove($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '2') { // disable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					repo_disable($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '3') { // enable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					repo_enable($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '4') { // default
				if (cacti_sizeof($selected_items) > 1) {
					// error message
				} else {
					for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
						repo_default($selected_items[$i]);
					}
				}
			}
		}

		header('Location: package_repos.php?header=false');

		exit;
	}

	// setup some variables
	$p_list  = '';
	$p_array = [];

	// loop through each of the data queries and process them
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			// ================= input validation =================
			input_validate_input_number($matches[1]);
			// ====================================================

			$p_list .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM package_repositories WHERE id = ?', [$matches[1]])) . '</li>';
			$p_array[] = $matches[1];
		}
	}

	top_header();

	form_start('package_repos.php', 'action_confirm');

	html_start_box($actions[gnrv('drp_action')], '60%', false, 3, 'center', '');

	if (cacti_sizeof($p_array)) {
		$save_html = '';

		if (gnrv('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following .', 'Click \'Continue\' to delete following Package Repositories.', cacti_sizeof($p_array)) . "</p>
					<div class='itemlist'><ul>$p_list</ul></div>
				</td>
			</tr>";

			$save_html = "<button type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()'>" . __esc('Cancel') . "</button>
				<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' title='" . __n('Delete Package Repository', 'Delete Package Repositories', cacti_sizeof($p_array)) . "'>" . __esc('Continue') . '</button>';
		} elseif (gnrv('drp_action') == '2') { // disable
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to disable the following Package Repository.', 'Click \'Continue\' to disable following Package Repositories.', cacti_sizeof($p_array)) . "</p>
					<div class='itemlist'><ul>$p_list</ul></div>
				</td>
			</tr>";

			$save_html = "<button type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()'>" . __esc('Cancel') . "</button>
				<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' title='" . __n('Disable Package Repository', 'Disable Package Repositories', cacti_sizeof($p_array)) . "'>" . __esc('Continue') . '</button>';
		} elseif (gnrv('drp_action') == '3') { // enable
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to enable the following Package Repository.', 'Click \'Continue\' to enable following Package Repositories.', cacti_sizeof($p_array)) . "</p>
					<div class='itemlist'><ul>$p_list</ul></div>
				</td>
			</tr>";

			$save_html = "<button type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()'>" . __esc('Cancel') . "</button>
				<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' title='" . __n('Enabled Package Repository', 'Enable Package Repositories', cacti_sizeof($p_array)) . "'>" . __esc('Continue') . '</button>';
		} elseif (gnrv('drp_action') == '4') { // default
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to make the following the following Package Repository the default one.') . "</p>
					<div class='itemlist'><ul>$p_list</ul></div>
				</td>
			</tr>";

			$save_html = "<button type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()'>" . __esc('Cancel') . "</button>
				<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' title='" . __esc('Make Selected Repository Default') . "'>" . __esc('Continue') . '</button>';
		}
	} else {
		raise_message(40);
		header('Location: package_repos.php?header=false');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . serialize($p_array) . "'>
			<input type='hidden' name='drp_action' value='" . htmle(gnrv('drp_action')) . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function repo_remove(int $id) : void {
	db_execute_prepared('DELETE FROM package_repositories WHERE id = ?', [$id]);
	db_execute_prepared('DELETE FROM package_repositories WHERE id = ?', [$id]);
}

function repo_disable(int $id) : void {
	db_execute_prepared('UPDATE package_repositories SET enabled = "" WHERE id = ?', [$id]);
}

function repo_enable(int $id) : void {
	db_execute_prepared('UPDATE package_repositories SET enabled = "on" WHERE id = ?', [$id]);
}

function repo_default(int $id) : void {
	db_execute('UPDATE package_repositories SET `default` = ""');
	db_execute_prepared('UPDATE package_repositories SET `default` = "on" WHERE id = ?', [$id]);
}

function repo_edit() : void {
	global $types;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$repo         = db_fetch_row_prepared('SELECT * FROM package_repositories WHERE id = ?', [grv('id')]);
		$header_label = __esc('Package Repository [edit: %s]', $repo['name']);
	} else {
		$header_label = __('Package Repository [new]');
		$repo         = [];
	}

	if (cacti_sizeof($repo)) {
		if ($repo['repo_type'] == 1) { // Local directory
			$method = 'dirpath';
		} else {
			$method = 'textbox';
		}
	} else {
		$method = 'textbox';
	}

	$fields_package = [
		'name' => [
			'method'        => 'textbox',
			'friendly_name' => __('Name'),
			'description'   => __('Enter a meaningful name for this Package Repository.'),
			'value'         => '|arg1:name|',
			'max_length'    => '32',
		],
		'repo_type' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Repository Type'),
			'description'   => __('Choose what Package Repository type this is.'),
			'value'         => '|arg1:repo_type|',
			'array'         => $types,
			'default'       => '0'
		],
		'repo_location' => [
			'method'        => $method,
			'friendly_name' => __('Location'),
			'description'   => __('Either the full path on the Cacti Web Server to the Repository or a URL to the Repository.  For example: https://github.com/Cacti/packages/'),
			'value'         => '|arg1:repo_location|',
			'max_length'    => '128',
			'size'          => '120'
		],
		'repo_branch' => [
			'method'        => 'textbox',
			'friendly_name' => __('Branch'),
			'description'   => __('For GitHub based repositories, the branch to include such as \'develop\', \'master\', etc.'),
			'value'         => '|arg1:repo_branch|',
			'max_length'    => '128',
			'size'          => '10'
		],
		'repo_api_key' => [
			'method'        => 'textbox',
			'friendly_name' => __('API Key (optional)'),
			'description'   => __('For GitHub based repositories, the optional API key required to access the repository.'),
			'value'         => '|arg1:repo_api_key|',
			'max_length'    => '128',
			'size'          => '100'
		],
		'enabled' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Enabled'),
			'description'   => __('If this checkbox is checked, you will be able to import packages from this Repository.'),
			'value'         => '|arg1:enabled|',
			'default'       => 'on',
		],
		'default' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Default'),
			'description'   => __('If this checkbox is checked, this will be the first Repository shown to the user.'),
			'value'         => '|arg1:default|',
			'default'       => '',
		],
		'id' => [
			'method' => 'hidden_zero',
			'value'  => '|arg1:id|'
		],
		'save_component_repo' => [
			'method' => 'hidden',
			'value'  => '1'
		]
	];

	form_start('package_repos.php');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form(
		[
			'config' => [],
			'fields' => inject_form_variables($fields_package, $repo)
		]
	);

	html_end_box(true);

	?>
	<script type='text/javascript'>
	function initPackageType() {
		if ($('#repo_type').val() == 0) {
			$('#row_repo_branch').show();
			$('#row_repo_api_key').show();
		} else {
			$('#row_repo_branch').hide();
			$('#row_repo_api_key').hide();
		}
	}

	$(function() {
		initPackageType();

		$('#repo_type').change(function() {
			initPackageType();
		});
	});
	</script>
	<?php

	form_save_button('package_repos.php', 'return', 'id');
}

function repos() : void {
	global $actions, $item_rows, $types;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Package Repositories'), 'package_repos.php', 'fors', 'sess_package_repos', 'package_repos.php?action=edit');

	$pageFilter->rows_label = __('Repos');
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = ($sql_where != '' ? ' AND ' : 'WHERE ') .
			'(name LIKE ? OR repo_branch LIKE ? OR repo_location LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM package_repositories
		$sql_where",
		$sql_params);

	$repos = db_fetch_assoc_prepared("SELECT *
		FROM package_repositories
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$display_text = [
		'name' => [
			'display' => __('Name'),
			'sort'    => 'ASC'
		],
		'repo_type' => [
			'display' => __('Type'),
			'sort'    => 'ASC'
		],
		'repo_location' => [
			'display' => __('Location'),
			'sort'    => 'ASC'
		],
		'repo_branch' => [
			'display' => __('Branch'),
			'align'   => 'center',
			'sort'    => 'ASC'
		],
		'enabled' => [
			'display' => __('Enabled'),
			'align'   => 'center',
			'sort'    => 'ASC'
		],
		'default' => [
			'display' => __('Default'),
			'align'   => 'center',
			'sort'    => 'ASC'
		],
	];

	$nav = html_nav_bar('package_repos.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, sizeof($display_text) + 1, __('Package Repositories'), 'page', 'main');

	form_start('package_repos.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($repos)) {
		foreach ($repos as $repo) {
			form_alternate_row('line' . $repo['id'], true);

			form_selectable_cell(filter_value($repo['name'], grv('filter'), 'package_repos.php?action=edit&id=' . $repo['id']), $repo['id']);
			form_selectable_cell($types[$repo['repo_type']], $repo['id']);
			form_selectable_cell(filter_value($repo['repo_location'], grv('filter')), $repo['id']);
			form_selectable_ecell($repo['repo_type'] == 0 ? ($repo['repo_branch'] != '' ? $repo['repo_branch'] : __('default')) : __('N/A'), $repo['id'], '', 'center');
			form_selectable_cell($repo['enabled'] == 'on' ? __('Yes') : __('No'), $repo['id'], '', 'center');
			form_selectable_cell($repo['default'] == 'on' ? __('Yes') : __('No'), $repo['id'], '', 'center');

			form_checkbox_cell($repo['name'], $repo['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Package Repositories Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($repos)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
