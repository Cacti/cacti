<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

function process_tree_settings() {
	if (isset_request_var('hide')) {
		if ((get_request_var('hide') == '0') || (get_request_var('hide') == '1')) {
			/* only update expand/contract info is this user has rights to keep their own settings */
			if ((isset($current_user)) && ($current_user['graph_settings'] == 'on')) {
				db_execute_prepared('DELETE FROM settings_tree WHERE graph_tree_item_id = ? AND user_id = ?', array(get_request_var('branch_id'), $_SESSION['sess_user_id']));
				db_execute_prepared('INSERT INTO settings_tree (graph_tree_item_id, user_id,status) values (?, ?, ?)', array(get_request_var('branch_id'), $_SESSION['sess_user_id'], get_request_var('hide')));
			}
		}
	}
}

function grow_dropdown_tree($tree_id, $parent = 0, $form_name = '', $selected_tree_item_id = '', $tier = 0) {
	global $config;

	$tier++;

	$branches = db_fetch_assoc_prepared('SELECT gti.id, gti.title, parent
		FROM graph_tree_items AS gti
		WHERE gti.graph_tree_id = ?
		AND gti.host_id = 0
		AND gti.local_graph_id = 0
		AND parent = ?
		ORDER BY parent, position',
		array($tree_id, $parent));

	if ($parent == 0) {
		print "<select name='$form_name' id='$form_name'>\n";
		print "<option value='0'>[root]</option>\n";
	}

	if (sizeof($branches)) {
		foreach ($branches as $leaf) {
			if ($leaf['parent'] == 0) {
				$tier = 1;
			}

			$indent = str_repeat('-', $tier);

			if ($selected_tree_item_id == $leaf['id']) {
				$html_selected = ' selected';
			} else {
				$html_selected = '';
			}

			print "<option value='" . $leaf['id'] . "'$html_selected>$indent " . html_escape($leaf['title']) . "</option>\n";

			grow_dropdown_tree($tree_id, $leaf['id'], $form_name, $selected_tree_item_id, $tier);
		}
	}

	if ($parent == 0) {
		print "</select>\n";
	}
}

function grow_dhtml_trees() {
	global $config;

	include_once($config['library_path'] . '/data_query.php');

	html_validate_tree_vars();

	$default_tree_id = read_user_setting('default_tree_id');

	if (empty($default_tree_id)) {
		if (read_config_option('auth_method') != 0) {
			$user = db_fetch_row_prepared('SELECT policy_trees
				FROM user_auth
				WHERE id = ?',
				array($_SESSION['sess_user_id']));

			if ($user['policy_trees'] == 1) {
				$default_tree_id = db_fetch_cell_prepared('SELECT graph_tree.id
					FROM graph_tree
					LEFT JOIN user_auth_perms ON user_auth_perms.item_id = graph_tree.id
					AND user_auth_perms.type = 2
					AND user_auth_perms.user_id = ?
					WHERE user_auth_perms.item_id IS NULL
					AND graph_tree.enabled = "on"
					ORDER BY graph_tree.id
					LIMIT 1',
					array($_SESSION['sess_user_id']));
			} else {
				$default_tree_id = db_fetch_cell('SELECT graph_tree.id
					FROM graph_tree
					INNER JOIN user_auth_perms ON user_auth_perms.item_id = graph_tree.id
					AND user_auth_perms.type = 2
					AND user_auth_perms.user_id = ?
					WHERE graph_tree.enabled = "on"
					ORDER BY graph_tree.id
					LIMIT 1',
					array($_SESSION['sess_user_id']));
			}
		}
	} else {
		$default_tree_id = db_fetch_cell('SELECT id FROM graph_tree ORDER BY sequence LIMIT 1');
	}

	print "<div class='cactiTreeSearch' style='white-space:nowrap'><span style='padding-right:4px;'>" . __('Search') . "</span><input id='searcher' style='padding:2px;font-size:12px;max-width:200px;' type='text' size='35'><hr></div>\n";

	$dhtml_tree = create_dhtml_tree();
	if (sizeof($dhtml_tree)) {
		print "<div id='jstree'></div>\n";
	}

	?>
	<script type='text/javascript'>
	<?php
	if (isset_request_var('hyper')) {
		$path = get_tree_path();
	} elseif (!isset_request_var('node')) {
		$path = array('tree_anchor-' . $default_tree_id . '-anchor');
		set_request_var('hyper', 'true');
	}
	?>

	var search_to = false;

	function resizeGraphContent() {
		docHeight  = parseInt($('body').height());
		navigation = $('.cactiTreeNavigationArea').offset();
		navHeight  = docHeight - navigation.top + 15;
		visWidth   = Math.max.apply(Math, $('.jstree').children(':visible').map(function() { return $(this).width(); }).get());
		$('.cactiTreeNavigationArea').height(navHeight).width(visWidth);
		$('.cactiGraphContentArea').css('margin-left', visWidth+10);
	}

	function checkTreeForLogout() {
		html = $('#jstree').html();
		found = html.indexOf('<?php print __('Login to Cacti');?>');
		if (found >= 0) {
			document.location = 'logout.php';
		}
	}

	$(function () {
		$('#jstree').each(function(data) {
			var id=$(this).attr('id');

			$(this)
			.on('init.jstree', function() {
				<?php if (isset_request_var('hyper')) { ?>
				//$('#jstree').jstree().clear_state();
				<?php } ?>
			})
			.on('ready.jstree', function() {
				resizeGraphContent();
			})
			.on('changed.jstree', function() {
				resizeGraphContent();
			})
			.on('before_open.jstree', function() {
				checkTreeForLogout();
			})
			.on('open_node.jstree', function() {
				resizeGraphContent();
			})
			.on('close_node.jstree', function() {
				resizeGraphContent();
			})
			.on('select_node.jstree', function(e, data) {
				if (data.node.id) {
					if (data.node.id.search('tree_anchor') >= 0) {
						href=$('#'+data.node.id).find('a:first').attr('href');
						//href=$('#'+data.node.id).find('a:first').attr('href')+"&node=0";
					} else {
						//href=$('#'+data.node.id).find('a:first').attr('href')+"&node="+data.node.id.replace('tbranch-','');
						href=$('#'+data.node.id).find('a:first').attr('href');
					}
					origHref = href;

					if (typeof href !== 'undefined') {
						href=href.replace('action=tree', 'action=tree_content');
						$('.cactiGraphContentArea').hide();

						$.get(href)
							.done(function(data) {
								$('#main').html(data);
								applySkin();

								$('.cactiGraphContentArea').show();

								var mytitle = 'Tree Mode - '+$('#nav_title').text();
								document.getElementsByTagName('title')[0].innerHTML = mytitle;
								if (typeof window.history.pushState !== 'undefined') {
									window.history.pushState({ page: origHref+'&hyper=true' }, mytitle, origHref+'&hyper=true');
								}

								window.scrollTo(0, 0);
								resizeGraphContent();
							})
							.fail(function(data) {
								getPresentHTTPError(data);
							});

					}
					node = data.node.id;
				}
				resizeGraphContent();
			})
			.jstree({
				'types' : {
					'tree' : {
						icon : urlPath+'images/tree.png',
						max_children : 0
					},
					'device' : {
						icon : urlPath+'images/server.png',
						max_children : 0
					},
					'graph' : {
						icon : urlPath+'images/server_chart_curve.png',
						max_children : 0
					},
					'graph_template' : {
						icon : urlPath+'images/server_chart.png',
						max_children : 0
					},
					'data_query' : {
						icon : urlPath+'images/server_dataquery.png',
						max_children : 0
					}
				},
				'core' : {
					'data' : {
						'url' : urlPath+'graph_view.php?action=get_node&tree_id=0',
						'data' : function(node) {
							return { 'id' : node.id }
						}
					},
					'animation' : 0,
					'check_callback' : false
				},
				'themes' : {
					'name' : 'default',
					'responsive' : true,
					'url' : true,
					'dots' : false
				},
				'state' : { 'key' : 'graph_tree_history' },
				'search' : { 'case_sensitive' : false, 'show_only_matches' : true, 'ajax' : { 'url' : 'graph_view.php?action=ajax_search'} },
				'plugins' : [ 'types', 'state', 'wholerow', 'search' ]
			});
		});

		$('#searcher').keyup(function() {
			if(search_to) { clearTimeout(search_to); }
			search_to = setTimeout(function () {
				var v = $('#searcher').val();
				$('#jstree').jstree('search', v, false);
			}, 250);
		});

		$(document).resize(function() {
			resizeGraphContent();
		});

		<?php print api_plugin_hook_function('top_graph_jquery_function');?>
	});

	</script>
	<?php
}

function get_tree_path() {
	if (isset_request_var('node')) {
		$nodes = array();

		if (strpos(get_request_var('node'), 'tbranch') !== false) {
			$parts = explode('-', get_request_var('node'));
			$node  = $parts[1];

			$linknode = db_fetch_row_prepared('SELECT *
				FROM graph_tree_items
				WHERE id = ?',
				array($node));

			if (sizeof($linknode)) {
				$nodes[]  = 'tree_anchor-' . $linknode['graph_tree_id'] . '_anchor';

				if (isset_request_var('hgd')) {
					$parts = explode(':', get_request_var('hgd'));
					switch($parts[0]) {
						case 'gt':
							$rnodes[] = 'tbranch-gt-' . $parts[1];
							break;
						case 'dq':
							$rnodes[] = 'tbranch-dq-' . $parts[1];
							break;
						case 'dqi':
							$rnodes[] = 'tbranch-dqi-' . $parts[1] . '-' . $parts[2];
							$rnodes[] = 'tbranch-dq-' . $parts[1];
							break;
						default:
							break;
					}
				}

				$rnodes[] = 'tbranch-' . $linknode['id'];

				while (true) {
					if ($linknode['parent'] > 0) {
						$rnodes[] = 'tbranch-' . $linknode['parent'];
						$linknode = db_fetch_row_prepared('SELECT *
							FROM graph_tree_items
							WHERE id = ?',
							array($linknode['parent']));
					} else {
						break;
					}
				}

				$rnodes = array_reverse($rnodes);
				$nodes = array_merge($nodes, $rnodes);
			}
		} elseif (strpos(get_request_var('node'), 'tree_anchor') !== false) {
			$parts = explode('-', get_request_var('node'));
			$nodes[] = 'tree_anchor-' . $parts[1] . '_anchor';
		}

		return $nodes;
	} else {
		return array();
	}
}

function draw_dhtml_tree_level($tree_id, $parent = 0, $editing = false) {
	$dhtml_tree = array();

	$heirarchy = get_allowed_tree_level($tree_id, $parent, $editing);

	if (sizeof($heirarchy)) {
		$dhtml_tree[] = "\t\t\t<ul>\n";
		foreach ($heirarchy as $leaf) {
			if ($leaf['host_id'] > 0) {  //It's a host
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . "_thost:" . $leaf['host_id'] . "' data-jstree='{ \"type\" : \"device\" }'>" . html_escape($leaf['hostname']) . "</li>\n";
			} elseif ($leaf['local_graph_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . "_tgraph:" . $leaf['local_graph_id'] . "' data-jstree='{ \"type\" : \"graph\" }'>" . html_escape(get_graph_title($leaf['local_graph_id'])) . "</a></li>\n";
			} else { //It's not a host
				$dhtml_tree[] = "\t\t\t\t<li class='jstree-closed' id='tbranch:" . $leaf['id'] . "'>" . html_escape($leaf['title']) . "</li>\n";
			}
		}

		$dhtml_tree[] = "\t\t\t</ul>\n";
	}

	return $dhtml_tree;
}

function draw_dhtml_tree_level_graphing($tree_id, $parent = 0) {
	global $config;

	include_once($config['base_path'] . '/lib/data_query.php');

	$heirarchy = get_allowed_tree_content($tree_id, $parent);

	$dhtml_tree = array();

	if (sizeof($heirarchy)) {
		if ($tree_id > 0) {
			$dhtml_tree[] = "\t\t\t<ul>\n";
			foreach ($heirarchy as $leaf) {
				if ($leaf['host_id'] > 0) {  //It's a host
					$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . "' data-jstree='{ \"type\" : \"device\" }'><a href=\"" . html_escape('graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&hgd=') . '">' . html_escape($leaf['hostname']) . "</a>\n";

					if (read_user_setting('expand_hosts') == 'on') {
						$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
						if ($leaf['host_grouping_type'] == HOST_GROUPING_GRAPH_TEMPLATE) {
							$graph_templates = get_allowed_graph_templates('gl.host_id=' . $leaf['host_id']);

							if (sizeof($graph_templates) > 0) {
								foreach ($graph_templates as $graph_template) {
									$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . "-gt-" . $graph_template['id'] . "' data-jstree='{ \"type\" : \"graph_template\" }'><a href='" . html_escape('graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&hgd=gt:' . $graph_template['id']) . "'>" . html_escape($graph_template['name']) . "</a></li>\n";
								}
							}
						} elseif ($leaf['host_grouping_type'] == HOST_GROUPING_DATA_QUERY_INDEX) {
							$data_queries = db_fetch_assoc_prepared('SELECT sq.id, sq.name
								FROM graph_local AS gl
								INNER JOIN snmp_query AS sq
								ON gl.snmp_query_id=sq.id
								AND gl.host_id = ?
								GROUP BY sq.id
								ORDER BY sq.name', array($leaf['host_id']));

							array_push($data_queries, array(
								'id' => '0',
								'name' => 'Non Query Based'
							));

							if (sizeof($data_queries)) {
								$ntg = get_allowed_graphs('gl.host_id=' . $leaf['host_id'] . ' AND gl.snmp_query_id=0');

								foreach ($data_queries as $data_query) {
									if ($data_query['id'] == 0) {
										$non_template_graphs = $ntg;
										$sort_field_data     = array();
									} else {
										$non_template_graphs = array();

										/* fetch a list of field names that are sorted by the preferred sort field */
										$sort_field_data     = get_formatted_data_query_indexes($leaf['host_id'], $data_query['id']);
									}

									if (($data_query['id'] == 0 && sizeof($non_template_graphs)) ||
										($data_query['id'] > 0 && sizeof($sort_field_data) > 0)
									) {
										if ($data_query['name'] != 'Non Query Based') {
											$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . "-dq-" . $data_query['id'] . "' data-jstree='{ \"type\" : \"data_query\" }'><a class='treepick' href=\"" . html_escape('graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . "&hgd=dq:" . $data_query['id']) . '">' . html_escape($data_query['name']) . "</a>\n";
										} else {
											$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-dq-' . $data_query['id'] . "' data-jstree='{ \"type\" : \"data_query\" }'><a class='treepick' href=\"" . html_escape('graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . "&hgd=dq:" . $data_query['id']) . '">' . html_escape($data_query['name']) . "</a>\n";
										}

										if ($data_query['id'] > 0) {
											$dhtml_tree[] = "\t\t\t\t\t\t\t<ul>\n";
											foreach ($sort_field_data as $snmp_index => $sort_field_value) {
												$dhtml_tree[] = "\t\t\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-dq-' . $data_query['id'] . '-' . urlencode($snmp_index) . "' data-jstree='{ \"type\" : \"graph\" }'><a class='treepick' href='" . html_escape('graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&hgd=dqi:' . $data_query['id'] . ':' . $snmp_index) . "'>" . html_escape($sort_field_value) . "</a></li>\n";
											}

											$dhtml_tree[] = "\t\t\t\t\t\t\t</ul>\n";
											$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";
										}
									}
								}
							}

							$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";
						}

						$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
					}

					$dhtml_tree[] = "\t\t\t\t</li>\n";
				} else { //It's not a host
					$children = db_fetch_cell_prepared('SELECT COUNT(*)
						FROM graph_tree_items
						WHERE parent = ?
						AND local_graph_id=0',
						array($leaf['id']));

					$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . "' " . ($children > 0 ? "class='jstree-closed'":"") . "><a href=\"" . html_escape('graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&hgd=') . '">' . html_escape($leaf['title']) . "</a></li>\n";
				}
			}

			$dhtml_tree[] = "\t\t\t</ul>\n";
		} else {
			$dhtml_tree[] = "<ul>\n";
			foreach($heirarchy as $h) {
				$dhtml_tree[] = "<li id='tree_anchor-" . $h['tree_id'] . "' data-jstree='{ \"type\" : \"tree\" }' class='jstree-closed'><a href='" . html_escape('graph_view.php?action=tree&node=tree_anchor-' . $h['tree_id'] . '&hgd=') . "'>" . html_escape($h['title']) . "</a></li>\n";
			}
			$dhtml_tree[] = "</ul>\n";
		}
	}

	return $dhtml_tree;
}

function create_dhtml_tree() {
	$dhtml_tree = array();

	$tree_list = get_allowed_trees();

	if (sizeof($tree_list)) {
		foreach ($tree_list as $tree) {
			$dhtml_tree['tree:'.$tree['id']] = true;
		}
	}

	return $dhtml_tree;
}

function html_validate_tree_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'graphs' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('treeview_graphs_per_page')
			),
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => read_user_setting('graph_template_id', '0')
			),
		'columns' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('num_columns_tree')
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'predefined_timeshift' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('default_timeshift')
			),
		'predefined_timespan' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('default_timespan')
			),
		'node' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			),
		'hgd' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => '',
			),
		'thumbnails' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_user_setting('thumbnail_section_tree_2')
			)
	);

	validate_store_request_vars($filters, 'sess_grt');
	/* ================= input validation ================= */
}

function grow_right_pane_tree($tree_id, $leaf_id, $host_group_data) {
	global $current_user, $config, $graphs_per_page, $graph_timeshifts;

	include($config['include_path'] . '/global_arrays.php');
	include_once($config['library_path'] . '/data_query.php');
	include_once($config['library_path'] . '/html_utility.php');

	if (empty($tree_id)) { return; }
	if (empty($leaf_id)) { $leaf_id = 0; }

	$sql_where       = '';
	$sql_join        = '';
	$title           = '';
	$title_delimeter = '';

	$leaf = db_fetch_row_prepared('SELECT
		title, host_id, host_grouping_type
		FROM graph_tree_items
		WHERE id = ?',
		array($leaf_id));

	$leaf_type = api_tree_get_item_type($leaf_id);

	/* get information for the headers */
	if (!empty($tree_id)) {
		$tree_name = db_fetch_cell_prepared('SELECT name
			FROM graph_tree
			WHERE id = ?',
			array($tree_id));
	}

	if (!empty($leaf_id)) {
		$leaf_name = $leaf['title'];
	}

	if (!empty($leaf_id)) {
		$host_name = db_fetch_cell_prepared('SELECT host.description
			FROM (graph_tree_items,host)
			WHERE graph_tree_items.host_id=host.id
			AND graph_tree_items.id = ?',
			array($leaf_id));
	}

	$host_group_data_array = explode(':', $host_group_data);

	if ($host_group_data_array[0] == 'gt') {
		$host_group_data_name = '<strong>' . __('Graph Template:'). '</strong> ' . db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array($host_group_data_array[1]));
		$graph_template_id = $host_group_data_array[1];
	} elseif ($host_group_data_array[0] == 'dq') {
		$host_group_data_name = '<strong>' . __('Graph Template:') . '</strong> ' . (empty($host_group_data_array[1]) ? __('Non Query Based') : db_fetch_cell_prepared('SELECT name FROM snmp_query WHERE id = ?', array($host_group_data_array[1])));
		$data_query_id = $host_group_data_array[1];
	} elseif ($host_group_data_array[0] == 'dqi') {
		$host_group_data_name = '<strong>' . __('Graph Template:') . '</strong> ' . (empty($host_group_data_array[1]) ? __('Non Query Based') : db_fetch_cell_prepared('SELECT name FROM snmp_query WHERE id = ?', array($host_group_data_array[1]))) . '-> ' . (empty($host_group_data_array[2]) ? 'Template Based' : get_formatted_data_query_index($leaf['host_id'], $host_group_data_array[1], $host_group_data_array[2]));
		$data_query_id = $host_group_data_array[1];
		$data_query_index = $host_group_data_array[2];
	}

	if (!empty($tree_name)) {
		$title .= $title_delimeter . '<strong>' . __('Tree:') . '</strong> ' . html_escape($tree_name); $title_delimeter = '-> ';
	}

	if (!empty($leaf_name)) {
		$title .= $title_delimeter . '<strong>' . __('Leaf:') . '</strong> ' . html_escape($leaf_name); $title_delimeter = '-> ';
	}

	if (!empty($host_name)) {
		$title .= $title_delimeter . '<strong>' . __('Device:') . '</strong> ' . html_escape($host_name); $title_delimeter = '-> ';
	}

	if (!empty($host_group_data_name)) {
		$title .= $title_delimeter . " $host_group_data_name"; $title_delimeter = '-> ';
	}

	html_start_box(__('Graph Filters') . (get_request_var('rfilter') != '' ? " [ " . __('Filter') . " '" . html_escape(get_request_var('rfilter')) . "' " . __('Applied') . " ]" : ''), '100%', "", '3', 'center', '');

	?>
	<tr class='even noprint' id='search'>
		<td class='noprint'>
		<form name='form_graph_view' method='post' onSubmit='applyGraphFilter();return false'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='rfilter' size='30' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id' multiple style='opacity:0.1;overflow-y:auto;overflow-x:hide;height:0px;'>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('All Graphs & Templates');?></option>
							<?php
							$graph_templates = get_allowed_graph_templates();
							if (sizeof($graph_templates)) {
								$selected    = explode(',', get_request_var('graph_template_id'));
								foreach ($graph_templates as $gt) {
									$found = db_fetch_cell_prepared('SELECT id
										FROM graph_local
										WHERE graph_template_id = ? LIMIT 1',
										array($gt['id']));

									if ($found) {
										print "<option value='" . $gt['id'] . "'";
										if (sizeof($selected)) {
											if (in_array($gt['id'], $selected)) {
												print ' selected';
											}
										}
										print '>';
										print $gt['name'] . "</option>\n";
									}
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input id='refresh' type='button' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filter');?>' onClick='applyGraphFilter()'>
							<input id='clear' type='button' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>' onClick='clearGraphFilter()'>
							<?php if (is_view_allowed('graph_settings')) {?>
							<input id='save' type='button' value='<?php print __esc('Save');?>' title='<?php print __esc('Save the current Graphs, Columns, Thumbnail, Preset, and Timeshift preferences to your profile');?>' onClick='saveGraphFilter("tree")'>
							<?php }?>
						</span>
					</td>
					<td id='text'></td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select id='graphs' onChange='applyGraphFilter()'>
							<?php
							if (sizeof($graphs_per_page)) {
								foreach ($graphs_per_page as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('graphs') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Columns');?>
					</td>
					<td>
						<select id='columns' onChange='applyGraphFilter()'>
							<option value='1' <?php print (get_request_var('columns') == '1' ? ' selected':'');?>><?php print __('%d Column', 1);?></option>
							<option value='2' <?php print (get_request_var('columns') == '2' ? ' selected':'');?>><?php print __('%d Columns', 2);?></option>
							<option value='3' <?php print (get_request_var('columns') == '3' ? ' selected':'');?>><?php print __('%d Columns', 3);?></option>
							<option value='4' <?php print (get_request_var('columns') == '4' ? ' selected':'');?>><?php print __('%d Columns', 4);?></option>
							<option value='5' <?php print (get_request_var('columns') == '5' ? ' selected':'');?>><?php print __('%d Columns', 5);?></option>
							<option value='6' <?php print (get_request_var('columns') == '6' ? ' selected':'');?>><?php print __('%d Columns', 6);?></option>
						</select>
					</td>
					<td>
						<span>
							<input id='thumbnails' type='checkbox' name='thumbnails' onClick='applyGraphFilter()' <?php print ((get_request_var('thumbnails') == 'true' || get_request_var('thumbnails') == 'on') ? 'checked':'');?>>
							<label for='thumbnails'><?php print __('Thumbnails');?></label>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<tr class='even noprint'>
		<td class='noprint'>
		<form name='form_timespan_selector' method='post' action='graph_view.php'>
			<table class='filterTable'>
				<tr id='timespan'>
					<td>
						<?php print __('Presets');?>
					</td>
					<td>
						<select id='predefined_timespan' onChange='applyGraphTimespan()'>
							<?php
							$graph_timespans[GT_CUSTOM] = __('Custom');
							$start_val = 0;
							$end_val = sizeof($graph_timespans);

							if (sizeof($graph_timespans)) {
								foreach($graph_timespans as $value => $text) {
									print "<option value='$value'"; if ($_SESSION['sess_current_timespan'] == $value) { print ' selected'; } print '>' . $text . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('From');?>
					</td>
					<td>
						<span>
							<input type='text' id='date1' size='18' value='<?php print (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
							<i id='startDate' class='calendar fa fa-calendar' title='<?php print __esc('Start Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<?php print __('To');?>
					</td>
					<td>
						<span>
							<input type='text' id='date2' size='18' value='<?php print (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
							<i id='endDate' class='calendar fa fa-calendar' title='<?php print __esc('End Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<i class='shiftArrow fa fa-backward' onClick='timeshiftGraphFilterLeft()' title='<?php print __esc('Shift Time Backward');?>'></i>
							<select id='predefined_timeshift' title='<?php print __esc('Define Shifting Interval');?>'>
								<?php
								$start_val = 1;
								$end_val = sizeof($graph_timeshifts)+1;
								if (sizeof($graph_timeshifts) > 0) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'"; if ($_SESSION['sess_current_timeshift'] == $shift_value) { print ' selected'; } print '>' . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
									}
								}
								?>
							</select>
							<i class='shiftArrow fa fa-forward' onClick='timeshiftGraphFilterRight()' title='<?php print __esc('Shift Time Forward');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<input id='tsrefresh' type='button' value='<?php print __esc('Refresh');?>' title='<?php print __esc('Refresh selected time span');?>' onClick='refreshGraphTimespanFilter()'>
							<input id='tsclear' type='button' value='<?php print __esc('Clear');?>' title='<?php print __esc('Return to the default time span');?>' onClick='clearGraphTimespanFilter()'>
						</span>
					</td>
				</tr>
				<tr id='realtime' style='display:none;'>
					<td>
						<?php print __('Window');?>
					</td>
					<td>
						<select id='graph_start' onChange='imageOptionsChanged("timespan")'>
							<?php
							foreach ($realtime_window as $interval => $text) {
								printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_window'] ? ' selected="selected"' : '', $text);
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='ds_step' onChange="imageOptionsChanged('interval')">
							<?php
							foreach ($realtime_refresh as $interval => $text) {
								printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_dsstep'] ? ' selected="selected"' : '', $text);
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='realtimeoff' value='<?php print __esc('Stop');?>'>
					</td>
					<td align='center' colspan='6'>
						<span id='countdown'></span>
					</td>
					<td>
						<input id='future' type='hidden' value='<?php print read_config_option('allow_graph_dates_in_future');?>'></input>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<script type='text/javascript'>

	var graph_start=<?php print get_current_graph_start();?>;
	var graph_end=<?php print get_current_graph_end();?>;
	var timeOffset=<?php print date('Z');?>;
	var pageAction = 'tree';
	var graphPage  = 'graph_view.php';
	var hgd        = '<?php print $host_group_data;?>';
	var date1Open  = false;
	var date2Open  = false;

	function initPage() {
		var msWidth = 100;
		$('#graph_template_id option').each(function() {
			if ($(this).textWidth() > msWidth) {
				msWidth = $(this).textWidth();
			}
			$('#graph_template_id').css('width', msWidth+120+'px');
		});

		$('#graph_template_id').hide().multiselect({
			height: 300,
			noneSelectedText: '<?php print __('All Graphs & Templates');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Templates Selected');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Graphs & Templates');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All');?>',
			uncheckAllText: '<?php print __('None');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			close: function(event, ui) {
				applyGraphFilter();
			},
			open: function(event, ui) {
				$("input[type='search']:first").focus();
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == 0) {
					if (ui.checked == true) {
						$('#graph_template_id').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				}else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter({
			label: '<?php print __('Search');?>',
			placeholder: '<?php print __('Enter keyword');?>',
			width: msWidth
		});

		$('#startDate').click(function() {
			if (date1Open) {
				date1Open = false;
				$('#date1').datetimepicker('hide');
			} else {
				date1Open = true;
				$('#date1').datetimepicker('show');
			}
		});

		$('#endDate').click(function() {
			if (date2Open) {
				date2Open = false;
				$('#date2').datetimepicker('hide');
			} else {
				date2Open = true;
				$('#date2').datetimepicker('show');
			}
		});

		$('#date1').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$('#date2').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		var navBar = "<div id='navBar' class='navBar'><?php print draw_navigation_text();?></div>";
		if (navBar != '') {
			$('#navBar').replaceWith(navBar);
		}

		setupBreadcrumbs();
	}

	$(function() {
		$.when(initPage())
		.done(function() {
			initializeGraphs();
		});
	});

	</script>
	<?php
	html_spikekill_js();
	html_end_box();

	api_plugin_hook_function('graph_tree_page_buttons',
		array(
			'treeid' => $tree_id,
			'leafid' => $leaf_id,
			'mode' => 'tree',
			'timespan' => $_SESSION['sess_current_timespan'],
			'starttime' => get_current_graph_start(),
			'endtime' => get_current_graph_end()
		)
	);

	$graph_list = array();

	if (($leaf_type == 'header') || (empty($leaf_id))) {
		$sql_where = '';
		if (get_request_var('rfilter') != '') {
			$sql_where .= " (gtg.title_cache RLIKE '" . get_request_var('rfilter') . "' OR gtg.title RLIKE '" . get_request_var('rfilter') . "')";
		}

		if (get_request_var('graph_template_id') != '' && get_request_var('graph_template_id') != '0') {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
		}

		$graph_list = get_allowed_tree_header_graphs($tree_id, $leaf_id, $sql_where);
	} elseif ($leaf_type == 'host') {
		/* graph template grouping */
		if ($leaf['host_grouping_type'] == HOST_GROUPING_GRAPH_TEMPLATE) {
			$sql_where = 'gl.host_id=' . $leaf['host_id'] . (empty($graph_template_id) ? '' : ' AND gl.graph_template_id=' . $graph_template_id);
			if (get_request_var('graph_template_id') != '' && get_request_var('graph_template_id') != '0') {
				$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
			}

			$graph_templates = get_allowed_graph_templates($sql_where);

			/* for graphs without a template */
			array_push(
				$graph_templates, array(
					'id' => '0',
					'name' => __('(No Graph Template)')
				)
			);

			if (sizeof($graph_templates)) {
				foreach ($graph_templates as $graph_template) {
					$sql_where = '';
					if (get_request_var('rfilter') != '') {
						$sql_where = " (gtg.title_cache RLIKE '" . get_request_var('rfilter') . "')";
					}
					$sql_where .= ($sql_where != '' ? 'AND':'') . ' gl.graph_template_id=' . $graph_template['id'] . ' AND gl.host_id=' . $leaf['host_id'];

					$graphs = get_allowed_graphs($sql_where);

					/* let's sort the graphs naturally */
					usort($graphs, 'naturally_sort_graphs');

					if (sizeof($graphs)) {
						foreach ($graphs as $graph) {
							$graph['graph_template_name'] = $graph_template['name'];
							array_push($graph_list, $graph);
						}
					}
				}
			}
		/* data query index grouping */
		} elseif ($leaf['host_grouping_type'] == HOST_GROUPING_DATA_QUERY_INDEX) {
			$data_queries = db_fetch_assoc("SELECT sq.id, sq.name
				FROM graph_local AS gl
				INNER JOIN snmp_query AS sq
				ON gl.snmp_query_id=sq.id
				WHERE gl.host_id=" . $leaf['host_id'] . "
				" . (!isset($data_query_id) ? '' : "AND sq.id=$data_query_id") . "
				GROUP BY sq.id
				ORDER BY sq.name");

			/* for graphs without a data query */
			if (empty($data_query_id)) {
				array_push($data_queries,
					array(
						'id' => '0',
						'name' => 'Non Query Based'
					)
				);
			}

			if (sizeof($data_queries)) {
				foreach ($data_queries as $data_query) {
					$sql_where = '';

					/* fetch a list of field names that are sorted by the preferred sort field */
					$sort_field_data = get_formatted_data_query_indexes($leaf['host_id'], $data_query['id']);

					if (get_request_var('rfilter') != '') {
						$sql_where = " (gtg.title_cache RLIKE '" . get_request_var('rfilter') . "')";
					}

					/* grab a list of all graphs for this host/data query combination */
					$sql_where .= ($sql_where != '' ? ' AND ':'') .
						' gl.snmp_query_id=' . $data_query['id'] . ' AND gl.host_id=' . $leaf['host_id'] .
						' ' . (empty($data_query_index) ? '' : " AND gl.snmp_index='$data_query_index'");

					$graphs = get_allowed_graphs($sql_where);

					/* re-key the results on data query index */
					$snmp_index_to_graph = array();
					if (sizeof($graphs) > 0) {
						/* let's sort the graphs naturally */
						usort($graphs, 'naturally_sort_graphs');

						foreach ($graphs as $graph) {
							$snmp_index_to_graph[$graph['snmp_index']][$graph['local_graph_id']] = $graph['title_cache'];
							$graphs_height[$graph['local_graph_id']] = $graph['height'];
							$graphs_width[$graph['local_graph_id']] = $graph['width'];
						}
					}

					/* using the sorted data as they key; grab each snmp index from the master list */
					foreach ($sort_field_data as $snmp_index => $sort_field_value) {
						/* render each graph for the current data query index */
						if (isset($snmp_index_to_graph[$snmp_index])) {
							foreach ($snmp_index_to_graph[$snmp_index] as $local_graph_id => $graph_title) {
								/* reformat the array so it's compatable with the html_graph* area functions */
								array_push($graph_list, array('data_query_name' => $data_query['name'], 'sort_field_value' => $sort_field_value, 'local_graph_id' => $local_graph_id, 'title_cache' => $graph_title, 'height' => $graphs_height[$graph['local_graph_id']], 'width' => $graphs_width[$graph['local_graph_id']]));
							}
						}
					}
				}
			}
		}
	}

	$total_rows = sizeof($graph_list);

	/* generate page list */
	$nav = html_nav_bar("graph_view.php?action=tree_content&tree_id=$tree_id&leaf_id=$leaf_id&node=" . get_request_var('node') . '&hgd=' . $host_group_data, MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('graphs'), $total_rows, get_request_var('columns'), __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	/* start graph display */
	print "<tr class='tableHeader'><td style='width:390px;' colspan='" . get_request_var('columns') . "' class='graphSubHeaderColumn textHeaderDark'>$title</td></tr>";

	$i = get_request_var('graphs') * (get_request_var('page') - 1);
	$last_graph = $i + get_request_var('graphs');

	$new_graph_list = array();
	while ($i < $total_rows && $i < $last_graph) {
		$new_graph_list[] = $graph_list[$i];
		$i++;
	}

	if (get_request_var('thumbnails') == 'true' || get_request_var('thumbnails') == 'on') {
		html_graph_thumbnail_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	} else {
		html_graph_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}

	if (!empty($leaf_id)) {
		api_plugin_hook_function('tree_after',$host_name.','.get_request_var('leaf_id'));
	}

	api_plugin_hook_function('tree_view_page_end');

	html_end_box();

	if ($total_rows) {
		print $nav;
	}
}

