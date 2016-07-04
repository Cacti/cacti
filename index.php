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

include('./include/auth.php');
top_header();

api_plugin_hook('console_before');

?>
<table class='cactiTable'>
	<tr>
		<td class="textAreaNotes top left">
			<strong><?php print __('You are now logged into <a href="%s">Cacti</a>. You can follow these basic steps to get started.', 'about.php');?></strong>

			<ul>
				<li><?php print __('<a href="%s">Create devices</a> for network', 'host.php');?></li>
				<li><?php print __('<a href="%s">Create graphs</a> for your new devices', 'graphs_new.php');?></li>
				<li><?php print __('<a href="%s">View</a> your new graphs', 'graph_view.php');?></li>
			</ul>
		</td>
		<td class="textAreaNotes top right">
			<strong><?php print __('Version %s', $config['cacti_version']);?></strong>
		</td>
	</tr>
</table>

<?php

api_plugin_hook('console_after');

bottom_footer();

