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

/**
 * Processes the tree settings for the current user.
 *
 * @return void
 */
function process_tree_settings() : void {
	global $current_user;

	if (isrv('hide')) {
		if ((grv('hide') == '0') || (grv('hide') == '1')) {
			// only update expand/contract info is this user has rights to keep their own settings
			if ((isset($current_user)) && ($current_user['graph_settings'] == 'on')) {
				db_execute_prepared('DELETE FROM settings_tree
					WHERE graph_tree_item_id = ?
					AND user_id = ?',
					[grv('branch_id'), $_SESSION[SESS_USER_ID]]);

				db_execute_prepared('INSERT INTO settings_tree
					(graph_tree_item_id, user_id,status)
					VALUES (?, ?, ?)',
					[grv('branch_id'), $_SESSION[SESS_USER_ID], grv('hide')]);
			}
		}
	}
}

/**
 * Generates a dropdown tree structure for a given tree ID.
 *
 * @param int    $tree_id               The ID of the tree to generate the dropdown for.
 * @param mixed  $parent                The parent ID to start building the tree from. Default is 0 (root).
 * @param string $form_name             The name attribute for the <select> element.
 * @param string $selected_tree_item_id The ID of the tree item to be selected by default.
 * @param int    $tier                  The current tier level in the tree. Default is 0.
 *
 * @return void
 */
function grow_dropdown_tree(int $tree_id, mixed $parent = 0, string $form_name = '', string $selected_tree_item_id = '', int $tier = 0) : void {
	$tier++;

	$branches = db_fetch_assoc_prepared('SELECT gti.id, gti.title, parent
		FROM graph_tree_items AS gti
		WHERE gti.graph_tree_id = ?
		AND gti.host_id = 0
		AND gti.local_graph_id = 0
		AND parent = ?
		ORDER BY parent, position',
		[$tree_id, $parent]);

	if ($parent == 0) {
		print "<select name='$form_name' id='$form_name'>";
		print "<option value='0'>[root]</option>";
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

			print "<option value='" . $leaf['id'] . "'$html_selected>$indent " . htmle($leaf['title']) . '</option>';

			grow_dropdown_tree($tree_id, $leaf['id'], $form_name, $selected_tree_item_id, $tier);
		}
	}

	if ($parent == 0) {
		print '</select>';
	}
}

/**
 * Generates and displays the DHTML trees
 *
 * @return void
 */
function grow_dhtml_trees() : void {
	include_once(CACTI_PATH_LIBRARY . '/data_query.php');

	draw_tree_filter();

	$default_tree_id = read_user_setting('default_tree_id');

	if (empty($default_tree_id)) {
		$user = db_fetch_row_prepared('SELECT policy_trees
			FROM user_auth
			WHERE id = ?',
			[$_SESSION[SESS_USER_ID]]);

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
				[$_SESSION[SESS_USER_ID]]);
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
				[$_SESSION[SESS_USER_ID]]);
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
	if (isrv('hyper')) {
		$path = json_encode(get_tree_path());
		?>var nodes = <?php print $path; ?>;<?php
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

	function resizeTreePanel() {
		var docHeight      = $(window).outerHeight();
		var navWidth       = $('.cactiTreeNavigationArea').width();
		var searchHeight   = $('.cactiTreeSearch').outerHeight();
		var pageHeadHeight = $('.cactiPageHead').outerHeight();
		var breadCrHeight  = $('.breadCrumbBar').outerHeight();
		var pageBottomHeight = $('.cactiPageBottom').outerHeight();
		//console.log('----------------------');

		var jsTreeHeight  = Math.max.apply(Math, $('#jstree').children(':visible').map(function() {
			return $(this).outerHeight();
		}).get());

		var treeAreaHeight = docHeight - pageHeadHeight - breadCrHeight - searchHeight - pageBottomHeight;
		//console.log('docHeight:' + docHeight);
		//console.log('searchHeight:' + searchHeight);
		//console.log('pageHeadHeight:' + pageHeadHeight);
		//console.log('pageBottomHeight:' + pageBottomHeight);
		//console.log('breadCrHeight:' + breadCrHeight);
		//console.log('jsTreeHeight:' + jsTreeHeight);
		//console.log('treeAreaHeight:' + treeAreaHeight);

		$('#jstree').height(jsTreeHeight + 30);
		$('.cactiTreeNavigationArea').height(treeAreaHeight+searchHeight);

		var visWidth = Math.max.apply(Math, $('#jstree').children(':visible').map(function() {
			return $(this).width();
		}).get());

		var minWidth = <?php print read_user_setting('min_tree_width'); ?>;
		var maxWidth = <?php print read_user_setting('max_tree_width'); ?>;

		if (visWidth < minWidth) {
			$('.cactiTreeNavigationArea').width(minWidth);
			$('.cactiGraphContentArea').css('margin-left', minWidth+5);
			$('.cactiTreeNavigationArea').css('overflow-x', '');
		} else if (visWidth > maxWidth) {
			$('.cactiTreeNavigationArea').width(maxWidth);
			$('.cactiGraphContentArea').css('margin-left', maxWidth+5);
			$('.cactiTreeNavigationArea').css('overflow-x', 'auto');
		} else if (visWidth > navWidth) {
			$('.cactiTreeNavigationArea').width(visWidth);
			$('.cactiGraphContentArea').css('margin-left', visWidth+5);
			$('.cactiTreeNavigationArea').css('overflow-x', 'auto');
		} else {
			$('.cactiTreeNavigationArea').width(navWidth);
			$('.cactiGraphContentArea').css('margin-left', navWidth+5);
			$('.cactiTreeNavigationArea').css('overflow-x', '');
		}
	}

	function checkTreeForLogout() {
		html = $('#jstree').html();
		found = html.indexOf('<?php print __('Login to Cacti'); ?>');
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
			var pageGraphFirstLoad = true;

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
				pageGraphFirstLoad = false;
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
						loadUrl({url:href,noState:pageGraphFirstLoad});
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
							if (node.id != '#') {
								return { 'id' : node.id }
							}
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

		$('#searcher').delayKeyup(function() {
			var v = $('#searcher').val();
			if (v.length >= 3) {
				$('#jstree').jstree('search', v, false);
			}else {
				$('#jstree').jstree('search', '', false);
			}
		});

		<?php print api_plugin_hook_function('top_graph_jquery_function'); ?>
	});

	</script>
	<?php
}

/**
 * Retrieves the tree path based on the request variables.
 *
 * This function constructs an array of node identifiers representing the path
 * in a tree structure. It processes the request variables to determine the
 * specific nodes and their hierarchy.
 *
 * @return array An array of node identifiers representing the tree path.
 */
function get_tree_path() : array {
	if (isrv('node')) {
		$nodes  = [];
		$nnodes = [];
		$rnodes = [];

		if (str_contains(grv('node'), 'tbranch')) {
			$parts = explode('-', grv('node'));
			$node  = $parts[1];

			$linknode = db_fetch_row_prepared('SELECT *
				FROM graph_tree_items
				WHERE id = ?',
				[$node]);

			if (cacti_sizeof($linknode)) {
				$nodes[] = 'tree_anchor-' . $linknode['graph_tree_id'] . '_anchor';

				$nstack = 'tbranch-' . $linknode['id'];

				if (grv('site_id') > 0) {
					$nstack .= '-site-' . grv('site_id');
					$nnodes[] = $nstack;

					if (isrv('gti')) {
						$nstack .= '-gts';
						$nnodes[] = $nstack;
					} else {
						if (grv('host_template_id') > 0) {
							$nstack .= '-ht-' . grv('host_template_id');
							$nnodes[] = $nstack;
						}

						if (grv('host_id') > 0) {
							$nstack .= '-host-' . grv('host_id');
							$nnodes[] = $nstack;
						}
					}
				} else {
					$nnodes[] = $nstack;
				}

				if (isrv('hgd')) {
					$parts = explode(':', grv('hgd'));

					switch($parts[0]) {
						case 'gt':
							$nnodes[] = $nstack . '-gt-' . $parts[1];

							break;
						case 'dq':
							$nnodes[] = $nstack . '-dq-' . $parts[1];

							break;
						case 'dqi':
							$nnodes[] = $nstack . '-dqi-' . $parts[1] . '-' . $parts[2];
							$nnodes[] = $nstack . '-dq-' . $parts[1];

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
							[$linknode['parent']]);
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
		} elseif (str_contains(grv('node'), 'tree_anchor')) {
			$parts   = explode('-', grv('node'));
			$nodes[] = 'tree_anchor-' . $parts[1] . '_anchor';
		}

		return $nodes;
	} else {
		return [];
	}
}

/**
 * Get the CSS class for a device based on its status.
 *
 * @param  int    $host_id The ID of the host device.
 * @return string The CSS class name corresponding to the device's status.
 *
 * @return string
 */
function get_device_leaf_class(int $host_id) : string {
	$status = db_fetch_cell_prepared('SELECT status FROM host WHERE id = ?', [$host_id]);

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

/**
 * Draws a DHTML tree level for a given tree ID and parent node.
 *
 * @param int  $tree_id The ID of the tree to draw.
 * @param int  $parent  The ID of the parent node (default is 0).
 * @param bool $editing Whether the tree is in editing mode (default is false).
 *
 * @return array An array of HTML strings representing the DHTML tree level.
 */
function draw_dhtml_tree_level(int $tree_id, int $parent = 0, bool $editing = false) : array {
	$dhtml_tree = [];

	$hierarchy = get_allowed_tree_level($tree_id, $parent, $editing);

	if (cacti_sizeof($hierarchy)) {
		$dhtml_tree[] = "\t\t\t<ul>\n";

		foreach ($hierarchy as $leaf) {
			if ($leaf['host_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . '_thost:' . $leaf['host_id'] . "' data-jstree='{ \"type\" : \"device\" }'>" . htmle(strip_domain($leaf['hostname'])) . "</li>\n";
			} elseif ($leaf['site_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . '_tsite:' . $leaf['site_id'] . "' data-jstree='{ \"type\" : \"site\" }'>" . htmle($leaf['sitename']) . "</a></li>\n";
			} elseif ($leaf['local_graph_id'] > 0) {
				$dhtml_tree[] = "\t\t\t\t<li id='tbranch:" . $leaf['id'] . '_tgraph:' . $leaf['local_graph_id'] . "' data-jstree='{ \"type\" : \"graph\" }'>" . htmle(get_graph_title_cache($leaf['local_graph_id'])) . "</a></li>\n";
			} else {
				$dhtml_tree[] = "\t\t\t\t<li class='jstree-closed' id='tbranch:" . $leaf['id'] . "'>" . htmle($leaf['title']) . "</li>\n";
			}
		}

		$dhtml_tree[] = "\t\t\t</ul>\n";
	}

	return $dhtml_tree;
}

/**
 * Draws a DHTML tree level for graphing.
 *
 * This function generates the HTML structure for a DHTML tree based on the given tree ID and parent node.
 * It includes branches for sites, hosts, and other elements, and returns the generated HTML as an array of strings.
 *
 * @param int $tree_id The ID of the tree to draw.
 * @param int $parent  The ID of the parent node. Defaults to 0.
 *
 * @return array The generated HTML structure as an array of strings.
 */
function draw_dhtml_tree_level_graphing(int $tree_id, int $parent = 0) : array {
	include_once(CACTI_PATH_LIBRARY . '/data_query.php');

	$hierarchy = get_allowed_tree_content($tree_id, $parent);

	$dhtml_tree = [];

	if (cacti_sizeof($hierarchy)) {
		if ($tree_id > 0) {
			$dhtml_tree[] = "\t\t\t<ul>\n";

			foreach ($hierarchy as $leaf) {
				if ($leaf['site_id'] > 0) {  // It's a site
					$dhtml_tree = array_merge($dhtml_tree, create_site_branch($leaf));
				} elseif ($leaf['host_id'] > 0) {  // It's a host
					$dhtml_tree = array_merge($dhtml_tree, create_host_branch($leaf));
				} else { // It's not a host
					$dhtml_tree = array_merge($dhtml_tree, create_branch($leaf));
				}
			}

			$dhtml_tree[] = "\t\t\t</ul>\n";
		} else {
			$dhtml_tree[] = "<ul>\n";

			foreach ($hierarchy as $h) {
				$dhtml_tree[] = "<li id='tree_anchor-" . $h['tree_id'] . "' data-jstree='{ \"type\" : \"tree\" }' class='jstree-closed'><a href='" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tree_anchor-' . $h['tree_id'] . '&site_id=-1&host_id=-1&host_template_id=-1&hgd=') . "'>" . htmle($h['title']) . "</a></li>\n";
			}

			$dhtml_tree[] = "</ul>\n";
		}
	}

	return $dhtml_tree;
}

/**
 * Creates a site branch in the DHTML tree structure.
 *
 * This function generates a hierarchical tree structure for a given site,
 * including its devices and graph templates.
 *
 * @param array $leaf An associative array containing site information.
 *
 * @return array An array of strings representing the DHTML tree structure.
 */
function create_site_branch(array $leaf) : array {
	global $unique_id;

	$unique_id++;

	$dhtml_tree   = [];

	$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-site-' . $leaf['site_id'] . "' data-jstree='{ \"type\" : \"site\" }'><a href=\"" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&host_id=-1&host_template_id=-1&hgd=') . '">' . htmle($leaf['sitename']) . "</a>\n";

	$devices = get_allowed_site_devices($leaf['site_id'], '', 'ht.name ASC, h1.description ASC');
	$ht_name = '';

	if (cacti_sizeof($devices)) {
		$dhtml_tree[] = "\t\t\t\t\t<ul>\n";

		foreach ($devices as $d) {
			if ($ht_name != $d['host_template_name']) {
				if ($ht_name != '') {
					$dhtml_tree[] = "</ul></li>\n";
				}

				$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-site-' . $leaf['site_id'] . '-ht-' . $d['host_template_id'] . "' data-jstree='{ \"type\" : \"host_template\" }'><a href='" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&host_template_id=' . $d['host_template_id'] . '&host_id=-1&hgd=') . "'>" . htmle($d['host_template_name']) . "</a><ul>\n";
			}

			$hleaf             = $leaf;
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
		$dhtml_tree[] = "\t\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-site-' . $leaf['site_id'] . '-gts' . "' data-jstree='{ \"type\" : \"graph_templates\" }'><a href='" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&gti=-1&host_id=-1&host_template_id=-1&hgd=') . "'>" . __('Graph Templates') . "</a>\n";
		$dhtml_tree[] = "\t\t\t\t\t\t\t<ul>\n";

		foreach ($graph_templates as $graph_template) {
			$dhtml_tree[] = "\t\t\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . '-site-' . $leaf['site_id'] . '-gts-gt-' . $graph_template['id'] . "' data-jstree='{ \"type\" : \"graph_template\" }'><a href='" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=' . $leaf['site_id'] . '&gti=' . $graph_template['id'] . '&host_id=' . $leaf['host_id'] . '&host_template_id=-1&hgd=gt:' . $graph_template['id']) . "'>" . htmle($graph_template['name']) . "</a></li>\n";
		}

		$dhtml_tree[] = "\t\t\t\t\t\t\t</ul>\n";
		$dhtml_tree[] = "\t\t\t\t\t\t</li>\n";
		$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
	}

	$dhtml_tree[] = "\t\t\t\t</li>\n";

	return $dhtml_tree;
}

/**
 * Creates a branch in the DHTML tree structure.
 *
 * This function generates a list item (`<li>`) element for a given leaf node
 * in the tree. It checks if the leaf node has children and assigns the appropriate
 * CSS class to indicate whether the node is closed or not. The function also
 * constructs a URL for the leaf node and escapes it for HTML output.
 *
 * @param array $leaf An associative array representing the leaf node. It should
 *
 * @return array An array containing the generated HTML for the leaf node.
 */
function create_branch(array $leaf) : array {
	$dhtml_tree = [];

	$children = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM graph_tree_items
		WHERE parent = ?
		AND local_graph_id=0',
		[$leaf['id']]);

	$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . "' " . ($children > 0 ? "class='jstree-closed'" : '') . '><a href="' . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&site_id=-1&host_id=-1&host_template_id=-1&hgd=') . '">' . htmle($leaf['title']) . "</a></li>\n";

	return $dhtml_tree;
}

/**
 * Creates a host branch in the DHTML tree structure.
 *
 * @param array $leaf    The leaf node containing host information.
 * @param int   $site_id The site ID associated with the host (default is -1).
 * @param int   $ht      The host template ID (default is -1).
 *
 * @return array The DHTML tree structure with the host branch added.
 */
function create_host_branch(array $leaf, int $site_id = -1, int $ht = -1) : array {
	global $unique_id;

	$unique_id++;

	if (isset($leaf['host_id']) && $leaf['host_id'] > 0) {
		$class = get_device_leaf_class($leaf['host_id']);
	} else {
		$class = '';
	}

	$dhtml_tree[] = "\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id : '') . ($ht > 0 ? '-ht-' . $ht : '') . '-host-' . $leaf['host_id'] . '-uid-' . $unique_id . "' data-jstree='{ \"type\" : \"device\" }'><a class='$class' href=\"" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=') . '">' . htmle(strip_domain($leaf['hostname'])) . "</a>\n";

	if (read_user_setting('expand_hosts') == 'on') {
		if ($leaf['host_grouping_type'] == HOST_GROUPING_DATA_QUERY_INDEX) {
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree   = array_merge($dhtml_tree, create_data_query_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
		} elseif ($leaf['host_grouping_type'] == HOST_GROUPING_GRAPH_TEMPLATE) {
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree   = array_merge($dhtml_tree, create_graph_template_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
		} else {
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree   = array_merge($dhtml_tree, create_graph_template_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
			$dhtml_tree[] = "\t\t\t\t\t<ul>\n";
			$dhtml_tree   = array_merge($dhtml_tree, create_data_query_branch($leaf, $site_id, $ht));
			$dhtml_tree[] = "\t\t\t\t\t</ul>\n";
		}
	}

	$dhtml_tree[] = "\t\t\t\t</li>\n";

	return $dhtml_tree;
}

/**
 * Creates a branch of graph templates for a given host in a DHTML tree structure.
 *
 * @param array $leaf    An associative array containing information about the host.
 * @param int   $site_id Optional. The ID of the site. Default is -1.
 * @param int   $ht      Optional. The ID of the host template. Default is -1.
 *
 * @return array An array of HTML list items representing the graph templates.
 */
function create_graph_template_branch(array $leaf, int $site_id = -1, int $ht = -1) : array {
	global $unique_id;

	$dhtml_tree = [];

	// suppress total rows collection
	$total_rows = -1;

	$graph_templates = get_allowed_graph_templates('gl.host_id=' . $leaf['host_id'], 'name', '', $total_rows);

	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			$unique_id++;

			$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht : '') . '-host-' . $leaf['host_id'] : '') . '-gt-' . $graph_template['id'] . "-uid-$unique_id' data-jstree='{ \"type\" : \"graph_template\" }'><a href='" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=gt:' . $graph_template['id']) . "'>" . htmle($graph_template['name']) . "</a></li>\n";
		}
	}

	return $dhtml_tree;
}

/**
 * Creates a data query branch for a given leaf and site.
 *
 * This function generates a hierarchical tree structure for data queries
 * associated with a specific host. It supports both query-based and non-query-based
 * data sources and includes options for sorting and filtering based on user settings.
 *
 * @param array $leaf    The leaf node containing host information.
 * @param int   $site_id The site ID (default is -1).
 * @param int   $ht      The host template ID (default is -1).
 *
 * @return array The generated DHTML tree structure.
 */
function create_data_query_branch(array $leaf, int $site_id = -1, int $ht = -1) : array {
	global $unique_id;

	$dhtml_tree = [];

	$data_queries = db_fetch_assoc_prepared('SELECT sq.id, sq.name
		FROM graph_local AS gl
		INNER JOIN snmp_query AS sq
		ON gl.snmp_query_id=sq.id
		AND gl.host_id = ?
		GROUP BY sq.id
		ORDER BY sq.name',
		[$leaf['host_id']]);

	array_push($data_queries, [
		'id'   => '0',
		'name' => __('Non Query Based')
	]);

	if (cacti_sizeof($data_queries)) {
		if ($leaf['host_id'] > 0) {
			if (read_config_option('dsstats_enable') == 'on' && grv('graph_source') != '' && grv('graph_order') != '') {
				$sql_order = [
					'data_source'  => grv('graph_source'),
					'order'        => grv('graph_order'),
					'start_time'   => get_current_graph_start(),
					'end_time'     => get_current_graph_end(),
					'cf'           => grv('cf'),
					'measure'      => grv('measure')
				];
			} else {
				$sql_order = 'gtg.title_cache';
			}

			$ntg = get_allowed_graphs('gl.host_id=' . $leaf['host_id'] . ' AND gl.snmp_query_id=0', $sql_order);

			if (read_user_setting('show_aggregates', 'on') == 'on') {
				$agg = get_allowed_aggregate_graphs('gl.host_id=' . $leaf['host_id'] . ' AND gl.snmp_query_id=0');
			} else {
				$agg = [];
			}
		} else {
			if (read_config_option('dsstats_enable') == 'on' && grv('graph_source') != '' && grv('graph_order') != '') {
				$sql_order = [
					'data_source'  => grv('graph_source'),
					'order'        => grv('graph_order'),
					'start_time'   => get_current_graph_start(),
					'end_time'     => get_current_graph_end(),
					'cf'           => grv('cf'),
					'measure'      => grv('measure')
				];
			} else {
				$sql_order = 'gtg.title_cache';
			}

			$ntg = get_allowed_graphs('gl.snmp_query_id=0', $sql_order);

			if (read_user_setting('show_aggregates', 'on') == 'on') {
				$agg = get_allowed_aggregate_graphs('gl.snmp_query_id=0');
			} else {
				$agg = [];
			}
		}

		$ntg = array_merge($ntg, $agg);

		foreach ($data_queries as $data_query) {
			if ($data_query['id'] == 0) {
				$non_tg = $ntg;
				$sfd    = [];
			} else {
				$non_tg = [];

				// fetch a list of field names that are sorted by the preferred sort field
				$sfd = get_formatted_data_query_indexes($leaf['host_id'], $data_query['id']);
			}

			if (($data_query['id'] == 0 && cacti_sizeof($non_tg)) || ($data_query['id'] > 0 && cacti_sizeof($sfd))) {
				$unique_id++;

				if ($data_query['name'] != __('Non Query Based')) {
					$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht : '') . '-host-' . $leaf['host_id'] : '') . '-dq-' . $data_query['id'] . "-uid-$unique_id' data-jstree='{ \"type\" : \"data_query\" }'><a class='treepick' href=\"" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=dq:' . $data_query['id']) . '">' . htmle($data_query['name']) . "</a>\n";
				} else {
					$dhtml_tree[] = "\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht : '') . '-host-' . $leaf['host_id'] : '') . '-dq-' . $data_query['id'] . "-uid-$unique_id' data-jstree='{ \"type\" : \"data_query\" }'><a class='treepick' href=\"" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=dq:' . $data_query['id']) . '">' . htmle($data_query['name']) . "</a>\n";
				}

				if ($data_query['id'] > 0) {
					$dhtml_tree[] = "\t\t\t\t\t\t\t<ul>\n";

					foreach ($sfd as $snmp_index => $sort_field_value) {
						$unique_id++;

						$dhtml_tree[] = "\t\t\t\t\t\t\t\t<li id='tbranch-" . $leaf['id'] . ($site_id > 0 ? '-site-' . $site_id . ($ht > 0 ? '-ht-' . $ht : '') . '-host-' . $leaf['host_id'] : '') . '-dq-' . $data_query['id'] . '-' . urlencode($snmp_index) . "-uid-$unique_id' data-jstree='{ \"type\" : \"graph\" }'><a class='treepick' href='" . htmle(CACTI_PATH_URL . 'graph_view.php?action=tree&node=tbranch-' . $leaf['id'] . '&host_id=' . $leaf['host_id'] . '&site_id=' . $site_id . '&host_template_id=' . $ht . '&hgd=dqi:' . $data_query['id'] . ':' . $snmp_index) . "'>" . htmle($sort_field_value) . "</a></li>\n";
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

/**
 * Creates a DHTML tree structure.
 *
 * This function generates a DHTML tree structure by retrieving a list of allowed trees
 * and marking each tree as true in the resulting array.
 *
 * @return array An associative array where the keys are tree identifiers prefixed with 'tree:'
 *               and the values are set to true.
 */
function create_dhtml_tree() : array {
	$dhtml_tree = [];

	$tree_list = get_allowed_trees();

	if (cacti_sizeof($tree_list)) {
		foreach ($tree_list as $tree) {
			$dhtml_tree['tree:' . $tree['id']] = true;
		}
	}

	return $dhtml_tree;
}

function create_tree_filter() : array {
	global $item_rows;

	$all     = ['-1' => __('All')];
	$any     = ['-1' => __('Any')];
	$none    = ['0'  => __('None')];

	// unset the ordering if we have a setup that does not support ordering
	if (isrv('graph_template_id')) {
		if (str_contains(gnrv('graph_template_id'), ',') || gnrv('graph_template_id') <= 0) {
			srv('graph_order', '');
			srv('graph_source', '');
		}
	}

	if (gfrv('host_id') == 0) {
		$templates = get_allowed_graph_templates_normalized('gl.host_id=0', 'name', '', $total_rows);
	} elseif (gfrv('host_id') > 0) {
		$templates = get_allowed_graph_templates_normalized('gl.host_id=' . gfrv('host_id'), 'name', '', $total_rows);
	} else {
		$templates = get_allowed_graph_templates_normalized('', 'name', '', $total_rows);
	}

	$normalized_templates = [];

	if (cacti_sizeof($templates)) {
		foreach ($templates as $t) {
			$normalized_templates[$t['id']] = $t['name'];
		}
	}

	$columns = [
		'1' => __('%d Column', 1),
		'2' => __('%d Columns', 2),
		'3' => __('%d Columns', 3),
		'4' => __('%d Columns', 4),
		'5' => __('%d Columns', 5),
		'6' => __('%d Columns', 6)
	];

	$normalized_templates = $all + $none + $normalized_templates;

	$metrics_array = html_graph_order_filter_array();

	if (isrv('business_hours')) {
		$business_hours = gnrv('business_hours');
	} else {
		$business_hours = read_user_setting('show_business_hours') == 'on' ? 'true' : 'false';
	}

	if (isrv('thumbnails')) {
		$thumbnails = gnrv('thumbnails');
	} else {
		$thumbnails = read_user_setting('thumbnail_section_tree') == 'on' ? 'true' : 'false';
	}

	$filters = [
		'rows' => [
			[
				'rfilter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_VALIDATE_IS_REGEX,
					'placeholder'    => __('Enter a search term'),
					'size'           => '55',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				]
			],
			[
				'graph_template_id' => [
					'method'         => 'drop_multi',
					'friendly_name'  => __('Template'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '/^(cg_[0-9]+|dq_[0-9]+|-?[0-9]+)(,(cg_[0-9]+|dq_[0-9]+|-?[0-9]+))*$/']],
					'default'        => '-1',
					'dynamic'        => false,
					'class'          => 'graph-multiselect',
					'pageset'        => true,
					'array'          => $normalized_templates,
					'value'          => gnrv('graph_template_id')
				],
			],
			[
				'graphs' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Graphs'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => ''
				],
				'columns' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Columns'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => read_user_setting('num_columns_tree', '2'),
					'pageset'       => true,
					'array'         => $columns,
					'value'         => read_user_setting('num_columns_tree', '2')
				],
				'thumbnails' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Thumbnails'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(true|false)']],
					'default'        => read_user_setting('thumbnail_section_tree') == 'on' ? 'true' : 'false',
					'value'          => $thumbnails
				],
				'business_hours' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Business Hours'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '(true|false)']],
					'default'        => read_user_setting('show_business_hours') == 'on' ? 'true' : 'false',
					'value'          => $business_hours
				]
			],
			[
				'timespan' => [
					'method'         => 'timespan',
					'refresh'        => true,
					'clear'          => true,
					'shifter'        => true,
				],
				'node' => [
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '/([_\-a-z:0-9#]+)/']],
					'pageset'        => true,
					'default'        => ''
				],
				'site_id' => [
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1'
				],
				'host_id' => [
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1'
				],
				'host_template_id'   => [
					'method'         => 'validate',
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1'
				],
				'hgd' => [
					'method'         => 'validate',
					'filter'         => FILTER_CALLBACK,
					'filter_options' => ['options' => 'sanitize_search_string'],
					'pageset'        => true,
					'default'        => '',
				],
			]
		],
		'buttons' => [
			'go' => [
				'method'   => 'submit',
				'display'  => __('Go'),
				'title'    => __('Apply filter to table'),
				'callback' => 'applyGraphFilter()'
			],
			'clear' => [
				'method'   => 'button',
				'display'  => __('Clear'),
				'title'    => __('Reset filter to default values'),
				'callback' => 'clearGraphFilter()'
			]
		]
	];

	if (cacti_sizeof($metrics_array)) {
		$filters['rows'][1] += $metrics_array;
	}

	if (is_view_allowed('graph_settings')) {
		$filters['buttons']['save'] = [
			'method'   => 'button',
			'display'  => __('Save'),
			'title'    => __('Save filter to the database'),
			'callback' => 'saveGraphFilter("treeview")'
		];
	}

	return $filters;
}

function draw_tree_filter(bool $render = false) : void {
	$header = __('Graph Tree Filters') . (gnrv('rfilter') != '' ? ' [ ' . __('Filter') . " '" . htmlerv('rfilter') . "' " . __('Applied') . ' ]' : '');

	// create the page filter
	$filters                = create_tree_filter();
	$pageFilter             = new CactiTableFilter($header, 'graph_view.php', 'form_graph_view', 'sess_tview', '', false, false);
	$pageFilter->rows_label = __('Graphs');
	$pageFilter->set_filter_array($filters);
	$pageFilter->inject_content = inject_realtime_form();

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

/**
 * Generates the right pane tree structure for the given tree and leaf IDs.
 *
 * This function constructs the right pane tree structure for a given tree and leaf ID,
 * including various filters and options for graph templates, host templates, sites, and more.
 * It also handles the display of graph filters, timespan selectors, and other UI elements.
 *
 * @param int    $tree_id         The ID of the tree to generate the right pane for.
 * @param int    $leaf_id         The ID of the leaf to generate the right pane for.
 * @param string $host_group_data The host group data string, which can include graph template IDs, data query IDs, and data query indexes.
 *
 * @return void
 */
function grow_right_pane_tree(int $tree_id, int $leaf_id, string $host_group_data) : void {
	global $current_user, $graphs_per_page, $graph_timeshifts;

	include(CACTI_PATH_INCLUDE . '/global_arrays.php');
	include_once(CACTI_PATH_LIBRARY . '/data_query.php');
	include_once(CACTI_PATH_LIBRARY . '/html_utility.php');

	if (empty($tree_id)) {
		return;
	}

	draw_tree_filter(true);

	if (empty($leaf_id)) {
		$leaf_id = 0;
	}

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
	$leaf_names           = [];

	$leaf = db_fetch_row_prepared('SELECT
		title, host_id, site_id, host_grouping_type, parent
		FROM graph_tree_items
		WHERE id = ?',
		[$leaf_id]);

	$leaf_type = api_tree_get_item_type($leaf_id);

	// get information for the headers
	$tree_name = db_fetch_cell_prepared('SELECT name
		FROM graph_tree
		WHERE id = ?',
		[$tree_id]);

	if (isset($leaf['title']) && $leaf['title'] != '') {
		$leaf_names[] = $leaf['title'];
	}

	if (($leaf_type == 'site' || $leaf_type == 'host') && $leaf['parent'] != 0) {
		$parent     = $leaf['parent'];
		$leaf_names = [];

		while ($parent != 0) {
			$pleaf = db_fetch_row_prepared('SELECT * FROM graph_tree_items
				WHERE id = ?',
				[$parent]);

			if (cacti_sizeof($pleaf)) {
				$leaf_names[] = $pleaf['title'];
				$parent       = $pleaf['parent'];
			} else {
				break;
			}
		}
	}

	if (!empty($leaf_id)) {
		$host_name = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[$leaf['host_id']]);

		$site_name = db_fetch_cell_prepared('SELECT name
			FROM sites
			WHERE id = ?',
			[$leaf['site_id']]);
	}

	if (isrv('host_id') && gfrv('host_id') > 0 && $host_name == '') {
		$host_name = db_fetch_cell_prepared('SELECT description
			FROM host
			WHERE id = ?',
			[grv('host_id')]);
	}

	if (isrv('site_id') && gfrv('site_id') > 0 && $site_name == '') {
		$site_name = db_fetch_cell_prepared('SELECT name
			FROM sites
			WHERE id = ?',
			[grv('site_id')]);
	}

	if (isrv('host_template_id') && gfrv('host_template_id') > 0) {
		$host_template_name = db_fetch_cell_prepared('SELECT name
			FROM host_template
			WHERE id = ?',
			[grv('host_template_id')]);
	}

	$host_group_data_array = explode(':', $host_group_data);

	if ($host_group_data_array[0] == 'gt') {
		$name = db_fetch_cell_prepared('SELECT name
			FROM graph_templates
			WHERE id = ?',
			[$host_group_data_array[1]]);

		$host_group_data_name = '<i class="bold">' . __('Graph Template:') . '</i> ' . htmle($name);
		$graph_template_id    = $host_group_data_array[1];
	} elseif ($host_group_data_array[0] == 'dq') {
		$name = db_fetch_cell_prepared('SELECT name
			FROM snmp_query
			WHERE id = ?',
			[$host_group_data_array[1]]);

		$host_group_data_name = '<i class="bold">' . __('Graph Template:') . '</i> ' . (empty($host_group_data_array[1]) ? __('Non Query Based') : htmle($name));
		$data_query_id        = intval($host_group_data_array[1]);
	} elseif ($host_group_data_array[0] == 'dqi') {
		$name = db_fetch_cell_prepared('SELECT name
			FROM snmp_query
			WHERE id = ?',
			[$host_group_data_array[1]]);

		$host_group_data_name = '<i class="bold">' . __('Graph Template:') . '</i> ' . (empty($host_group_data_array[1]) ? __('Non Query Based') : htmle($name)) . '-> ' . (empty($host_group_data_array[2]) ? __('Template Based') : get_formatted_data_query_index($leaf['host_id'], intval($host_group_data_array[1]), $host_group_data_array[2]));
		$data_query_id        = intval($host_group_data_array[1]);
		$data_query_index     = $host_group_data_array[2];
	}

	if ($tree_name != '') {
		$title .= $title_delimiter . '<i class="bold">' . __('Tree:') . '</i> ' . htmle($tree_name);
		$title_delimiter = ' > ';
	}

	if ($site_name != '') {
		$title .= $title_delimiter . '<i class="bold">' . __('Site:') . '</i>&nbsp;' . htmle($site_name);
		$title_delimiter = ' > ';
	}

	if (cacti_sizeof($leaf_names)) {
		foreach ($leaf_names as $leaf_name) {
			$title .= $title_delimiter . '<i class="bold">' . __('Leaf:') . '</i> ' . htmle($leaf_name);
			$title_delimiter = ' > ';
		}
	}

	if ($host_template_name != '') {
		$title .= $title_delimiter . '<i class="bold">' . __('Device Template:') . '</i> ' . htmle($host_template_name);
		$title_delimiter = ' > ';
	}

	if ($host_name != '') {
		$title .= $title_delimiter . '<i class="bold">' . __('Device:') . '</i> ' . htmle($host_name);
		$title_delimiter = ' > ';
	}

	if ($host_group_data_name != '') {
		$title .= $title_delimiter . ' ' . $host_group_data_name;
		$title_delimiter = ' > ';
	}

	if (grv('graphs') == '-1') {
		$graph_rows = read_user_setting('treeview_graphs_per_page', read_config_option('treeview_graphs_per_page') ?? 10);
	} else {
		$graph_rows = grv('graphs');
	}

	?>
	<script type='text/javascript'>

	var graph_start = <?php print get_current_graph_start(); ?>;
	var graph_end   = <?php print get_current_graph_end(); ?>;
	var timeOffset  = <?php print date('Z'); ?>;
	var pageAction  = 'tree';
	var graphPage   = '<?php print CACTI_PATH_URL; ?>graph_view.php';
	var hgd         = '<?php print $host_group_data; ?>';
	var date1Open   = false;
	var date2Open   = false;

	function initPage() {
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

		var navBar = "<div id='navBar' class='navBar'><?php print draw_navigation_text(); ?></div>";
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
		[
			'treeid'    => $tree_id,
			'leafid'    => $leaf_id,
			'mode'      => 'tree',
			'timespan'  => $_SESSION['sess_current_timespan'],
			'starttime' => get_current_graph_start(),
			'endtime'   => get_current_graph_end()
		]
	);

	$graph_list = [];

	if (($leaf_type == 'header') || (empty($leaf_id))) {
		$sql_where = '';

		if (grv('rfilter') != '') {
			$sql_where .= ' (gtg.title_cache RLIKE ' . db_qstr(grv('rfilter')) . ' OR gtg.title RLIKE ' . db_qstr(grv('rfilter')) . ')';
		}

		if (isrv('graph_template_id') && grv('graph_template_id') != '' && grv('graph_template_id') != -1) {
			$graph_templates = html_transform_graph_template_ids(grv('graph_template_id'));

			$sql_where .= ($sql_where != '' ? ' AND ' : '') . ' ' . db_in_clause('gl.graph_template_id', $graph_templates);
		}

		$graph_list = get_allowed_tree_header_graphs($tree_id, $leaf_id, $sql_where);
	} elseif ($leaf_type == 'host') {
		if ($graph_template_id == '-1') {
			$graph_template_id = grv('graph_template_id');
		}

		$graph_list = get_host_graph_list($leaf['host_id'], $graph_template_id, $data_query_id, $leaf['host_grouping_type'], $data_query_index);
	} elseif ($leaf_type == 'site') {
		$sql_where = '';

		if (isrv('site_id') && gfrv('site_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.site_id = ' . grv('site_id');
		}

		if (isrv('host_template_id') && gfrv('host_template_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'h.host_template_id = ' . grv('host_template_id');
		}

		if (isrv('host_id') && gfrv('host_id') > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.host_id = ' . grv('host_id');
		}

		if (isrv('hgd')) {
			$parts = explode(':', grv('hgd'));

			switch($parts[0]) {
				case 'gt':
					input_validate_input_number($parts[1], 'hgd-gt');
					$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.graph_template_id = ' . $parts[1];

					break;
				case 'dq':
					input_validate_input_number($parts[1], 'hgd-dq');
					$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.snmp_query_id = ' . $parts[1];

					break;
				case 'dqi':
					input_validate_input_number($parts[1], 'hgd-dqi');
					$dqi = db_qstr($parts[2]);
					$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.snmp_query_id = ' . $parts[1] . ' AND snmp_index = ' . $dqi;

					break;
				default:
					break;
			}
		}

		if (grv('rfilter') != '') {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . '(gtg.title_cache RLIKE ' . db_qstr(grv('rfilter')) . ')';
		}

		if (isrv('graph_template_id') && grv('graph_template_id') != '') {
			$graph_templates = html_transform_graph_template_ids(grv('graph_template_id'));

			$sql_where .= ($sql_where != '' ? ' AND ' : '') . db_in_clause('gl.graph_template_id', $graph_templates);
		}

		if (read_config_option('dsstats_enable') == 'on' && grv('graph_source') != '' && grv('graph_order') != '') {
			$sql_order = [
				'data_source' => grv('graph_source'),
				'order'       => grv('graph_order'),
				'start_time'  => get_current_graph_start(),
				'end_time'    => get_current_graph_end(),
				'cf'          => grv('cf'),
				'measure'     => grv('measure')
			];
		} else {
			$sql_order = 'gtg.title_cache';
		}

		$graphs = get_allowed_graphs($sql_where, $sql_order);

		if (read_user_setting('show_aggregates', 'on') == 'on') {
			$agg = get_allowed_aggregate_graphs($sql_where);
		} else {
			$agg = [];
		}

		$graphs = array_merge($graphs, $agg);

		// let's sort the graphs naturally
		usort($graphs, 'naturally_sort_graphs');

		if (cacti_sizeof($graphs)) {
			foreach ($graphs as $graph) {
				array_push($graph_list, $graph);
			}
		}
	}

	$total_rows = cacti_sizeof($graph_list);

	// generate page list
	$nav = html_nav_bar(CACTI_PATH_URL . 'graph_view.php?action=tree_content&tree_id=' . $tree_id . '&leaf_id=' . $leaf_id . '&node=' . grv('node') . '&hgd=' . $host_group_data, MAX_DISPLAY_PAGES, grv('page'), $graph_rows, $total_rows, grv('columns'), __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	// start graph display
	print "<div class='tableHeaderGraph left'><span>$title</span></div>";

	$i = $graph_rows * (grv('page') - 1);

	$last_graph = $i + $graph_rows;

	$new_graph_list = [];

	while ($i < $total_rows && $i < $last_graph) {
		$new_graph_list[] = $graph_list[$i];
		$i++;
	}

	if (grv('thumbnails') == 'true' || grv('thumbnails') == 'on') {
		html_graph_thumbnail_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', grv('columns'), $tree_id, $leaf_id);
	} else {
		html_graph_area($new_graph_list, '', 'view_type=tree&graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', grv('columns'), $tree_id, $leaf_id);
	}

	if (!empty($leaf_id)) {
		api_plugin_hook_function('tree_after', $host_name . ',' . gnrv('leaf_id'));
	}

	api_plugin_hook_function('tree_view_page_end');

	html_end_box(false, true);

	if ($total_rows) {
		print $nav;
	}
}

/**
 * Retrieves a list of graphs for a given host, graph template, and data query.
 *
 * @param int    $host_id            The ID of the host.
 * @param int    $graph_template_id  The ID of the graph template.
 * @param int    $data_query_id      The ID of the data query.
 * @param string $host_grouping_type The type of host grouping (optional).
 * @param string $data_query_index   The index of the data query (optional).
 *
 * @return array An array of graphs for the specified host, graph template, and data query.
 */
function get_host_graph_list(int $host_id, int $graph_template_id, int $data_query_id, string $host_grouping_type = '', string $data_query_index = '') : array {
	$graph_list = [];
	$sql_where  = '';

	// graph template grouping
	if ($host_grouping_type == HOST_GROUPING_GRAPH_TEMPLATE) {
		if ($host_id > 0) {
			$sql_where = 'gl.host_id=' . $host_id;
		}

		$graph_template_id = html_transform_graph_template_ids($graph_template_id);

		if ($graph_template_id != '' && $graph_template_id != '-1' && $graph_template_id != '0') {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . ' (gl.graph_template_id IN (' . $graph_template_id . '))';
		} elseif ($graph_template_id == 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : '') . ' (gl.graph_template_id IN (' . $graph_template_id . '))';
		}

		// suppress total rows collection
		$total_rows = -1;

		$graph_templates = get_allowed_graph_templates($sql_where, 'name', '', $total_rows);

		$final_templates = [];

		if ($graph_template_id != '' && $graph_template_id != '-1' && $graph_template_id != '0') {
			$templates = explode(',', $graph_template_id);

			foreach ($templates as $id) {
				$ptemplates[$id]['id'] = $id;
			}

			foreach ($graph_templates as $template) {
				if (isset($ptemplates[$template['id']])) {
					$final_templates[$template['id']]['id']   = $template['id'];
					$final_templates[$template['id']]['name'] = $template['name'];
				}
			}
		} elseif ($graph_template_id == '0') {
			$final_templates = [];
		} else {
			$final_templates = $graph_templates;
		}

		// for graphs without a template
		array_push(
			$final_templates, [
				'id'   => '0',
				'name' => __('(Non Graph Template)')
			]
		);

		if (cacti_sizeof($final_templates)) {
			$sql_where = '';

			if (grv('rfilter') != '') {
				$sql_where .= ' (gtg.title_cache RLIKE ' . db_qstr(grv('rfilter')) . ')';
			}

			if ($host_id > 0) {
				$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.host_id = ' . $host_id;
			}

			$graph_template_ids = [];

			foreach ($final_templates as $graph_template) {
				array_push($graph_template_ids, $graph_template['id']);
			}

			$sql_where .= ($sql_where != '' ? ' AND ' : '') . 'gl.graph_template_id IN (' . implode(', ', $graph_template_ids) . ')';

			if (grv('graph_source') != '-1' && read_config_option('dsstats_enable') == 'on' && grv('graph_source') != '' && grv('graph_order') != '') {
				$sql_order = [
					'data_source' => grv('graph_source'),
					'order'       => grv('graph_order'),
					'start_time'  => get_current_graph_start(),
					'end_time'    => get_current_graph_end(),
					'cf'          => grv('cf'),
					'measure'     => grv('measure')
				];
			} else {
				$sql_order = 'gtg.title_cache';
			}

			$graphs = get_allowed_graphs($sql_where, $sql_order);

			if (read_user_setting('show_aggregates', 'on') == 'on') {
				$agg = get_allowed_aggregate_graphs($sql_where);
			} else {
				$agg = [];
			}

			$graphs = array_merge($graphs, $agg);

			// let's sort the graphs naturally
			usort($graphs, 'naturally_sort_graphs');

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $graph) {
					$graph['graph_template_name'] = $graph_template['name'];
					array_push($graph_list, $graph);
				}
			}
		}
	} elseif ($host_grouping_type == HOST_GROUPING_DATA_QUERY_INDEX) {
		// data query index grouping
		if ($host_id > 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'gl.host_id=' . $host_id;
		}

		if ($data_query_id >= 0) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'sq.id=' . $data_query_id;
		}

		$data_queries = db_fetch_assoc("SELECT sq.id, sq.name
			FROM graph_local AS gl
			INNER JOIN snmp_query AS sq
			ON gl.snmp_query_id=sq.id
			$sql_where
			GROUP BY sq.id
			ORDER BY sq.name");

		// for graphs without a data query
		if ($data_query_id <= 0) {
			array_push($data_queries,
				[
					'id'   => '0',
					'name' => __('Non Query Based')
				]
			);
		}

		if (cacti_sizeof($data_queries)) {
			foreach ($data_queries as $data_query) {
				$sql_where = '';

				// fetch a list of field names that are sorted by the preferred sort field
				$sfd = get_formatted_data_query_indexes($host_id, $data_query['id']);

				if (grv('rfilter') != '') {
					$sql_where = ' (gtg.title_cache RLIKE ' . db_qstr(grv('rfilter')) . ')';
				}

				// grab a list of all graphs for this host/data query combination
				$sql_where .= ($sql_where != '' ? ' AND ' : '') .
					'gl.snmp_query_id=' . $data_query['id'] . ($host_id > 0 ? ' AND gl.host_id=' . $host_id : '') .
					' ' . ($data_query_index != '' ? ' AND gl.snmp_index = ' . db_qstr($data_query_index) : '');

				if (read_config_option('dsstats_enable') == 'on' && grv('graph_source') != '' && grv('graph_order') != '') {
					$sql_order = [
						'data_source' => grv('graph_source'),
						'order'       => grv('graph_order'),
						'start_time'  => get_current_graph_start(),
						'end_time'    => get_current_graph_end(),
						'cf'          => grv('cf'),
						'measure'     => grv('measure')
					];
				} else {
					$sql_order = 'gtg.title_cache';
				}

				$graphs = get_allowed_graphs($sql_where, $sql_order);

				if (read_user_setting('show_aggregates', 'on') == 'on') {
					$agg = get_allowed_aggregate_graphs($sql_where);
				} else {
					$agg = [];
				}

				$graphs = array_merge($graphs, $agg);

				// re-key the results on data query index
				$snmp_index_to_graph = [];

				if (cacti_sizeof($graphs)) {
					// let's sort the graphs naturally
					usort($graphs, 'naturally_sort_graphs');

					foreach ($graphs as $graph) {
						$snmp_index_to_graph[$graph['snmp_index']][$graph['local_graph_id']] = $graph['title_cache'];

						$graphs_height[$graph['local_graph_id']] = $graph['height'];
						$graphs_width[$graph['local_graph_id']]  = $graph['width'];
					}
				}

				// using the sorted data as they key; grab each snmp index from the master list
				foreach ($sfd as $snmp_index => $sort_field_value) {
					// render each graph for the current data query index
					if (isset($snmp_index_to_graph[$snmp_index])) {
						foreach ($snmp_index_to_graph[$snmp_index] as $local_graph_id => $graph_title) {
							// reformat the array so it's compatible with the html_graph* area functions
							array_push($graph_list, [
								'data_query_name'  => $data_query['name'],
								'sort_field_value' => $sort_field_value,
								'local_graph_id'   => $local_graph_id,
								'title_cache'      => $graph_title,
								'height'           => $graphs_height[$local_graph_id] ?? intval(read_config_option('default_graph_height')),
								'width'            => $graphs_width[$local_graph_id] ?? intval(read_config_option('default_graph_width'))
							]);
						}
					}
				}
			}
		}
	}

	return $graph_list;
}

/**
 * Retrieves a list of tree branches that match a search string and returns
 * them as a JSON object to the page for filtering the tree list by matching
 * branch object.
 *
 * @return void
 */
function get_matching_nodes() : void {
	$my_matches = [];
	$match      = [];

	$filter = '%' . gnrv('str') . '%';

	if (gnrv('str') != '') {
		$matching = db_fetch_assoc_prepared('SELECT gti.parent, gti.graph_tree_id
			FROM graph_tree_items AS gti
			LEFT JOIN host AS h
			ON h.id = gti.host_id
			LEFT JOIN (
				SELECT DISTINCT site_id
				FROM host
				WHERE description LIKE ?
				OR hostname LIKE ?
			) AS h2
			ON h2.site_id = gti.site_id
			LEFT JOIN (
				SELECT local_graph_id
				FROM graph_templates_graph
				WHERE local_graph_id > 0
				AND title_cache LIKE ?
			) AS gtg
			ON gtg.local_graph_id = gti.local_graph_id
			LEFT JOIN (
				SELECT id
				FROM sites
				WHERE name LIKE ?
			) AS site
			ON site.id = gti.site_id
			WHERE (gti.title LIKE ?)
			OR (h.description LIKE ? AND (gti.host_id > 0 OR gti.site_id > 0))
			OR (h.hostname LIKE ? AND (gti.host_id > 0 OR gti.site_id > 0))
			OR (h2.site_id > 0)
			OR (gtg.local_graph_id > 0)
			OR (site.id > 0)',
			[$filter, $filter, $filter, $filter, $filter, $filter, $filter]);
	} else {
		$matching = db_fetch_assoc('SELECT parent, graph_tree_id FROM graph_tree_items');
	}

	if (cacti_sizeof($matching)) {
		foreach ($matching as $row) {
			while ($row['parent'] != '0') {
				$match[] = 'tbranch-' . $row['parent'];

				$row = db_fetch_row_prepared('SELECT parent, graph_tree_id
					FROM graph_tree_items
					WHERE id = ?',
					[$row['parent']]);

				if (!cacti_sizeof($row)) {
					break;
				}
			}

			if (cacti_sizeof($row)) {
				$match[]      = 'tree_anchor-' . $row['graph_tree_id'];
				$my_matches[] = array_reverse($match);
				$match        = [];
			}
		}

		// Now flatten the list of nodes
		$final_array = [];
		$level       = 0;

		while (true) {
			$found = 0;

			foreach ($my_matches as $match) {
				if (isset($match[$level])) {
					if ($level == 0) {
						$final_array[$match[$level]][$match[$level]] = 1;
					} else {
						$final_array[$match[0]][$match[$level]] = 1;
					}
					$found++;
				}
			}
			$level++;

			if ($found == 0) {
				break;
			}
		}

		$fa = [];

		if (cacti_sizeof($final_array)) {
			foreach ($final_array as $matches) {
				foreach ($matches as $branch => $dnc) {
					$fa[] = $branch;
				}
			}
		}

		header('Content-Type: application/json; charset=utf-8');

		print json_encode($fa);
	}
}

function html_tree_init() : void {
	draw_tree_filter();

	if (isrv('tree_id')) {
		$_SESSION['sess_tree_id'] = gfrv('tree_id');
	}

	top_graph_header();

	?>
	<script type='text/javascript'>
	minTreeWidth = <?php print read_user_setting('min_tree_width'); ?>;
	maxTreeWidth = <?php print read_user_setting('max_tree_width'); ?>;
	</script>
	<?php

	bottom_footer();
}

function html_tree_get_node() : void {
	$parent  = -1;
	$tree_id = 0;

	if (isrv('tree_id')) {
		if (gnrv('tree_id') == 0 && str_contains(gnrv('id'), 'tbranch-')) {
			$tree_id = db_fetch_cell_prepared('SELECT graph_tree_id
			FROM graph_tree_items
			WHERE id = ?',
				[str_replace('tbranch-', '', gnrv('id'))]);
		} elseif (gnrv('tree_id') == 'default' ||
			gnrv('tree_id') == 'undefined' ||
			gnrv('tree_id') == '') {
			$tree_id = read_user_setting('default_tree_id');
		} elseif (gnrv('tree_id') == 0 &&
			substr_count(gnrv('id'), 'tree_anchor') > 0) {
			$ndata   = explode('-', gnrv('id'));
			$tree_id = $ndata[1];
			input_validate_input_number($tree_id, 'tree_id');
		}
	} else {
		$tree_id = read_user_setting('default_tree_id');
	}

	if (isrv('id') && gnrv('id') != '#') {
		if (substr_count(gnrv('id'), 'tree_anchor')) {
			$parent = -1;
		} else {
			$ndata = explode('_', gnrv('id'));

			foreach ($ndata as $node) {
				$pnode = explode('-', $node);

				if ($pnode[0] == 'tbranch') {
					$parent = $pnode[1];
					input_validate_input_number($parent, 'parent');

					$tree_id = db_fetch_cell_prepared('SELECT graph_tree_id
					FROM graph_tree_items
					WHERE id = ?',
						[$parent]);

					break;
				}
			}
		}
	}

	api_tree_get_main($tree_id, $parent);
}

function html_tree_get_content() : void {
	draw_tree_filter();

	top_graph_header();

	if (!is_view_allowed('show_tree')) {
		header('Location: permission_denied.php');

		exit;
	}

	if (!ierv('node')) {
		$_SESSION['sess_graph_node'] = sanitize_search_string(gnrv('node'));

		if (!ierv('hgd')) {
			$_SESSION['sess_graph_hgd'] = sanitize_search_string(gnrv('hgd'));
		} else {
			$_SESSION['sess_graph_hgd'] = '';
		}
	} elseif (isset($_SESSION['sess_graph_node'])) {
		srv('node', $_SESSION['sess_graph_node']);
		srv('hgd', $_SESSION['sess_graph_hgd']);
	}

	?>
	<script type='text/javascript'>
	refreshIsLogout = false;
	refreshPage     = '<?php print str_replace('tree_content', 'tree', sanitize_uri($_SERVER['REQUEST_URI'])); ?>';
	refreshMSeconds = <?php print read_user_setting('page_refresh') * 1000; ?>;
	refreshFunction = 'refreshGraphs()';
	var graph_start     = <?php print get_current_graph_start(); ?>;
	var graph_end       = <?php print get_current_graph_end(); ?>;
	var timeOffset      = <?php print date('Z'); ?>

	// Adjust the height of the tree
	$(function() {
		pageAction   = 'tree';
		navHeight    = $('.cactiTreeNavigationArea').height();
		windowHeight = $(window).height();
		navOffset    = $('.cactiTreeNavigationArea').offset();

        if (navOffset !== undefined) {
            if (navOffset.top == undefined) {
                navOffset.top = 0;
            }

            if (navHeight + navOffset.top < windowHeight) {
                $('.cactiTreeNavigationArea').height(windowHeight - navOffset.top);
            }
        }
		handleUserMenu();
	});
	</script>
	<?php

	$access_denied   = false;
	$tree_parameters = [];
	$tree_id         = 0;
	$node_id         = 0;
	$hgdata          = 0;

	if (isrv('node')) {
		$parts = explode('-', sanitize_search_string(grv('node')));

		// Check for tree anchor
		if (str_contains(gnrv('node'), 'tree_anchor')) {
			$tree_id = $parts[1];
			$node_id = 0;
		} elseif (str_contains(gnrv('node'), 'tbranch')) {
			// Check for branch
			$node_id = $parts[1];
			$tree_id = db_fetch_cell_prepared('SELECT graph_tree_id
			FROM graph_tree_items
			WHERE id = ?',
				[$node_id]);
		}
	}

	if (isrv('hgd')) {
		$hgdata = gnrv('hgd');
	}

	if ($tree_id > 0) {
		if (!is_tree_allowed($tree_id)) {
			header('Location: permission_denied.php');

			exit;
		}

		grow_right_pane_tree($tree_id, $node_id, $hgdata);
	}

	bottom_footer();
}
