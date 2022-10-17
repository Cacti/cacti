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

$guest_account = true;
include('./include/auth.php');
include_once('./lib/rrd.php');

/* set default action */
set_default_action('view');

if (!isset_request_var('view_type')) {
	set_request_var('view_type', '');
}

/* ================= input validation ================= */
get_filter_request_var('rra_id', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([0-9]+|all)$/')));
get_filter_request_var('local_graph_id');
get_filter_request_var('graph_end');
get_filter_request_var('graph_start');
get_filter_request_var('view_type', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9]+)$/')));
/* ==================================================== */

api_plugin_hook_function('graph');

include_once('./lib/html_tree.php');

top_graph_header();

if (!isset_request_var('rra_id')) {
	set_request_var('rra_id', 'all');
}

if (get_request_var('rra_id') == 'all' || isempty_request_var('rra_id')) {
	$sql_where = ' AND dspr.id IS NOT NULL';
} else {
	$sql_where = ' AND dspr.id=' . get_request_var('rra_id');
}

$exists = db_fetch_cell_prepared('SELECT local_graph_id
	FROM graph_templates_graph
	WHERE local_graph_id = ?',
	array(get_request_var('local_graph_id')));

/* make sure the graph requested exists (sanity) */
if (!$exists) {
	print '<strong><font class="txtErrorTextBox">' . __('GRAPH DOES NOT EXIST') . '</font></strong>';
	bottom_footer();
	exit;
}

/* take graph permissions into account here */
if (!is_graph_allowed(get_request_var('local_graph_id'))) {
	header('Location: permission_denied.php');
	exit;
}

$graph_title = get_graph_title(get_request_var('local_graph_id'));

if (get_request_var('action') != 'properties') {
	print "<table width='100%' class='cactiTable'>";
}

$rras = get_associated_rras(get_request_var('local_graph_id'), $sql_where);

switch (get_request_var('action')) {
case 'view':
	api_plugin_hook_function('page_buttons',
		array(
			'lgid'   => get_request_var('local_graph_id'),
			'leafid' => '',//$leaf_id,
			'mode'   => 'mrtg',
			'rraid'  => get_request_var('rra_id')
		)
	);

	?>
	<tr class='tableHeader'>
		<td colspan='3' class='textHeaderDark'>
			<strong><?php print __('Viewing Graph');?></strong> '<?php print html_escape($graph_title);?>'
		</td>
	</tr>
	<?php

	$graph = db_fetch_row_prepared('SELECT local_graph_id, width, height, graph_template_id
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array(get_request_var('local_graph_id')));

	$graph_template_id = $graph['graph_template_id'];

	$i = 0;
	if (cacti_sizeof($rras)) {
		$graph_end   = time() - 30;
		foreach ($rras as $rra) {
			if (!empty($rra['timespan'])) {
				$graph_start = $graph_end - $rra['timespan'];
			} else {
				$graph_start = $graph_end - ($rra['step'] * $rra['rows'] * $rra['steps']);
			}

			$aggregate_url = aggregate_build_children_url(get_request_var('local_graph_id'), $graph_start, $graph_end, $rra['id']);

			?>
			<tr class='tableRowGraph'>
				<td class='center'>
					<table>
						<tr>
							<td>
								<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id'] ?>' graph_id='<?php print $graph['local_graph_id'];?>' rra_id='<?php print $rra['id'];?>' graph_width='<?php print $graph['width'];?>' graph_height='<?php print $graph['height'];?>' graph_start='<?php print $graph_start;?>' graph_end='<?php print $graph_end;?>' title_font_size='<?php print ((read_user_setting('custom_fonts') == 'on') ? read_user_setting('title_size') : read_config_option('title_size'));?>'></div>
							</td>

							<?php if (is_realm_allowed(27)) { ?><td id='dd<?php print get_request_var('local_graph_id');?>' style='vertical-align:top;' class='graphDrillDown noprint'>
								<a class='iconLink utils' href='#' id='graph_<?php print get_request_var('local_graph_id');?>_util' graph_start='<?php print $graph_start;?>' graph_end='<?php print $graph_end;?>' rra_id='<?php print $rra['id'];?>'><img class='drillDown' src='<?php print $config['url_path'] . 'images/cog.png';?>' alt='' title='<?php print __esc('Graph Details, Zooming and Debugging Utilities');?>'></a><br>
								<a id='graph_<?php print $rra['id'];?>_csv' class='iconLink csv' href='<?php print html_escape($config['url_path'] . 'graph_xport.php?local_graph_id=' . get_request_var('local_graph_id') . '&rra_id=' . $rra['id'] . '&view_type=' . get_request_var('view_type') .  '&graph_start=' . $graph_start . '&graph_end=' . $graph_end);?>'><img src='<?php print $config['url_path'] . 'images/table_go.png';?>' alt='' title='<?php print __esc('CSV Export');?>'></a><br>

								<?php
								if (is_realm_allowed(10) && $graph_template_id > 0) {
									print "<a class='iconLink' role='link' title='" . __esc('Edit Graph Template') . "' href='" . html_escape($config['url_path'] . '/graph_templates.php?action=template_edit&id=' . $graph_template_id) . "'><img src='" . html_escape($config['url_path'] . 'images/template_edit.png') . "'></img></a>";
									print '<br/>';
								}

								if (read_config_option('realtime_enabled') == 'on' || is_realm_allowed(25)) {
									print "<a class='iconLink' href='#' onclick=\"window.open('".$config['url_path'] . 'graph_realtime.php?top=0&left=0&local_graph_id=' . get_request_var('local_graph_id') . "', 'popup_" . get_request_var('local_graph_id') . "', 'directories=no,toolbar=no,menubar=no,resizable=yes,location=no,scrollbars=no,status=no,titlebar=no,width=650,height=300');return false\"><img src='" . $config['url_path'] . "images/chart_curve_go.png' alt='' title='" . __esc('Click to view just this Graph in Real-time') . "'></a><br/>\n";
								}

								print ($aggregate_url != '' ? $aggregate_url:'');

								api_plugin_hook('graph_buttons', array('hook' => 'view', 'local_graph_id' => get_request_var('local_graph_id'), 'rra' => $rra['id'], 'view_type' => get_request_var('view_type')));

								?>
							</td><?php } ?>
						</tr>
						<tr>
							<td class='no-print center'>
								<span><?php print html_escape($rra['name']);?></span>
							</td>
						</tr>
					</table>
					<input type='hidden' id='thumbnails' value='<?php print html_escape(get_request_var('thumbnails'));?>'></input>
				</td>
			</tr>
			<?php
			$i++;
		}

		api_plugin_hook_function('tree_view_page_end');
	}

	?>
	<script type='text/javascript'>

	var originalWidth = null;
	var refreshTime   = <?php print read_user_setting('page_refresh')*1000;?>;
	var graphTimeout  = null;

	function initializeGraph() {
		$('a.iconLink').tooltip();

		$('.graphWrapper').each(function() {
			var itemWrapper = $(this);
			var itemGraph   = $(this).find('.graphimage');

			if (itemGraph.length != 1) {
				itemGraph = itemWrapper;
			}

			graph_id     = itemGraph.attr('graph_id');
			rra_id       = itemGraph.attr('rra_id');
			graph_height = itemGraph.attr('graph_height');
			graph_width  = itemGraph.attr('graph_width');
			graph_start  = itemGraph.attr('graph_start');
			graph_end    = itemGraph.attr('graph_end');

			$.getJSON(urlPath+'graph_json.php?'+
				'local_graph_id='+graph_id+
				'&graph_height='+graph_height+
				'&graph_start='+graph_start+
				'&graph_end='+graph_end+
				'&rra_id='+rra_id+
				'&graph_width='+graph_width+
				'&disable_cache=true'+
				($('#thumbnails').val() == 'true' ? '&graph_nolegend=true':''))
				.done(function(data) {
					wrapper=$('#wrapper_'+data.local_graph_id+'[rra_id=\''+data.rra_id+'\']');
					wrapper.html(
						"<img class='graphimage' id='graph_"+data.local_graph_id+
						"' src='data:image/"+data.type+";base64,"+data.image+
						"' rra_id='"+data.rra_id+
						"' graph_type='"+data.type+
						"' graph_id='"+data.local_graph_id+
						"' graph_start='"+data.graph_start+
						"' graph_end='"+data.graph_end+
						"' graph_left='"+data.graph_left+
						"' graph_top='"+data.graph_top+
						"' graph_width='"+data.graph_width+
						"' graph_height='"+data.graph_height+
						"' image_width='"+data.image_width+
						"' image_height='"+data.image_height+
						"' canvas_left='"+data.graph_left+
						"' canvas_top='"+data.graph_top+
						"' canvas_width='"+data.graph_width+
						"' canvas_height='"+data.graph_height+
						"' width='"+data.image_width+
						"' height='"+data.image_height+
						"' value_min='"+data.value_min+
						"' value_max='"+data.value_max+"'>"
					);

					$('#graph_start').val(data.graph_start);
					$('#graph_end').val(data.graph_end);

					var gr_location = '#graph_'+data.local_graph_id;
					if (data.rra_id > 0) {
						gr_location += '[rra_id=\'' + data.rra_id + '\']';
					}

					$(gr_location).zoom({
						inputfieldStartTime : 'date1',
						inputfieldEndTime : 'date2',
						serverTimeOffset : <?php print date('Z');?>
					});

					responsiveResizeGraphs(true);
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});

		$('a[id$="_util"]').off('click').on('click', function() {
			graph_id    = $(this).attr('id').replace('graph_','').replace('_util','');
			rra_id      = $(this).attr('rra_id');
			graph_start = $(this).attr('graph_start');
			graph_end   = $(this).attr('graph_end');

			$.get(urlPath+'graph.php?' +
				'action=zoom' +
				'&header=false' +
				'&local_graph_id='+graph_id+
				'&rra_id='+rra_id+
				'&graph_start='+graph_start+
				'&graph_end='+graph_end)
				.done(function(data) {
					$('#main').html(data);
					$('#breadcrumbs').append('<li><a id="nav_util" href="#"><?php print __('Utility View');?></a></li>');
					applySkin();
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});
		$('a[id$="_csv"]').each(function() {
			$(this).off('click').on('click', function(event) {
				event.preventDefault();
				event.stopPropagation();
				document.location = $(this).attr('href');
				Pace.stop();
			});
		});
		graphTimeout = setTimeout(initializeGraph, refreshTime);
	}

	$(function() {
		pageAction = 'graph';

		if (graphTimeout !== null) {
			clearTimeout(graphTimeout);
		}

		initializeGraph();
		$('#navigation').show();
		$('#navigation_right').show();
	});
	</script>
	<?php

	break;
case 'zoom':
	/* find the maximum time span a graph can show */
	$max_timespan=1;
	if (cacti_sizeof($rras)) {
		foreach ($rras as $rra) {
			if ($rra['steps'] * $rra['rows'] * $rra['rrd_step'] > $max_timespan) {
				$max_timespan = $rra['steps'] * $rra['rows'] * $rra['rrd_step'];
			}
		}
	}

	/* fetch information for the current RRA */
	if (isset_request_var('rra_id') && get_request_var('rra_id') > 0) {
		$rra = db_fetch_row_prepared('SELECT dspr.id, step, steps, dspr.name, `rows`
			FROM data_source_profiles_rra AS dspr
			INNER JOIN data_source_profiles AS dsp
			ON dsp.id=dspr.data_source_profile_id
			WHERE dspr.id = ?', array(get_request_var('rra_id')));

		$rra['timespan'] = $rra['steps'] * $rra['step'] * $rra['rows'];
	} else {
		$rra = db_fetch_row_prepared('SELECT dspr.id, step, steps, dspr.name, `rows`
			FROM data_source_profiles_rra AS dspr
			INNER JOIN data_source_profiles AS dsp
			ON dsp.id=dspr.data_source_profile_id
			WHERE dspr.id = ?', array($rras[0]['id']));

		$rra['timespan'] = $rra['steps'] * $rra['step'] * $rra['rows'];
	}

	/* define the time span, which decides which rra to use */
	$timespan = -($rra['timespan']);

	/* find the step and how often this graph is updated with new data */
	$ds_step = db_fetch_cell_prepared('SELECT
		data_template_data.rrd_step
		FROM (data_template_data, data_template_rrd, graph_templates_item)
		WHERE graph_templates_item.task_item_id = data_template_rrd.id
		AND data_template_rrd.local_data_id = data_template_data.local_data_id
		AND graph_templates_item.local_graph_id = ?
		LIMIT 0,1', array(get_request_var('local_graph_id')));
	$ds_step = empty($ds_step) ? 300 : $ds_step;
	$seconds_between_graph_updates = ($ds_step * $rra['steps']);

	$now = time();

	if (isset_request_var('graph_end') && (get_request_var('graph_end') <= $now - $seconds_between_graph_updates)) {
		$graph_end = get_request_var('graph_end');
	} else {
		$graph_end = $now - $seconds_between_graph_updates;
	}

	if (isset_request_var('graph_start')) {
		if (($graph_end - get_request_var('graph_start'))>$max_timespan) {
			$graph_start = $now - $max_timespan;
		}else {
			$graph_start = get_request_var('graph_start');
		}
	} else {
		$graph_start = $now + $timespan;
	}

	/* required for zoom out function */
	if ($graph_start == $graph_end) {
		$graph_start--;
	}

	$graph = db_fetch_row_prepared('SELECT width, height, title_cache, local_graph_id, graph_template_id
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array(get_request_var('local_graph_id')));

	$graph_height      = $graph['height'];
	$graph_width       = $graph['width'];
	$graph_template_id = $graph['graph_template_id'];

	if (read_user_setting('custom_fonts') == 'on' && read_user_setting('title_size') != '') {
		$title_font_size = read_user_setting('title_size');
	} elseif (read_config_option('title_size') != '') {
		$title_font_size = read_config_option('title_size');
	}else {
	 	$title_font_size = 10;
	}

	?>
	<tr class='tableHeader'>
		<td colspan='3' class='textHeaderDark'>
			<strong><?php print __('Graph Utility View');?></strong> '<?php print html_escape($graph_title);?>'
		</td>
	</tr>
	<tr class='tableRowGraph'>
		<td class='center'>
			<table>
				<tr>
					<td class='center'>
						<div class='graphWrapper' id='wrapper_<?php print $graph['local_graph_id']?>' graph_id='<?php print $graph['local_graph_id'];?>' rra_id='<?php print $rra['id'];?>' graph_width='<?php print $graph['width'];?>' graph_height='<?php print $graph['height'];?>' title_font_size='<?php print ((read_user_setting('custom_fonts') == 'on') ? read_user_setting('title_size') : read_config_option('title_size'));?>'></div>
                            <?php print (read_user_setting('show_graph_title') == 'on' ? "<span class='center'>" . html_escape($graph['title_cache']) . '</span>' : '');?>
					</td>
					<?php if (is_realm_allowed(27)) { ?><td id='dd<?php print $graph['local_graph_id'];?>' style='vertical-align:top;' class='graphDrillDown noprint'>
						<a href='#' id='graph_<?php print $graph['local_graph_id'];?>_properties' class='iconLink properties'>
							<img class='drillDown' src='<?php print $config['url_path'] . 'images/graph_properties.gif';?>' alt='' title='<?php print __esc('Graph Source/Properties');?>'>
						</a>
						<br>
						<a href='#' id='graph_<?php print $graph['local_graph_id'];?>_csv' class='iconLink properties'>
							<img class='drillDown' src='<?php print $config['url_path'] . 'images/table_go.png';?>' alt='' title='<?php print __esc('Graph Data');?>'>
						</a>
						<br>
						<?php
						if (is_realm_allowed(10) && $graph_template_id > 0) {
							print "<a class='iconLink' role='link' title='" . __esc('Edit Graph Template') . "' href='" . html_escape($config['url_path'] . '/graph_templates.php?action=template_edit&id=' . $graph_template_id) . "'><img src='" . html_escape($config['url_path'] . 'images/template_edit.png') . "'></img></a>";
							print '<br/>';
						}

						api_plugin_hook('graph_buttons', array('hook' => 'zoom', 'local_graph_id' => get_request_var('local_graph_id'), 'rra' =>  get_request_var('rra_id'), 'view_type' => get_request_var('view_type')));
						?>
					</td><?php } ?>
				</tr>
				<tr>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td style='display:none;'>
			<input type='hidden' id='date1' value=''>
			<input type='hidden' id='date2' value=''>
			<input type='hidden' id='graph_start' value='<?php print $graph_start;?>'>
			<input type='hidden' id='graph_end' value='<?php print $graph_end;?>'>
			<input type='hidden' id='thumbnails' value='<?php print html_escape(get_request_var('thumbnails'));?>'></input>
		</td>
	</tr>
	<tr class='odd'>
		<td id='data'></td>
	</tr>
	<script type='text/javascript'>
	var graph_id      = <?php print get_request_var('local_graph_id') . ";\n";?>
	var rra_id        = <?php print get_request_var('rra_id') . ";\n";?>
	var graph_start   = 0;
	var graph_end     = 0;
	var graph_height  = 0;
	var graph_width   = 0;
	var props_on      = false;
	var graph_data_on = true;

	/* turn off the page refresh */
	var refreshMSeconds=9999999;

	function graphProperties() {
		$.get(urlPath+'graph.php?action=properties&header=false&local_graph_id='+graph_id+'&rra_id=<?php print get_request_var('rra_id');?>&view_type=<?php print get_request_var('view_type');?>&graph_start='+$('#graph_start').val()+'&graph_end='+$('#graph_end').val())
			.done(function(data) {
				$('#data').html(data);
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		props_on = true;
		graph_data_on = false;
	}

	function graphXport() {
		$.get(urlPath+'graph_xport.php?local_graph_id='+graph_id+'&rra_id=0&format=table&graph_start='+$('#graph_start').val()+'&graph_end='+$('#graph_end').val())
			.done(function(data) {
				$('#data').html(data);
				resizeWrapper();

				$('.download').click(function(event) {
					event.preventDefault;
					graph_id = $(this).attr('id').replace('graph_','');
					document.location = urlPath+'graph_xport.php?local_graph_id='+graph_id+'&rra_id=0&view_type=tree&graph_start='+$('#graph_start').val()+'&graph_end='+$('#graph_end').val();
					Pace.stop();
				});
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		props_on = false;
		graph_data_on = true;
	}

	function initializeGraph() {
		$('.graphWrapper').each(function() {
			graph_id     = $(this).attr('id').replace('wrapper_','');
			rra_id       = $(this).attr('rra_id');
			graph_height = $(this).attr('graph_height');
			graph_width  = $(this).attr('graph_width');

			if (!(rra_id > 0)) {
				rra_id = 0;
			}

			$.getJSON(urlPath+'graph_json.php?rra_id='+rra_id+
				'&local_graph_id='+graph_id+
				'&graph_start='+$('#graph_start').val()+
				'&graph_end='+$('#graph_end').val()+
				'&graph_height='+graph_height+
				'&graph_width='+graph_width+
				'&disable_cache=true'+
				($('#thumbnails').val() == 'true' ? '&graph_nolegend=true':''))
				.done(function(data) {
					$('#wrapper_'+data.local_graph_id).html(
						"<img class='graphimage' id='graph_"+data.local_graph_id+
						"' src='data:image/"+data.type+";base64,"+data.image+
						"' rra_id='"+data.rra_id+
						"' graph_type='"+data.type+
						"' graph_id='"+data.local_graph_id+
						"' graph_start='"+data.graph_start+
						"' graph_end='"+data.graph_end+
						"' graph_left='"+data.graph_left+
						"' graph_top='"+data.graph_top+
						"' graph_width='"+data.graph_width+
						"' graph_height='"+data.graph_height+
						"' image_width='"+data.image_width+
						"' image_height='"+data.image_height+
						"' canvas_left='"+data.graph_left+
						"' canvas_top='"+data.graph_top+
						"' canvas_width='"+data.graph_width+
						"' canvas_height='"+data.graph_height+
						"' width='"+data.image_width+
						"' height='"+data.image_height+
						"' value_min='"+data.value_min+
						"' value_max='"+data.value_max+"'>"
					);

					$('#graph_start').val(data.graph_start);
					$('#graph_end').val(data.graph_end);

					var gr_location = '#graph_'+data.local_graph_id;
					if (data.rra_id > 0) {
						gr_location += '[rra_id=\'' + data.rra_id + '\']';
					}

					$(gr_location).zoom({
						inputfieldStartTime : 'date1',
						inputfieldEndTime : 'date2',
						serverTimeOffset : <?php print date('Z');?>
					});

					if (graph_data_on) {
						graphXport();
					} else if (props_on) {
						graphProperties();
					}

					responsiveResizeGraphs(true);
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		});

		$('a[id$="_properties"]').unbind('click').click(function() {
			graph_id = $(this).attr('id').replace('graph_', '').replace('_properties', '');
			graphProperties();
		});

		$('a[id$="_csv"]').unbind('click').click(function() {
			graph_id = $(this).attr('id').replace('graph_', '').replace('_csv', '');
			graphXport();
		});
	}

	$(function() {
		pageAction = 'graph';
		initializeGraph();
		$('#navigation').show();
		$('#navigation_right').show();
		$('a.iconLink').tooltip();
	});

	</script>
	<?php

	break;
case 'properties':
	$graph_data_array['print_source'] = true;

	/* override: graph start time (unix time) */
	if (!isempty_request_var('graph_start')) {
		$graph_data_array['graph_start'] = get_request_var('graph_start');
	}

	/* override: graph end time (unix time) */
	if (!isempty_request_var('graph_end')) {
		$graph_data_array['graph_end'] = get_request_var('graph_end');
	}

	$graph_data_array['output_flag'] = RRDTOOL_OUTPUT_STDERR;
	$graph_data_array['print_source'] = 1;

	print "<table class='center' width='100%' class='cactiTable'<tr><td>\n";
	print "<table class='cactiTable' width='100%'>\n";
	print "<tr class='tableHeader'><td colspan='3' class='linkOverDark' style='font-weight:bold;'>" . __('RRDtool Graph Syntax') . "</td></tr>\n";
	print "<tr><td><pre>\n";
	print "<span class='textInfo'>" . __('RRDtool Command:') . "</span><br>";

	$null_param = array();
	print @rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, '', $null_param, $_SESSION['sess_user_id']);
	unset($graph_data_array['print_source']);
	print "<span class='textInfo'>" . __('RRDtool Says:') . "</span><br>";
	if ($config['poller_id'] == 1) {
		print @rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array, '', $null_param, $_SESSION['sess_user_id']);
	} else {
		print __esc('Not Checked');
	}
	print "</pre></td></tr>\n";
	print "</table></td></tr></table>\n";
	exit;
	break;
}

print '</table>';

bottom_footer();

