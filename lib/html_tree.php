<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
	global $current_user;

	if (isset_request_var('hide')) {
		if ((get_request_var('hide') == '0') || (get_request_var('hide') == '1')) {
			/* only update expand/contract info is this user has rights to keep their own settings */
			if ((isset($current_user)) && ($current_user['graph_settings'] == 'on')) {
				db_execute_prepared('DELETE FROM settings_tree
					WHERE graph_tree_item_id = ?
					AND user_id = ?',
					array(get_request_var('branch_id'), $_SESSION['sess_user_id']));

				db_execute_prepared('INSERT INTO settings_tree
					(graph_tree_item_id, user_id,status)
					VALUES (?, ?, ?)',
					array(get_request_var('branch_id'), $_SESSION['sess_user_id'], get_request_var('hide')));
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

	if (cacti_sizeof($branches)) {
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

			print "<option value='" . $leaf['id'] . "'$html_selected>$indent " . html_escape($leaf['title']) . '</option>';

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
		$user = db_fetch_row_prepared('SELECT policy_trees
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

		if ($user['policy_trees'] == 1) {
			$default_tree_id = db_fetch_cell_prepared('SELECT graph_tree.id
				FROM graph_tree
				LEFT JOIN user_auth_perms
				ON user_auth_perms.item_id = graph_tree.id
				AND user_auth_perms.type = 2
				AND user_auth_perms.user_id = ?
				WHERE user_auth_perms.item_id IS NULL
				AND graph_tree.enabled = "on"
				ORDER BY graph_tree.id
				LIMIT 1',
				array($_SESSION['sess_user_id']));
		} else {
			$default_tree_id = db_fetch_cell_prepared('SELECT graph_tree.id
				FROM graph_tree
				INNER JOIN user_auth_perms
				ON user_auth_perms.item_id = graph_tree.id
				AND user_auth_perms.type = 2
				AND user_auth_perms.user_id = ?
				WHERE graph_tree.enabled = "on"
				ORDER BY graph_tree.id
				LIMIT 1',
				array($_SESSION['sess_user_id']));
		}
	} else {
		$default_tree_id = db_fetch_cell('SELECT id
			FROM graph_tree
			ORDER BY sequence
			LIMIT 1');
	}

	print "<div class='cactiTreeSearch' style='white-space:nowrap'><span style='padding-right:4px;'>" . __('Search') . "</span><input type='text' class='ui-state-default ui-corner-all' id='searcher' style='padding:2px;font-size:12px;max-width:200px;' size='35'><hr></div>\n";

	$dhtml_tree = create_dhtml_tree();
	if (cacti_sizeof($dhtml_tree)) {
		print "<div id='jstree'></div>\n";
	}

	?>
	<script type='text/javascript'>
	<?php
	if (isset_request_var('hyper')) {
		$path = json_encode(get_tree_path());
		?>var nodes = <?php print $path;?>;<?php
	} else {
		?>var nodes = [];<?php
	}
	?>

	var search_to = false;

	<?php
	if (read_user_setting('tree_history') != 'on') {
		print 'window.onunload = function() { localStorage.removeItem(\'graph_tree_history\'); }';
	}
	?>

	function checkTreeForLogout() {
		html = $('#jstree').html();
		found = html.indexOf('<?php print __('Login to Cacti');?>');
		if (found >= 0) {
			document.location = 'logout.php';
		}
	}

	function openNodes() {
		if (nodes.length > 0) {
			var deffereds = $.Deferred(function (def) { def.resolve(); });
			var lastNode  = nodes[nodes.length-1];

			for (var j = 0; j <= nodes.length-1; j++) {
				deffereds = (function(name, deferreds) {
					return deferreds.pipe(function () {
						return $.Deferred(function(def) {
							id = $('a[id^='+name+']').first().attr('id');

							if (lastNode == name) {
								$('#jstree').jstree('select_node', id, function() {
									def.resolve();
								});
							} else {
								$('#jstree').jstree('open_node', id, function() {
									$('.cactiConsoleNavigationArea').css('overflow-y', 'auto');
									def.resolve();
								});
							}
						});
					});
				})(nodes[j], deffereds);
			}
		}
	}

	$(function () {
		$('#jstree').each(function(data) {
			var id=$(this).attr('id');

			$(this)
			.on('init.jstree', function() {
				if (nodes.length > 0) {
					$('#jstree').jstree().clear_state();
				}
				resizeTreePanel();
			})
			.on('loaded.jstree', function() {
				openNodes();
				resizeTreePanel();
			})
			.on('ready.jstree', function() {
				resizeTreePanel();
			})
			.on('changed.jstree', function() {
				resizeTreePanel();
			})
			.on('before_open.jstree', function() {
				checkTreeForLogout();
			})
			.on('after_open.jstree', function() {
				resizeTreePanel();
				responsiveResizeGraphs();
			})
			.on('after_close.jstree', function() {
				resizeTreePanel();
				responsiveResizeGraphs();
			})
			.on('select_node.jstree', function(e, data) {
				if (data.node.id) {
					if (data.node.id.search('tree_anchor') >= 0) {
						href=$('#'+data.node.id).find('a:first').attr('href');
					} else {
						href=$('#'+data.node.id).find('a:first').attr('href');
					}

					origHref = href;

					if (typeof href !== 'undefined') {
						href = href.replace('action=tree', 'action=tree_content');
						href = href + '&hyper=true';
						$('.cactiGraphContentArea').hide();
						loadPage(href);
					}

					node = data.node.id;
				}
				resizeTreePanel();
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
					},
					'site' : {
						icon : urlPath+'images/site.png',
						max_children : 0
					},
					'location' : {
						icon : urlPath+'images/location.png',
						max_children : 0
					},
					'host_template' : {
						icon : urlPath+'images/server_device_template.png',
						max_children : 0
					},
					'graph_templates' : {
						icon : urlPath+'images/server_graph_template.png',
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
				'search' : { 'case_sensitive' : false, 'show_only_matches' : true, 'ajax' : { 'url' : urlPath+'graph_view.php?action=ajax_search'} },
				'plugins' : [ 'types', 'state', 'wholerow', 'search' ]
			});
		});

		$('#searcher').keyup(function() {
			if(search_to) { clearTimeout(search_to); }
			search_to = setTimeout(function() {
				var v = $('#searcher').val();
				if (v.length >= 3) {
					$('#jstree').jstree('search', v, false);
				}else {
                    $('#jstree').jstree('search', '', false);
                }
			}, 250);
		});

		<?php print api_plugin_hook_function('top_graph_jquery_function');?>
	});

	</script>
	<?php
}

function get_tree_path() {
	if (isset_request_var('node')) {
		$nodes  = array();
		$nnodes = array();
		$rnodes = array();

		if (strpos(get_request_var('node'), 'tbranch') !== false) {
			$parts = explode('-', get_request_var('node'));
			$node  = $parts[1];

			$linknode = db_fetch_row_prepared('SELECT *
				FROM graph_tree_items
				WHERE id = ?',
				array($node));

			if (cacti_sizeof($linknode)) {
				$nodes[] = 'tree_anchor-' . $linknode['graph_tree_id'] . '_anchor';

				$nstack = 'tbranch-' . $linknode['id'];

				if (get_request_var('site_id') > 0) {
					$nstack .= '-site-' . get_request_var('site_id');
					$nnodes[] = $nstack;

					if (isset_request_var('gti')) {
						$nstack .= '-gts';
						$nnodes[] = $nstack;
					} else {
						if (get_request_var('host_template_id') > 0) {
							$nstack .= '-ht-' . get_request_var('host_template_id');
							$nnodes[] = $nstack;
						}

						if (get_request_var('host_id') > 0) {
							$nstack .= '-host-' . get_request_var('host_id');
							$nnodes[] = $nstack;
						}
					}
				} else {
					$nnodes[] = $nstack;
				}

				if (isset_request_var('hgd')) {
					$parts = explode(':', get_request_var('hgd'));
					switch($parts[0]) {
						case 'gt':
							$nnodes[] = $nstack . '-gt-' . $parts[1];
							break;
						case 'dq':
							$nnodes[] = $nstack . '-dq-' . $parts[1];
							break;
						case 'dqi':
							$nnodes[] = $nstack . '-dqi-' . $parts[1] . '-' . $parts[2];
							$nnodes[] = $nstack . '-dq-'  . $parts[1];
							break;
						default:
							break;
					}
				}

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
			}

			if (cacti_sizeof($rnodes)) {
				$rnodes = array_reverse($rnodes);
				$nodes  = array_merge($nodes, $rnodes);
			}

			if (cacti_sizeof($nnodes)) {
				$nodes = array_merge($nodes, $nnodes);
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

function get_device_leaf_class($host_id) {
	$status = db_fetch_cell_prepared('SELECT status FROM host WHERE id = ?', array($host_id));
	switch($status) {
		case HOST_DOWN:
			$class = 'deviceDown';
			break;
		case HOST_RECOVERING:
			$class = 'deviceRecovering';
			break;
		case HOST_UP:
			$class = 'deviceUp';
			break;
		case HOST_UNKNOWN:
			$class = 'deviceUnknown';
			break;
		case HOST_ERROR:
			$class = 'deviceError';
			break;
		default:
			$class = '';
	}

	return $class;
}

function draw_dhtml_tree_level($tree_id, $parent = 0, $editing = false) {
	$dhtml_tree = array();

	$hierarchy = get_allowed_tree_level($tree_id, $parent, $editing);

	if (cacti_sizeof($hierarchy)) {
		$dhtml_tree[] = "\t\t\t<ul>\n";
		foreach ($hierarchy as $leaf) {
			if ($leaf['host_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . "_thost:" . $leaf['host_id'] . "' data-jstree='{ \"type\" : \"device\" }'>" . html_escape(strip_domain($leaf['hostname'])) . "</li>\n";
			} elseif ($leaf['site_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . "_tsite:" . $leaf['site_id'] . "' data-jstree='{ \"type\" : \"site\" }'>" . html_escape($leaf['sitename']) . "</a></li>\n";
			} elseif ($leaf['local_graph_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . "_tgraph:" . $leaf['local_graph_id'] . "' data-jstree='{ \"type\" : \"graph\" }'>" . html_escape(get_graph_title_cache($leaf['local_graph_id'])) . "</a></li>\n";
			} else {
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

	$hierarchy = get_allowed_tree_content($tree_id, $parent);

	$dhtml_tree = array();

	if (cacti_sizeof($hierarchy)) {
		if ($tree_id > 0) {
			$dhtml_tree[] = "\t\t\t<ul>\n";

			foreach ($hierarchy as $leaf) {
				if ($leaf['site_id'] > 0) {  // It's a site
					$dhtml_tree = array_merge($dhtml_tree, create_site_branch($leaf));
				} elseif ($leaf['host_id'] > 0) {  // It's a host
					$dhtml_tree = array_merge($dhtml_tree, create_host_branch($leaf));
				} else { //It's not a host
					$dhtml_tree = array_merge($dhtml_tree, create_branch($leaf));
				}
			}

			$dhtml_tree[] = "\t\t\t</ul>\n";
		} else {
			$dhtml_tree[] = "<ul>\n";

			foreach($hierarchy as $h) {
				$dhtml_tree[] = "<li id='tree_anchor-" . $h['tree_id'] . "' data-jstree='{ \"type\" : \"tree\" }' class='jstree-closed'><a href='" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tree_anchor-' . $h['tree_id'] . '&site_id=-1&host_id=-1&host_template_id=-1&hgd=') . "'>" . html_escape($h['title']) . "</a></li>\n";
			}

			$dhtml_tree[] = "</ul>\n";
		}
	}

	return $dhtml_tree;
}

function create_site_branch($leaf) {
	global $config, $unique_id;

	$unique_id++;

	$dhtml_tree   = array();

	$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . "-site-" . $leaf['site_id'] . "' data-jstree='{ \"type\" : \"site\" }'><a href=\"" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&host_id=-1&host_template_id=-1&hgd=') . '">' . html_escape($leaf['sitename']) . "</a>\n";

	$devices = get_allowed_site_devices($leaf['site_id'], '', 'ht.name ASC, h1.description ASC');
	$ht_name = '';

	if (cacti_sizeof($devices)) {
		$dhtml_tree[] = "\t\t\t\t\t<ul>\n";

		foreach($devices as $d) {
			if ($ht_name != $d['host_template_name']) {
				if ($ht_name != '') {
					$dhtml_tree[] = "</ul></li>\n";
				}

				$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-site-' . $leaf['site_id'] . '-ht-' . $d['host_template_id'] . "' data-jstree='{ \"type\" : \"host_template\" }'><a href='" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&host_template_id=' . $d['host_template_id'] . '&host_id=-1&hgd=') . "'>" . html_escape($d['host_template_name']) . "</a><ul>\n";
			}

			$hleaf = $leaf;
			$hleaf['hostname'] = strip_domain($d['description']);
			$hleaf['host_id']  = $d['id'];

			$dhtml_tree = array_merge($dhtml_tree, create_host_branch($hleaf, $leaf['site_id'], $d['host_template_id']));

			$ht_name = $d['host_template_name'];
		}
		$dhtml_tree[] = "</ul></li>\n";

		$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
	}

	// suppress total rows collection
	$total_rows = -1;

	$graph_templates = get_allowed_graph_templates('h.site_id=' . $leaf['site_id'], 'name', '', $total_rows);

	if (cacti_sizeof($graph_templates)) {
		$dhtml_tree[] = "\t\t\t\t\t\t<ul>\n";
		$dhtml_tree[] = "\t\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-site-' . $leaf['site_id'] . '-gts' . "' data-jstree='{ \"type\" : \"graph_templates\" }'><a href='" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&gti=-1&host_id=-1&host_template_id=-1&hgd=') . "'>" . __('Graph Templates') . "</a>\n";
		$dhtml_tree[] = "\t\t\t\t\t\t\t<ul>\n";

		foreach ($graph_templates as $graph_template) {
			$dhtml_tree[] = "\t\t\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-site-' . $leaf['site_id'] . '-gts-gt-' . $graph_template['id'] . "' data-jstree='{ \"type\" : \"graph_template\" }'><a href='" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&gti=' . $graph_template['id'] . '&host_id=' . $leaf['host_id'] . '&host_template_id=-1&hgd=gt:' . $graph_template['id']) . "'>" . html_escape($graph_template['name']) . "</a></li>\n";
		}

		$dhtml_tree[] = "\t\t\t\t\t\t\t</ul>\n";
		$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";
		$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
	}

	$dhtml_tree[] = "\t\t\t\t</li>\n";

	return $dhtml_tree;
}

function create_branch($leaf) {
	global $config;

	$dhtml_tree = array();

	$children = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM graph_tree_items
		WHERE parent = ?
		AND local_graph_id=0',
		array($leaf['id']));

	$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . "' " . ($children > 0 ? "class='jstree-closed'":"") . "><a href=\"" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=-1&host_id=-1&host_template_id=-1&hgd=') . '">' . html_escape($leaf['title']) . "</a></li>\n";

	return $dhtml_tree;
}

function create_host_branch($leaf, $site_id = -1, $ht = -1) {
	global $config, $unique_id;

	$unique_id++;

	if (isset($leaf['host_id']) && $leaf['host_id'] > 0) {
		$class = get_device_leaf_class($leaf['host_id']);
	} else {
		$class = '';
	}

	$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id:'') . ($ht > 0 ? '-ht-' . $ht:'') . '-host-' . $leaf['host_id'] . '-uid-' . $unique_id . "' data-jstree='{ \"type\" : \"device\" }'><a class='$class' href=\"" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht .'&hgd=') . '">' . html_escape(strip_domain($leaf['hostname'])) . "</a>\n";

	if (read_user_setting('expand_hosts') == 'on') {
		if ($leaf['host_grouping_type'] == HOST_GROUPING_DATA_QUERY_INDEX) {
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree = array_merge($dhtml_tree, create_data_query_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
		} elseif ($leaf['host_grouping_type'] == HOST_GROUPING_GRAPH_TEMPLATE) {
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree = array_merge($dhtml_tree, create_graph_template_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
		} else {
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree = array_merge($dhtml_tree, create_graph_template_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree = array_merge($dhtml_tree, create_data_query_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
		}
	}

	$dhtml_tree[] = "\t\t\t\t</li>\n";

	return $dhtml_tree;
}

function create_graph_template_branch($leaf, $site_id = -1, $ht = -1) {
	global $config, $unique_id;

	$dhtml_tree = array();

	// suppress total rows collection
	$total_rows = -1;

	$graph_templates = get_allowed_graph_templates('gl.host_id=' . $leaf['host_id'], 'name', '', $total_rows);

	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			$unique_id++;

			$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht:'') . '-host-' . $leaf['host_id']:'') . '-gt-' . $graph_template['id'] . "-uid-$unique_id' data-jstree='{ \"type\" : \"graph_template\" }'><a href='" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=gt:' . $graph_template['id']) . "'>" . html_escape($graph_template['name']) . "</a></li>\n";
		}
	}

	return $dhtml_tree;
}

function create_data_query_branch($leaf, $site_id = -1, $ht = -1) {
	global $config, $unique_id;

	$dhtml_tree = array();

	$data_queries = db_fetch_assoc_prepared('SELECT sq.id, sq.name
		FROM graph_local AS gl
		INNER JOIN snmp_query AS sq
		ON gl.snmp_query_id=sq.id
		AND gl.host_id = ?
		GROUP BY sq.id
		ORDER BY sq.name',
		array($leaf['host_id']));

	array_push($data_queries, array(
		'id' => '0',
		'name' => __('Non Query Based')
	));

	if (cacti_sizeof($data_queries)) {
		if ($leaf['host_id'] > 0) {
			$ntg = get_allowed_graphs('gl.host_id=' . $leaf['host_id'] . ' AND gl.snmp_query_id=0');
			if (read_user_setting('show_aggregates', 'on') == 'on') {
				$agg = get_allowed_aggregate_graphs('gl.host_id=' . $leaf['host_id'] . ' AND gl.snmp_query_id=0');
			} else {
				$agg = array();
			}
		} else {
			$ntg = get_allowed_graphs('gl.snmp_query_id=0');
			if (read_user_setting('show_aggregates', 'on') == 'on') {
				$agg = get_allowed_aggregate_graphs('gl.snmp_query_id=0');
			} else {
				$agg = array();
			}
		}

		$ntg = array_merge($ntg, $agg);

		foreach ($data_queries as $data_query) {
			if ($data_query['id'] == 0) {
				$non_tg = $ntg;
				$sfd = array();
			} else {
				$non_tg = array();

				/* fetch a list of field names that are sorted by the preferred sort field */
				$sfd = get_formatted_data_query_indexes($leaf['host_id'], $data_query['id']);
			}

			if (($data_query['id'] == 0 && cacti_sizeof($non_tg)) || ($data_query['id'] > 0 && cacti_sizeof($sfd))) {
				$unique_id++;

				if ($data_query['name'] != __('Non Query Based')) {
					$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht:'') . '-host-' . $leaf['host_id']:'') . '-dq-' . $data_query['id'] . "-uid-$unique_id' data-jstree='{ \"type\" : \"data_query\" }'><a class='treepick' href=\"" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=dq:' . $data_query['id']) . '">' . html_escape($data_query['name']) . "</a>\n";
				} else {
					$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht:'') . '-host-' . $leaf['host_id']:'') . '-dq-' . $data_query['id'] . "-uid-$unique_id' data-jstree='{ \"type\" : \"data_query\" }'><a class='treepick' href=\"" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=dq:' . $data_query['id']) . '">' . html_escape($data_query['name']) . "</a>\n";
				}

				if ($data_query['id'] > 0) {
					$dhtml_tree[] = "\t\t\t\t\t\t\t<ul>\n";
					foreach ($sfd as $snmp_index => $sort_field_value) {
						$unique_id++;

						$dhtml_tree[] = "\t\t\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht:'') . '-host-' . $leaf['host_id']:'') . '-dq-' . $data_query['id'] . '-' . urlencode($snmp_index) . "-uid-$unique_id' data-jstree='{ \"type\" : \"graph\" }'><a class='treepick' href='" . html_escape($config['url_path'] . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=dqi:' . $data_query['id'] . ':' . $snmp_index) . "'>" . html_escape($sort_field_value) . "</a></li>\n";
					}

					$dhtml_tree[] = "\t\t\t\t\t\t\t</ul>\n";
					$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";
				}
			}
		}
	}

	$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";

	return $dhtml_tree;
}

function create_dhtml_tree() {
	$dhtml_tree = array();

	$tree_list = get_allowed_trees();

	if (cacti_sizeof($tree_list)) {
		foreach ($tree_list as $tree) {
			$dhtml_tree['tree:'.$tree['id']] = true;
		}
	}

	return $dhtml_tree;
}

function html_validate_tree_vars() {
	static $count = false;

	// prevent double calls in the same stack
	if ($count) {
		return false;
	}

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
			'default' => '-1'
			),
		'columns' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('num_columns_tree', '2')
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
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
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
			'default' => read_user_setting('thumbnail_section_tree_2') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_grt');
	/* ================= input validation ================= */

	$count = true;
}

function grow_right_pane_tree($tree_id, $leaf_id, $host_group_data) {
	global $current_user, $config, $graphs_per_page, $graph_timeshifts;

	include($config['include_path'] . '/global_arrays.php');
	include_once($config['library_path'] . '/data_query.php');
	include_once($config['library_path'] . '/html_utility.php');

	if (empty($tree_id)) { return; }
	if (empty($leaf_id)) { $leaf_id = 0; }

	$sql_where            = '';
	$sql_join             = '';
	$title                = '';
	$host_name            = '';
	$site_name            = '';
	$host_template_name   = '';
	$title_delimiter      = '';
	$host_group_data_name = '';
	$graph_template_id    = '-1';
	$data_query_id        = '-1';
	$data_query_index     = '';
	$leaf_names           = array();

	$leaf = db_fetch_row_prepared('SELECT
		title, host_id, site_id, host_grouping_type, parent
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

	if (isset($leaf['title']) && $leaf['title'] != '') {
		$leaf_names[] = $leaf['title'];
	}

	if (($leaf_type == 'site' || $leaf_type == 'host') && $leaf['parent'] != 0) {
		$parent     = $leaf['parent'];
		$leaf_names = array();

		while ($parent != 0) {
			$pleaf = db_fetch_row_prepared('SELECT * FROM graph_tree_items
				WHERE id = ?',
				array($parent));

			if (cacti_sizeof($pleaf)) {
				$leaf_names[] = $pleaf['title'];
				$parent      = $pleaf['parent'];
			} else {
				break;
			}
		}
	}

	if (!empty($leaf_id)) {
		$host_name = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			array($leaf['host_id']));

		$site_name = db_fetch_cell_prepared('SELECT name
			FROM sites
			WHERE id = ?',
			array($leaf['site_id']));
	}

	if (isset_request_var('host_id') && get_request_var('host_id') > 0 && $host_name == '') {
		$host_name = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			array(get_request_var('host_id')));
	}

	if (isset_request_var('site_id') && get_request_var('site_id') > 0 && $site_name == '') {
		$site_name = db_fetch_cell_prepared('SELECT name
			FROM sites
			WHERE id = ?',
			array(get_request_var('site_id')));
	}

	if (isset_request_var('host_template_id') && get_request_var('host_template_id') > 0) {
		$host_template_name = db_fetch_cell_prepared('SELECT name
			FROM host_template
			WHERE id = ?',
			array(get_request_var('host_template_id')));
	}

	$host_group_data_array = explode(':', $host_group_data);

	if ($host_group_data_array[0] == 'gt') {
		$name = db_fetch_cell_prepared('SELECT name
			FROM graph_templates
			WHERE id = ?',
			array($host_group_data_array[1]));

		$host_group_data_name = '<strong>' . __('Graph Template:'). '</strong> ' . $name;
		$graph_template_id    = $host_group_data_array[1];
	} elseif ($host_group_data_array[0] == 'dq') {
		$name = db_fetch_cell_prepared('SELECT name
			FROM snmp_query
			WHERE id = ?',
			array($host_group_data_array[1]));

		$host_group_data_name = '<strong>' . __('Graph Template:') . '</strong> ' . (empty($host_group_data_array[1]) ? __('Non Query Based') : $name);
		$data_query_id        = $host_group_data_array[1];
	} elseif ($host_group_data_array[0] == 'dqi') {
		$name = db_fetch_cell_prepared('SELECT name
			FROM snmp_query
			WHERE id = ?',
			array($host_group_data_array[1]));

		$host_group_data_name = '<strong>' . __('Graph Template:') . '</strong> ' . (empty($host_group_data_array[1]) ? __('Non Query Based') : $name) . '-> ' . (empty($host_group_data_array[2]) ? __('Template Based') : get_formatted_data_query_index($leaf['host_id'], $host_group_data_array[1], $host_group_data_array[2]));
		$data_query_id    = $host_group_data_array[1];
		$data_query_index = $host_group_data_array[2];
	}

	if ($tree_name != '') {
		$title .= $title_delimiter . '<strong>' . __('Tree:') . '</strong> ' . html_escape($tree_name);
		$title_delimiter = '-> ';
	}

	if ($site_name != '') {
		$title .= $title_delimiter . '<strong>' . __('Site:') . '</strong>&nbsp;' . html_escape($site_name);
		$title_delimiter = '-> ';
	}

	if (cacti_sizeof($leaf_names)) {
		foreach($leaf_names as $leaf_name) {
			$title .= $title_delimiter . '<strong>' . __('Leaf:') . '</strong> ' . html_escape($leaf_name);
			$title_delimiter = '-> ';
		}
	}

	if ($host_template_name != '') {
		$title .= $title_delimiter . '<strong>' . __('Device Template:') . '</strong> ' . html_escape($host_template_name);
		$title_delimiter = '-> ';
	}

	if ($host_name != '') {
		$title .= $title_delimiter . '<strong>' . __('Device:') . '</strong> ' . html_escape($host_name);
		$title_delimiter = '-> ';
	}

	if ($host_group_data_name != '') {
		$title .= $title_delimiter . " $host_group_data_name";
		$title_delimiter = '-> ';
	}

	html_start_box(__('Graph Filters') . (get_request_var('rfilter') != '' ? " [ " . __('Filter') . " '" . html_escape_request_var('rfilter') . "' " . __('Applied') . " ]" : ''), '100%', "", '3', 'center', '');

	?>
	<tr class='even noprint' id='search'>
		<td class='noprint'>
		<form id='form_graph_view' method='post' onSubmit='applyGraphFilter();return false'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='55' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id' multiple style='opacity:0.1;overflow-y:auto;overflow-x:hide;height:0px;'>
							<option value='-1'<?php if (get_request_var('graph_template_id') == '-1') {?> selected<?php }?>><?php print __('All Graphs & Templates');?></option>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('Not Templated');?></option>
							<?php
							// suppress total rows collection
							$total_rows = -1;

							$graph_templates = get_allowed_graph_templates('', 'name', '', $total_rows);

							if (cacti_sizeof($graph_templates)) {
								$selected    = explode(',', get_request_var('graph_template_id'));
								foreach ($graph_templates as $gt) {
									$found = db_fetch_cell_prepared('SELECT id
										FROM graph_local
										WHERE graph_template_id = ? LIMIT 1',
										array($gt['id']));

									if ($found) {
										print "<option value='" . $gt['id'] . "'";
										if (cacti_sizeof($selected)) {
											if (in_array($gt['id'], $selected)) {
												print ' selected';
											}
										}
										print '>' . html_escape($gt['name']) . '</option>';
									}
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filter');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<?php if (is_view_allowed('graph_settings')) {?>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='save' value='<?php print __esc('Save');?>' title='<?php print __esc('Save the current Graphs, Columns, Thumbnail, Preset, and Timeshift preferences to your profile');?>'>
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
							if (cacti_sizeof($graphs_per_page)) {
								foreach ($graphs_per_page as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('graphs') == $key) { print ' selected'; } print '>' . $value . '</option>';
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
		<form name='form_timespan_selector' method='post' action='<?php print $config['url_path'];?>graph_view.php'>
			<table class='filterTable'>
				<tr id='timespan'>
					<td>
						<?php print __('Presets');?>
					</td>
					<td>
						<select id='predefined_timespan' onChange='applyGraphTimespan()'>
							<?php
							$graph_timespans = array_merge(array(GT_CUSTOM => __('Custom')), $graph_timespans);

							$start_val = 0;
							$end_val   = cacti_sizeof($graph_timespans);

							if (cacti_sizeof($graph_timespans)) {
								foreach($graph_timespans as $value => $text) {
									print "<option value='$value'"; if ($_SESSION['sess_current_timespan'] == $value) { print ' selected'; } print '>' . html_escape($text) . '</option>';
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
							<input type='text' class='ui-state-default ui-corner-all' id='date1' size='18' value='<?php print (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
							<i id='startDate' class='calendar fa fa-calendar-alt' title='<?php print __esc('Start Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<?php print __('To');?>
					</td>
					<td>
						<span>
							<input type='text' class='ui-state-default ui-corner-all' id='date2' size='18' value='<?php print (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
							<i id='endDate' class='calendar fa fa-calendar-alt' title='<?php print __esc('End Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<i class='shiftArrow fa fa-backward' onClick='timeshiftGraphFilterLeft()' title='<?php print __esc('Shift Time Backward');?>'></i>
							<select id='predefined_timeshift' title='<?php print __esc('Define Shifting Interval');?>'>
								<?php
								$start_val = 1;
								$end_val = cacti_sizeof($graph_timeshifts)+1;
								if (cacti_sizeof($graph_timeshifts)) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'"; if ($_SESSION['sess_current_timeshift'] == $shift_value) { print ' selected'; } print '>' . html_escape($graph_timeshifts[$shift_value]) . '</option>';
									}
								}
								?>
							</select>
							<i class='shiftArrow fa fa-forward' onClick='timeshiftGraphFilterRight()' title='<?php print __esc('Shift Time Forward');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='tsrefresh' value='<?php print __esc('Refresh');?>' title='<?php print __esc('Refresh selected time span');?>' onClick='refreshGraphTimespanFilter()'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='tsclear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Return to the default time span');?>' onClick='clearGraphTimespanFilter()'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr id='realtime' style='display:none;'>
					<td>
						<?php print __('Window');?>
					</td>
					<td>
						<select id='graph_start' onChange='realtimeGrapher()'>
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
						<select id='ds_step' onChange="realtimeGrapher()">
							<?php
							foreach ($realtime_refresh as $interval => $text) {
								printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_dsstep'] ? ' selected="selected"' : '', $text);
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' class='ui-button ui-corner-all ui-widget' id='realtimeoff' value='<?php print __esc('Stop');?>'>
					</td>
					<td class='center' colspan='6'>
						<span id='countdown'></span>
					</td>
					<td>
						<input id='future' type='hidden' value='<?php print read_config_option('allow_graph_dates_in_future');?>'></input>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr><?php
	html_end_box();

	?>
	<script type='text/javascript'>

	var graph_start = <?php print get_current_graph_start();?>;
	var graph_end   = <?php print get_current_graph_end();?>;
	var timeOffset  = <?php print date('Z');?>;
	var pageAction  = 'tree';
	var graphPage   = '<?php print $config['url_path'];?>graph_view.php';
	var hgd         = '<?php print $host_group_data;?>';
	var date1Open   = false;
	var date2Open   = false;

	function initPage() {
		<?php html_graph_template_multiselect();?>

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
		$('#go').off('click').on('click', function(event) {
			event.preventDefault();
			applyGraphFilter();
		});

		$('#clear').off('click').on('click', function() {
			clearGraphFilter();
		});

		$('#save').off('click').on('click', function() {
			 saveGraphFilter('tree');
		});

		$.when(initPage()).done(function() {
			initializeGraphs();
		});
	});

	</script>
	<?php
	html_spikekill_js();

	api_plugin_hook_function('graph_tree_page_buttons',
		array(
			'treeid'    => $tree_id,
			'leafid'    => $leaf_id,
			'mode'      => 'tree',
			'timespan'  => $_SESSION['sess_current_timespan'],
			'starttime' => get_current_graph_start(),
			'endtime'   => get_current_graph_end()
		)
	);

	$graph_list = array();

	if (($leaf_type == 'header') || (empty($leaf_id))) {
		$sql_where = '';

		if (get_request_var('rfilter') != '') {
			$sql_where .= ' (gtg.title_cache RLIKE "' . get_request_var('rfilter') . '" OR gtg.title RLIKE "' . get_request_var('rfilter') . '")';
		}

		if (isset_request_var('graph_template_id') && get_request_var('graph_template_id') >= 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
		}

		$graph_list = get_allowed_tree_header_graphs($tree_id, $leaf_id, $sql_where);
	} elseif ($leaf_type == 'host') {
		if ($graph_template_id == '-1') {
			$graph_template_id = get_request_var('graph_template_id');
		}

		$graph_list = get_host_graph_list($leaf['host_id'], $graph_template_id, $data_query_id, $leaf['host_grouping_type'], $data_query_index);
	} elseif ($leaf_type == 'site') {
		$sql_where = '';

		if (isset_request_var('site_id') && get_filter_request_var('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.site_id = ' . get_request_var('site_id');
		}

		if (isset_request_var('host_template_id') && get_filter_request_var('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.host_template_id = ' . get_request_var('host_template_id');
		}

		if (isset_request_var('host_id') && get_filter_request_var('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.host_id = ' . get_request_var('host_id');
		}

		if (isset_request_var('hgd')) {
			$parts = explode(':', get_request_var('hgd'));
			switch($parts[0]) {
				case 'gt':
					input_validate_input_number($parts[1]);
					$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.graph_template_id = ' . $parts[1];
					break;
				case 'dq':
					input_validate_input_number($parts[1]);
					$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.snmp_query_id = ' . $parts[1];
					break;
				case 'dqi':
					input_validate_input_number($parts[1]);
					$dqi = db_qstr($parts[2]);
					$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.snmp_query_id = ' . $parts[1] . ' AND snmp_index = ' . $dqi;
					break;
				default:
					break;
			}
		}

		if (get_request_var('rfilter') != '') {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . '(gtg.title_cache RLIKE "' . get_request_var('rfilter') . '")';
		}

		if (isset_request_var('graph_template_id') && get_request_var('graph_template_id') >= 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . '(gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
		}

		$graphs = get_allowed_graphs($sql_where);
		if (read_user_setting('show_aggregates', 'on') == 'on') {
			$agg = get_allowed_aggregate_graphs($sql_where);
		} else {
			$agg = array();
		}

		$graphs = array_merge($graphs, $agg);

		/* let's sort the graphs naturally */
		usort($graphs, 'naturally_sort_graphs');

		if (cacti_sizeof($graphs)) {
			foreach ($graphs as $graph) {
				array_push($graph_list, $graph);
			}
		}
	}

	$total_rows = cacti_sizeof($graph_list);

	/* generate page list */
	$nav = html_nav_bar($config['url_path'] . 'graph_view.php?action=tree_content&tree_id=' . $tree_id . '&leaf_id=' . $leaf_id . '&node=' . get_request_var('node') . '&hgd=' . $host_group_data, MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('graphs'), $total_rows, get_request_var('columns'), __('Graphs'), 'page', 'main');

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
		html_graph_thumbnail_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'), $tree_id, $leaf_id);
	} else {
		html_graph_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'), $tree_id, $leaf_id);
	}

	if (!empty($leaf_id)) {
		api_plugin_hook_function('tree_after', $host_name . ',' . get_nfilter_request_var('leaf_id'));
	}

	api_plugin_hook_function('tree_view_page_end');

	html_end_box();

	if ($total_rows) {
		print $nav;
	}
}

function get_host_graph_list($host_id, $graph_template_id, $data_query_id, $host_grouping_type = '', $data_query_index = '') {
	$graph_list = array();
	$sql_where  = '';

	/* graph template grouping */
	if ($host_grouping_type == HOST_GROUPING_GRAPH_TEMPLATE) {
		if ($host_id > 0) {
			$sql_where = 'gl.host_id=' . $host_id;
		}

		if ($graph_template_id != '' && $graph_template_id != '-1' && $graph_template_id != '0') {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . $graph_template_id . '))';
		} elseif ($graph_template_id == 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . $graph_template_id . '))';
		}

		// suppress total rows collection
		$total_rows = -1;

		$graph_templates = get_allowed_graph_templates('', 'name', '', $total_rows);
		$final_templates = array();
		if ($graph_template_id != '' && $graph_template_id != '-1' && $graph_template_id != '0') {
			$templates = explode(',', $graph_template_id);
			foreach($templates as $id) {
				$ptemplates[$id]['id'] = $id;
			}

			foreach($graph_templates as $template) {
				if (isset($ptemplates[$template['id']])) {
					$final_templates[$template['id']]['id']   = $template['id'];
					$final_templates[$template['id']]['name'] = $template['name'];
				}
			}
		} elseif ($graph_template_id == '0') {
			$final_templates = array();
		} else {
			$final_templates = $graph_templates;
		}

		/* for graphs without a template */
		array_push(
			$final_templates, array(
				'id'   => '0',
				'name' => __('(Non Graph Template)')
			)
		);

		if (cacti_sizeof($final_templates)) {
			$sql_where = '';
			if (get_request_var('rfilter') != '') {
				$sql_where = ' (gtg.title_cache RLIKE "' . get_request_var('rfilter') . '")';
			}

			if ($host_id > 0) {
				$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.host_id=' . $host_id;
			}

			$graph_template_ids = [];

			foreach ($final_templates as $graph_template) {
				array_push($graph_template_ids, $graph_template['id']);
			}

			$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.graph_template_id IN (' . implode(', ', $graph_template_ids) . ')';
			$graphs = get_allowed_graphs($sql_where);

			if (read_user_setting('show_aggregates', 'on') == 'on') {
				$agg = get_allowed_aggregate_graphs($sql_where);
			} else {
				$agg = array();
			}

			$graphs = array_merge($graphs, $agg);

			/* let's sort the graphs naturally */
			usort($graphs, 'naturally_sort_graphs');

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $graph) {
					$graph['graph_template_name'] = $graph_template['name'];
					array_push($graph_list, $graph);
				}
			}
		}
	} elseif ($host_grouping_type == HOST_GROUPING_DATA_QUERY_INDEX) {
		/* data query index grouping */
		if ($host_id > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.host_id=' . $host_id;
		}

		if ($data_query_id >= 0) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'sq.id=' . $data_query_id;
		}

		$data_queries = db_fetch_assoc("SELECT sq.id, sq.name
			FROM graph_local AS gl
			INNER JOIN snmp_query AS sq
			ON gl.snmp_query_id=sq.id
			$sql_where
			GROUP BY sq.id
			ORDER BY sq.name");

		/* for graphs without a data query */
		if ($data_query_id <= 0) {
			array_push($data_queries,
				array(
					'id' => '0',
					'name' => __('Non Query Based')
				)
			);
		}

		if (cacti_sizeof($data_queries)) {
			foreach ($data_queries as $data_query) {
				$sql_where = '';

				/* fetch a list of field names that are sorted by the preferred sort field */
				$sfd = get_formatted_data_query_indexes($host_id, $data_query['id']);

				if (get_request_var('rfilter') != '') {
					$sql_where = ' (gtg.title_cache RLIKE "' . get_request_var('rfilter') . '")';
				}

				/* grab a list of all graphs for this host/data query combination */
				$sql_where .= ($sql_where != '' ? ' AND ':'') .
					'gl.snmp_query_id=' . $data_query['id'] . ($host_id > 0 ? ' AND gl.host_id=' . $host_id:'') .
					' ' . ($data_query_index != '' ? ' AND gl.snmp_index = ' . db_qstr($data_query_index): '');

				$graphs = get_allowed_graphs($sql_where);

				if (read_user_setting('show_aggregates', 'on') == 'on') {
					$agg = get_allowed_aggregate_graphs($sql_where);
				} else {
					$agg = array();
				}

				$graphs = array_merge($graphs, $agg);

				/* re-key the results on data query index */
				$snmp_index_to_graph = array();
				if (cacti_sizeof($graphs)) {
					/* let's sort the graphs naturally */
					usort($graphs, 'naturally_sort_graphs');

					foreach ($graphs as $graph) {
						$snmp_index_to_graph[$graph['snmp_index']][$graph['local_graph_id']] = $graph['title_cache'];
						$graphs_height[$graph['local_graph_id']] = $graph['height'];
						$graphs_width[$graph['local_graph_id']] = $graph['width'];
					}
				}

				/* using the sorted data as they key; grab each snmp index from the master list */
				foreach ($sfd as $snmp_index => $sort_field_value) {
					/* render each graph for the current data query index */
					if (isset($snmp_index_to_graph[$snmp_index])) {
						foreach ($snmp_index_to_graph[$snmp_index] as $local_graph_id => $graph_title) {
							/* reformat the array so it's compatable with the html_graph* area functions */
							array_push($graph_list, array(
								'data_query_name'  => $data_query['name'],
								'sort_field_value' => $sort_field_value,
								'local_graph_id'   => $local_graph_id,
								'title_cache'      => $graph_title,
								'height'           => $graphs_height[$local_graph_id],
								'width'            => $graphs_width[$local_graph_id]
							));
						}
					}
				}
			}
		}
	}

	return $graph_list;
}
