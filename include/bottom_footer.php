<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

$oper_mode = api_plugin_hook_function('bottom_footer', OPER_MODE_NATIVE);
if (($oper_mode == OPER_MODE_NATIVE) || ($oper_mode == OPER_MODE_IFRAME_NONAV)) {

?>
			</div>
		</td>
	</tr>
</table>
<?php api_plugin_hook('page_bottom');?>
</body>
</html>

<?php

}

/* we use this session var to store field values for when a save fails,
this way we can restore the field's previous values. we reset it here, because
they only need to be stored for a single page */
kill_session_var("sess_field_values");

/* make sure the debug log doesn't get too big */
debug_log_clear();
?>
