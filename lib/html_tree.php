<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
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

function grow_dropdown_tree($tree_id, $parent = 0, $form_name = '', $selected_tree_item_id = '') {
	global $config;

	static $tier = 0;

	$tier++;

	$branches = db_fetch_assoc("SELECT gti.id, gti.title, parent
		FROM graph_tree_items AS gti
		WHERE gti.graph_tree_id=$tree_id
		AND gti.host_id = 0
		AND gti.local_graph_id = 0
		AND parent=$parent
		ORDER BY parent, position");

	if ($parent == 0) {
		print "<select id='$form_name'>\n";
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
			}else{
				$html_selected = '';
			}

			print "<option value='" . $leaf['id'] . "'$html_selected>$indent " . $leaf['title'] . "</option>\n";

			grow_dropdown_tree($tree_id, $leaf['id'], $form_name, $selected_tree_item_id);
		}
	}

	if ($parent == 0) {
		print "</select>\n";
	}
}

function grow_dhtml_trees() {
	global $config;

	include_once($config['library_path'] . '/data_query.php');

	$default_tree_id = read_user_setting('default_tree_id');

	if (empty($default_tree_id)) {
		$user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

		if ($user['policy_trees'] == 1) {
			$default_tree_id = db_fetch_cell("SELECT id 
				FROM graph_tree
				WHERE id NOT IN (
					SELECT item_id 
					FROM user_auth_perms 
					WHERE type=2 AND user_id=" . $_SESSION['sess_user_id'] . "
				)
				AND enabled='on'
				ORDER BY id LIMIT 1");
		}else{
			$default_tree_id = db_fetch_cell("SELECT id 
				FROM graph_tree
				WHERE id IN (
					SELECT item_id 
					FROM user_auth_perms 
					WHERE type=2 
					AND user_id=" . $_SESSION['sess_user_id'] . "
				)
				AND enabled='on'
				ORDER BY id LIMIT 1");
		}
	}

	print "<div style='white-space:nowrap'><span style='padding-right:4px;'>Search</span><input id='searcher' style='padding:2px;font-size:12px;max-width:200px;' type='text' size='35'><hr></div>\n";

	$dhtml_tree = create_dhtml_tree();
	if (sizeof($dhtml_tree)) {
		print "<div id='jstree'></div>\n";
	}

	?>
	<script type='text/javascript'>
	<?php
	if ((!isset($_SESSION['sess_node_id']) && !isset_request_var('tree_id'))) {
		print "var node='tree_anchor-" . $default_tree_id . "';\n";
		print "var reset=true;\n";
	}elseif (isset_request_var('nodeid') && !isempty_request_var('nodeid')) {
		print "var node='" . get_nfilter_request_var('nodeid') . "';\n";
		print "var reset=false;\n";
	}elseif (isset($_SESSION['sess_node_id'])) {
		if ($_SESSION['sess_node_id'] == 'tbranch-0') {
			if (isset($_SESSION['sess_tree_id'])) {
				print "var node='tree_anchor-" . $_SESSION['sess_tree_id'] . "';\n";
				print "var reset=false;\n";
			}else{
				print "var node='tree_anchor-" . $default_tree_id . "';\n";
				print "var reset=true;\n";
			}
		}else{
			print "var node='" . $_SESSION['sess_node_id'] . "';\n";
			print "var reset=false;\n";
		}
	}elseif (isset_request_var('tree_id')) {
		print "var node='tree_anchor-" . get_request_var('tree_id') . "';\n";
		print "var reset=false;\n";
	}else{
		print "var node='';\n";
		print "var reset=true;\n";
	}
	if (isset_request_var('leaf_id')) {
		print "var leaf='" . get_filter_request_var('leaf_id') . "';\n";
	}else{
		print "var leaf='';\n";
	}
	?>

	var search_to = false;

	function resizeGraphContent() {
		$('.cactiGraphContentArea').css('margin-left', parseInt($('#navigation').width()+10)+'px');
	}

	$(function () {
		$('#jstree').each(function(data) {
			var id=$(this).attr('id');

			$(this)
			.on('set_state.jstree', function(e, data) {
				if (node!='') {
					$(this).jstree('deselect_all');

					if (node.search('tree_anchor') >= 0) {
						href=$('#'+node).find('a:first').attr('href')+"&nodeid=0";
					}else if (node.search('-j') >= 0) {
						node = node.replace('tbranch-','');
						href=$('#'+node).find('a:first').attr('href')+"&nodeid="+node;
					}else{
						href=$('#'+node).find('a:first').attr('href')+"&nodeid="+node.replace('tbranch-','');
					}

					$(this).jstree('select_node', node);

					if (href.search('undefined') < 0) {
						origHref = href;
						href=href.replace('action=tree', 'action=tree_content').replace('leaf_id=&', 'leaf_id='+leaf+'&');
						$.get(href, function(data) {
							$('#main').html(data);
							applySkin();
							var mytitle = 'Tree Mode - '+$('#nav_title').text();
							document.getElementsByTagName('title')[0].innerHTML = mytitle;
							if (typeof window.history.pushState !== 'undefined') {
								window.history.pushState({ page: origHref }, mytitle, origHref);
							}
						});
					}
				}
				$('#navigation').show();
				$('#navigation_right').show();
			})
			.on('changed.jstree', function(e, data) {
				resizeGraphContent();
			})
			.on('open_node.jstree', function(e, data) {
				resizeGraphContent();
			})
			.on('close_node.jstree', function(e, data) {
				resizeGraphContent();
			})
			.on('activate_node.jstree', function(e, data) {
				if (data.node.id) {
					if (data.node.id.search('tree_anchor') >= 0) {
						href=$('#'+data.node.id).find('a:first').attr('href')+"&nodeid=0";
					}else{
						href=$('#'+data.node.id).find('a:first').attr('href')+"&nodeid="+data.node.id.replace('tbranch-','');
					}
					origHref = href;
					href=href.replace('action=tree', 'action=tree_content');
					$.get(href, function(data) {
						$('#main').html(data);
						applySkin();
						var mytitle = 'Tree Mode - '+$('#nav_title').text();
						document.getElementsByTagName('title')[0].innerHTML = mytitle;
						if (typeof window.history.pushState !== 'undefined') {
							window.history.pushState({ page: origHref }, mytitle, origHref);
						}

				        window.scrollTo(0, 0);
						resizeGraphContent();
					});
					node = data.node.id;
				}
			})
			.jstree({
				'types' : {
					'device' : {
						icon : 'images/server.png',
						max_children : 0
					},
					'graph' : {
						icon : 'images/server_chart_curve.png',
						max_children : 0
					}
				},
				'core' : {
					'data' : {
						'url' : 'graph_view.php?action=get_node&tree_id=0',
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
				'state' : { 'key' : 'graph_tree_'+id },
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

		$(window).resize(function() {
			height = parseInt($(window).height()-$('.jstree').offset().top-10)+'px';
			$('#navigation').height(height+'px');
		});

		<?php print api_plugin_hook_function('top_graph_jquery_function');?>
	});
	</script>
	<?php
}

function draw_dhtml_tree_level($tree_id, $parent = 0, $graphing = false) {
	global $config;

	/* Record Start Time */
	$start = microtime(true);

	$dhtml_tree = array();

	$heirarchy = get_allowed_tree_level($tree_id, $parent);

	if (sizeof($heirarchy) > 0) {
		$dhtml_tree[] = "\t\t\t<ul>\n";
		foreach ($heirarchy as $leaf) {
			if ($leaf['host_id'] > 0) {  //It's a host
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . "_thost:" . $leaf['host_id'] . "' data-jstree='{ \"type\" : \"device\" }'>Device: " . htmlspecialchars($leaf['hostname']) . "\n";
				$dhtml_tree[] = "\t\t\t\t</li>\n";
			}elseif ($leaf['local_graph_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . "_tgraph:" . $leaf['local_graph_id'] . "' data-jstree='{ \"type\" : \"graph\" }'>Graph: " . htmlspecialchars(get_graph_title($leaf['local_graph_id'])) . "</a>\n";
				$dhtml_tree[] = "\t\t\t\t</li>\n";
			}else{ //It's not a host
				$dhtml_tree[] = "\t\t\t\t<li class='jstree-closed' id='tbranch:" . $leaf['id'] . "'>" . htmlspecialchars($leaf['title']) . "\n";
				$dhtml_tree[] = "\t\t\t\t</li>\n";
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

	$tree = db_fetch_cell("SELECT * FROM graph_tree WHERE id=$tree_id");

	$dhtml_tree = array();

	if (sizeof($heirarchy)) {
		if ($tree_id > 0) {
			$dhtml_tree[] = "\t\t\t<ul>\n";
			foreach ($heirarchy as $leaf) {
				if ($leaf['host_id'] > 0) {  //It's a host
					$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . "' data-jstree='{ \"icon\" : \"" . $config['url_path'] . "images/server.png\" }'><a href=\"" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree_id . '&leaf_id=' . $leaf['id'] . '&host_group_data=') . '">Device: ' . htmlspecialchars($leaf['hostname']) . "</a>\n";

					if (read_user_setting('expand_hosts') == 'on') {
						$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
						if ($leaf['host_grouping_type'] == HOST_GROUPING_GRAPH_TEMPLATE) {
							$graph_templates = get_allowed_graph_templates('gl.host_id=' . $leaf['host_id']);

							if (sizeof($graph_templates) > 0) {
								foreach ($graph_templates as $graph_template) {
									$dhtml_tree[] = "\t\t\t\t\t\t<li data-jstree='{ \"icon\" : \"" . $config['url_path'] . "images/server_chart.png\" }'><a href=\"" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree_id . '&leaf_id=' . $leaf['id'] . '&host_group_data=graph_template:' . $graph_template['id']) . '">' . htmlspecialchars($graph_template['name']) . "</a></li>\n";
								}
							}
						}else if ($leaf['host_grouping_type'] == HOST_GROUPING_DATA_QUERY_INDEX) {
							$data_queries = db_fetch_assoc("SELECT sq.id, sq.name
								FROM graph_local AS gl
								INNER JOIN snmp_query AS sq
								ON gl.snmp_query_id=sq.id
								AND gl.host_id=" . $leaf['host_id'] . "
								GROUP BY sq.id
							ORDER BY sq.name");

							array_push($data_queries, array(
								'id' => '0',
								'name' => 'Non Query Based'
							));

							foreach ($data_queries as $data_query) {
								/* fetch a list of field names that are sorted by the preferred sort field */
								$sort_field_data = get_formatted_data_query_indexes($leaf['host_id'], $data_query['id']);
								if ($data_query['id'] == 0) {
									$non_template_graphs = get_allowed_graphs('gl.host_id=' . $leaf['host_id'] . ' AND gl.snmp_query_id=0');
								}else{
									$non_template_graphs = 0;
								}
	
								if ((($data_query['id'] == 0) && (sizeof($non_template_graphs))) ||
									(($data_query['id'] > 0) && (sizeof($sort_field_data) > 0))) {
									if ($data_query['name'] != 'Non Query Based') {
										$dhtml_tree[] = "\t\t\t\t\t\t<li id='node" . $tree['id'] . '_' . $leaf['id'] . '_hgd_dq_' . $data_query['id'] . "' data-jstree='{ \"icon\" : \"" . $config['url_path'] . "images/server_dataquery.png\" }'><a class='treepick' href=\"" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree['id'] . '&leaf_id=' . $leaf['id'] . '&nodeid=node' . $tree['id'] . '_' . $leaf['id'] . '_hgd_dq_' . $data_query['id'] . '&host_group_data=data_query:' . $data_query['id']) . '">' . htmlspecialchars($data_query['name']) . "</a>\n";
									}else{
										$dhtml_tree[] = "\t\t\t\t\t\t<li id='node" . $tree['id'] . '_' . $leaf['id'] . '_hgd_dq_' . $data_query['id'] . "' data-jstree='{ \"icon\" : \"" . $config['url_path'] . "images/server_chart.png\" }'><a class='treepick' href=\"" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree['id'] . '&leaf_id=' . $leaf['id'] . '&nodeid=node'. $tree['id'] . '_' . $leaf['id'] . '_hgd_dq_' . $data_query['id'] . '&host_group_data=data_query:' . $data_query['id']) . '">' . htmlspecialchars($data_query['name']) . "</a>\n";
									}
	
									if ($data_query['id'] > 0) {
										$dhtml_tree[] = "\t\t\t\t\t\t\t<ul>\n";
										while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
											$dhtml_tree[] = "\t\t\t\t\t\t\t\t<li id='node" . $tree['id'] . '_' . $leaf['id'] . '_hgd_dq_' . $data_query['id'] . urlencode($snmp_index) . "' data-jstree='{ \"icon\" : \"" . $config['url_path'] . "images/server_chart_curve.png\" }'><a class='treepick' href=\"" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree['id'] . '&leaf_id=' . $leaf['id'] . '&nodeid=node' . $tree['id'] . '_' . $leaf['id'] . '_hgd_dq_' . $data_query['id'] . urlencode($snmp_index) . '&host_group_data=data_query_index:' . $data_query['id'] . ':' . urlencode($snmp_index)) . '">' . htmlspecialchars($sort_field_value) . "</a></li>\n";
										}
	
										$dhtml_tree[] = "\t\t\t\t\t\t\t</ul>\n";
										$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";
									}
								}
							}
	
							$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";
						}
	
						$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
					}
	
					$dhtml_tree[] = "\t\t\t\t</li>\n";
				}else{ //It's not a host
					$children = db_fetch_cell("SELECT COUNT(*) FROM graph_tree_items WHERE parent=" . $leaf['id'] . " AND local_graph_id=0");

					$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . "' " . ($children > 0 ? "class='jstree-closed'":"") . "><a href=\"" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree_id . '&leaf_id=' . $leaf['id'] . '&host_group_data=') . '">' . htmlspecialchars($leaf['title']) . "</a></li>\n";
				}
			}
	
			$dhtml_tree[] = "\t\t\t</ul>\n";
		}else{
			$dhtml_tree[] = "<ul>\n";
			foreach($heirarchy as $h) {
				$dhtml_tree[] = "<li id='tree_anchor-" . $h['tree_id'] . "' class='jstree-closed'><a href='" . htmlspecialchars("graph_view.php?action=tree&tree_id=" . $h['tree_id'] . "&leaf_id=0&host_group_data=") . "'>" . htmlspecialchars($h['title']) . "</a></li>\n";
			}
			$dhtml_tree[] = "</ul>\n";
		}
	}

	return $dhtml_tree;
}

function create_dhtml_tree() {
	global $config;

	$dhtml_tree = array();

	$tree_list = get_graph_tree_array();

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
		'columns' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => read_user_setting('num_columns_tree')
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => '1'
			),
		'tree_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => read_user_setting('default_tree_id')
			),
		'predefined_timeshift' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => read_user_setting('default_timeshift')
			),
		'predefined_timespan' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => read_user_setting('default_timespan')
			),
		'branch_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => ''
			),
		'leaf_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => ''
			),
		'nodeid' => array(
			'filter' => FILTER_VALIDATE_REGEXP, 
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_group_data' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
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

	$leaf = db_fetch_row("SELECT title, host_id, host_grouping_type
		FROM graph_tree_items
		WHERE id=$leaf_id");

	$leaf_type = api_tree_get_item_type($leaf_id);

	/* get information for the headers */
	if (!empty($tree_id)) { $tree_name = db_fetch_cell("SELECT name FROM graph_tree WHERE id=$tree_id"); }
	if (!empty($leaf_id)) { $leaf_name = $leaf['title']; }
	if (!empty($leaf_id)) { $host_name = db_fetch_cell("SELECT host.description FROM (graph_tree_items,host) WHERE graph_tree_items.host_id=host.id AND graph_tree_items.id=$leaf_id"); }

	$host_group_data_array = explode(':', $host_group_data);

	if ($host_group_data_array[0] == 'graph_template') {
		$host_group_data_name = '<strong>' . __('Graph Template:'). '</strong> ' . db_fetch_cell('SELECT name FROM graph_templates WHERE id=' . $host_group_data_array[1]);
		$graph_template_id = $host_group_data_array[1];
	}elseif ($host_group_data_array[0] == 'data_query') {
		$host_group_data_name = '<strong>' . __('Graph Template:') . '</strong> ' . (empty($host_group_data_array[1]) ? 'Non Query Based' : db_fetch_cell('SELECT name FROM snmp_query WHERE id=' . $host_group_data_array[1]));
		$data_query_id = $host_group_data_array[1];
	}elseif ($host_group_data_array[0] == 'data_query_index') {
		$host_group_data_name = '<strong>' . __('Graph Template:') . '</strong> ' . (empty($host_group_data_array[1]) ? 'Non Query Based' : db_fetch_cell('SELECT name FROM snmp_query WHERE id=' . $host_group_data_array[1])) . '-> ' . (empty($host_group_data_array[2]) ? 'Template Based' : get_formatted_data_query_index($leaf['host_id'], $host_group_data_array[1], $host_group_data_array[2]));
		$data_query_id = $host_group_data_array[1];
		$data_query_index = $host_group_data_array[2];
	}

	if (!empty($tree_name)) { $title .= $title_delimeter . '<strong>' . __('Tree:') . '</strong>' . htmlspecialchars($tree_name, ENT_QUOTES); $title_delimeter = '-> '; }
	if (!empty($leaf_name)) { $title .= $title_delimeter . '<strong>' . __('Leaf:') . '</strong>' . htmlspecialchars($leaf_name, ENT_QUOTES); $title_delimeter = '-> '; }
	if (!empty($host_name)) { $title .= $title_delimeter . '<strong>' . __('Device:') . '</strong>' . htmlspecialchars($host_name, ENT_QUOTES); $title_delimeter = '-> '; }
	if (!empty($host_group_data_name)) { $title .= $title_delimeter . " $host_group_data_name"; $title_delimeter = '-> '; }

	html_start_box(__('Graph Filters') . (strlen(get_request_var('filter')) ? " [ " . __('Filter') . " '" . htmlspecialchars(get_request_var('filter')) . "' " . __('Applied') . " ]" : ''), '100%', "", '3', 'center', '');

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
						<input id='filter' size='30' name='filter' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select name='graphs' id='graphs' onChange='applyGraphFilter()'>
							<?php
							if (sizeof($graphs_per_page) > 0) {
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
						<select name='columns' id='columns' onChange='applyGraphFilter()' <?php print get_request_var('thumbnails') == 'false' ? 'disabled':'';?>>
							<?php if ( get_request_var('thumbnails') == 'false') {?>
							<option value='<?php print get_request_var('columns');?>' selected><?php print __('N/A');?></option>
							<?php }else{?>
							<option value='1' <?php print (get_request_var('columns') == '1' ? ' selected':'');?>><?php print __('%d Column', 1);?></option>
							<option value='2' <?php print (get_request_var('columns') == '2' ? ' selected':'');?>><?php print __('%d Columns', 2);?></option>
							<option value='3' <?php print (get_request_var('columns') == '3' ? ' selected':'');?>><?php print __('%d Columns', 3);?></option>
							<option value='4' <?php print (get_request_var('columns') == '4' ? ' selected':'');?>><?php print __('%d Columns', 4);?></option>
							<option value='5' <?php print (get_request_var('columns') == '5' ? ' selected':'');?>><?php print __('%d Columns', 5);?></option>
							<option value='6' <?php print (get_request_var('columns') == '6' ? ' selected':'');?>><?php print __('%d Columns', 6);?></option>
							<?php }?>
						</select>
					</td>
					<td>
						<label for='thumbnails'><?php print __('Thumbnails');?></label>
					</td>
					<td>
						<input id='thumbnails' type='checkbox' name='thumbnails' onClick='applyGraphFilter()' <?php print ((get_request_var('thumbnails') == 'true') ? 'checked':'');?>>
					</td>
					<td>
						<input type='button' value='<?php print __('Go');?>' title='<?php print __('Set/Refresh Filter');?>' onClick='applyGraphFilter()'>
					</td>
					<td>
						<input type='button' value='<?php print __('Clear');?>' title='<?php print __('Clear Filters');?>' onClick='applyGraphFilter()'>
					</td>
					<?php if (is_view_allowed('graph_settings')) {?>
					<td>
						<input type='button' id='save' value='<?php print __('Save');?>' title='<?php print __('Save the current Graphs, Columns, Thumbnail, Preset, and Timeshift preferences to your profile');?>' onClick='saveGraphFilter("tree")'>
					</td>
					<td id='text'></td>
					<?php }?>
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
						<select id='predefined_timespan' name='predefined_timespan' onChange='applyGraphTimespan()'>
							<?php
							if (isset($_SESSION['custom'])) {
								$graph_timespans[GT_CUSTOM] = __('Custom');
								$start_val = 0;
								$end_val = sizeof($graph_timespans);
							} else {
								if (isset($graph_timespans[GT_CUSTOM])) {
									asort($graph_timespans);
									array_shift($graph_timespans);
								}
								$start_val = 1;
								$end_val = sizeof($graph_timespans)+1;
							}

							if (sizeof($graph_timespans) > 0) {
								for ($value=$start_val; $value < $end_val; $value++) {
									print "<option value='$value'"; if ($_SESSION['sess_current_timespan'] == $value) { print ' selected'; } print '>' . title_trim($graph_timespans[$value], 40) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('From');?>
					</td>
					<td>
						<input type='text' id='date1' size='18' value='<?php print (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
					</td>
					<td>
						<i id='startDate' class='calendar fa fa-calendar' title='<?php print __('Start Date Selector');?>'></i>
					</td>
					<td>
						<?php print __('To');?>
					</td>
					<td>
						<input type='text' id='date2' size='18' value='<?php print (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
					</td>
					<td>
						<i id='endDate' class='calendar fa fa-calendar' title='<?php print __('End Date Selector');?>'></i>
					</td>
					<td>
						<i class='shiftArrow fa fa-backward' onClick='timeshiftGraphFilterLeft()' title='<?php print __('Shift Time Backward');?>'></i>
					</td>
					<td>
						<select id='predefined_timeshift' name='predefined_timeshift' title='<?php print __('Define Shifting Interval');?>'>
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
					</td>
					<td>
						<i class='shiftArrow fa fa-forward' onClick='timeshiftGraphFilterRight()' title='<?php print __('Shift Time Forward');?>'></i>
					</td>
					<td>
						<input type='button' name='button_refresh_x' value='<?php print __('Refresh');?>' title='<?php print __('Refresh selected time span');?>' onClick='refreshGraphTimespanFilter()'>
					</td>
					<td>
						<input type='button' name='button_clear' value='<?php print __('Clear');?>' title='<?php print __('Return to the default time span');?>' onClick='clearGraphTimespanFilter()'>
					</td>
				</tr>
				<tr id='realtime' style='display:none;'>
					<td>
						<?php print __('Window');?>
					</td>
					<td>
						<select name='graph_start' id='graph_start' onChange='imageOptionsChanged("timespan")'>
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
						<select name='ds_step' id='ds_step' onChange="imageOptionsChanged('interval')">
							<?php
							foreach ($realtime_refresh as $interval => $text) {
								printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_dsstep'] ? ' selected="selected"' : '', $text);
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='realtimeoff' value='<?php print __('Stop');?>'>
					</td>
					<td align='center' colspan='6'>
						<span id='countdown'></span>
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
	var date1Open  = false;
	var date2Open  = false;

	$(function() {
		$('#startDate').click(function() {
			if (date1Open) {
				date1Open = false;
				$('#date1').datetimepicker('hide');
			}else{
				date1Open = true;
				$('#date1').datetimepicker('show');
			}
		});

		$('#endDate').click(function() {
			if (date2Open) {
				date2Open = false;
				$('#date2').datetimepicker('hide');
			}else{
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
		initializeGraphs();
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
		if (strlen(get_request_var('filter'))) {
			$sql_where = " (gtg.title_cache LIKE '%" . get_request_var('filter') . "%' OR gtg.title LIKE '%" . get_request_var('filter') . "%')";
		}

		$graph_list = get_allowed_tree_header_graphs($tree_id, $leaf_id, $sql_where);
	}elseif ($leaf_type == 'host') {
		/* graph template grouping */
		if ($leaf['host_grouping_type'] == HOST_GROUPING_GRAPH_TEMPLATE) {
			$sql_where       = 'gl.host_id=' . $leaf['host_id'] . (empty($graph_template_id) ? '' : ' AND gt.id=' . $graph_template_id);
			$graph_templates = get_allowed_graph_templates($sql_where);

			/* for graphs without a template */
			array_push($graph_templates, array(
				'id' => '0',
				'name' => '(No Graph Template)'
				));

			if (sizeof($graph_templates) > 0) {
			foreach ($graph_templates as $graph_template) {
				$sql_where = '';
				if (strlen(get_request_var('filter'))) {
					$sql_where = " (gtg.title_cache LIKE '%" . get_request_var('filter') . "%')";
				}
				$sql_where .= (strlen($sql_where) ? 'AND':'') . ' gl.graph_template_id=' . $graph_template['id'] . ' AND gl.host_id=' . $leaf['host_id'];

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
		}elseif ($leaf['host_grouping_type'] == HOST_GROUPING_DATA_QUERY_INDEX) {
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

			if (sizeof($data_queries) > 0) {
			foreach ($data_queries as $data_query) {
				$sql_where = '';

				/* fetch a list of field names that are sorted by the preferred sort field */
				$sort_field_data = get_formatted_data_query_indexes($leaf['host_id'], $data_query['id']);

				if (strlen(get_request_var('filter'))) {
					$sql_where = " (gtg.title_cache LIKE '%" . get_request_var('filter') . "%')";
				}

				/* grab a list of all graphs for this host/data query combination */
				$sql_where .= (strlen($sql_where) ? ' AND ':'') . ' gl.snmp_query_id=' . $data_query['id'] . ' AND gl.host_id=' . $leaf['host_id'] . "
                                        " . (empty($data_query_index) ? '' : " AND gl.snmp_index='$data_query_index'");

				$graphs = get_allowed_graphs($sql_where);

				/* re-key the results on data query index */
				$snmp_index_to_graph = array();
				if (sizeof($graphs) > 0) {
					/* let's sort the graphs naturally */
					usort($graphs, 'naturally_sort_graphs');

					foreach ($graphs as $graph) {
						$snmp_index_to_graph{$graph['snmp_index']}{$graph['local_graph_id']} = $graph['title_cache'];
						$graphs_height[$graph['local_graph_id']] = $graph['height'];
						$graphs_width[$graph['local_graph_id']] = $graph['width'];
					}
				}

				/* using the sorted data as they key; grab each snmp index from the master list */
				while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
					/* render each graph for the current data query index */
					if (isset($snmp_index_to_graph[$snmp_index])) {
						while (list($local_graph_id, $graph_title) = each($snmp_index_to_graph[$snmp_index])) {
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
	$nav = html_nav_bar("graph_view.php?action=tree_content&tree_id=$tree_id&leaf_id=$leaf_id&nodeid=" . get_request_var('nodeid') . '&host_group_data=' . get_request_var('host_group_data'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('graphs'), $total_rows, get_request_var('columns'), __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', "", '3', 'center', '');

	/* start graph display */
	print "<tr class='tableHeader'><td style='width:390px;' colspan='11' class='graphSubHeaderColumn textHeaderDark'>$title</td></tr>";

	$i = get_request_var('graphs') * (get_request_var('page') - 1);
	$last_graph = $i + get_request_var('graphs');

	$new_graph_list = array();
	while ($i < $total_rows && $i < $last_graph) {
		$new_graph_list[] = $graph_list[$i];
		$i++;
	}

	if (get_request_var('thumbnails') == 'true') {
		html_graph_thumbnail_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}else{
		html_graph_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', 1);
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

