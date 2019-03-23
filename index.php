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

include('./include/auth.php');
top_header();

api_plugin_hook('console_before');

function render_external_links($style = 'FRONT') {
	global $config;

	$consoles = db_fetch_assoc_prepared('SELECT id, contentfile
		FROM external_links
		WHERE enabled = "on"
		AND style = ?', array($style));

	if (cacti_sizeof($consoles)) {
		foreach($consoles as $page) {
			if (is_realm_allowed($page['id']+10000)) {
				if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i', $page['contentfile'])) {
					print '<iframe class="content" src="' . $page['contentfile'] . '" frameborder="0"></iframe>';
				} else {
					print '<div id="content">';

					$file = $config['base_path'] . "/include/content/" . $page['contentfile'];

					if (file_exists($file)) {
						include_once($file);
					} else {
						print '<h1>The file \'' . $page['contentfile'] . '\' does not exist!!</h1>';
					}

					print '</div>';
				}
			}
		}
	}
}

render_external_links('FRONTTOP');

if (read_config_option('hide_console') != 'on') {
?>
<table class='cactiTable'>
	<tr class='tableRow'>
		<td class='textAreaNotes top left'>
			<?php print __('You are now logged into <a href="%s"><b>Cacti</b></a>. You can follow these basic steps to get started.', 'about.php');?>

			<ul>
				<li><?php print __('<a href="%s">Create devices</a> for network', 'host.php');?></li>
				<li><?php print __('<a href="%s">Create graphs</a> for your new devices', 'graphs_new.php');?></li>
				<li><?php print __('<a href="%s">View</a> your new graphs', $config['url_path'] . 'graph_view.php');?></li>
			</ul>
		</td>
		<td class='textAreaNotes top right'>
			<strong><?php print CACTI_VERSION_TEXT_FULL;?></strong>
		</td>
	</tr>
	<?php if ($config['poller_id'] > 1) {?>
	<tr class='tableRow'><td colspan='2'><hr></td></tr>
	<tr class='tableRow'><td colspan='2'><strong><?php print __('Remote Data Collector Status:');?></strong>  <?php print '<i>' . ($config['connection'] == 'online' ? __('Online'):($config['connection'] == 'recovery' ? __('Recovery'):__('Offline'))) . '</i>';?></td></tr>
	<?php if ($config['connection'] != 'online') {?>
	<tr class='tableRow'><td colspan='2'><strong><?php print __('Number of Offline Records:');?></strong>  <?php print '<i>' . number_format_i18n(db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost', '', true, $local_db_cnn_id)) . '</i>';?></td></tr>
	<?php }?>
	<tr class='tableRow'><td colspan='2'><hr></td></tr>
	<tr class='tableRow'>
		<td class='textAreaNotes top left' colspan='2'>
			<?php print __('<strong>NOTE:</strong> You are logged into a Remote Data Collector.  When <b>\'online\'</b>, you will be able to view and control much of the Main Cacti Web Site just as if you were logged into it.  Also, it\'s important to note that Remote Data Collectors are required to use the Cacti\'s Performance Boosting Services <b>\'On Demand Updating\'</b> feature, and we always recommend using Spine.  When the Remote Data Collector is <b>\'offline\'</b>, the Remote Data Collectors Web Site will contain much less information.  However, it will cache all updates until the Main Cacti Database and Web Server are reachable.  Then it will dump it\'s Boost table output back to the Main Cacti Database for updating.');?>
		</td>
	</tr>
	<tr class='tableRow'>
		<td class='textAreaNotes top left' colspan='2'>
			<?php print __('<strong>NOTE:</strong> None of the Core Cacti Plugins, to date, have been re-designed to work with Remote Data Collectors.  Therefore, Plugins such as MacTrack, and HMIB, which require direct access to devices will not work with Remote Data Collectors at this time.  However, plugins such as Thold will work so long as the Remote Data Collector is in <b>\'online\'</b> mode.');?>
		</td>
	</tr>
	<?php } ?>
</table>

<?php
}

render_external_links('FRONT');

api_plugin_hook('console_after');

?>
<script type='text/javascript'>
$(function() {
	resizeWindow();
	$(window).resize(function() {
		resizeWindow();
	});
});

function resizeWindow() {
	height = parseInt($('#navigation_right').height());
	width  = $('#main').width();
	$('.content').css({'height':height+'px', 'width':width, 'margin-top':'-5px'});
}
</script>
<?php

bottom_footer();

