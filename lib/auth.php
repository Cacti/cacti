<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

/* user_copy - copies a user account
   @arg $template_user - username of account that should be used as the template
   @arg $new_user - username of the account to be created
   @arg $new_realm - the realm the new account should be a member of */
function user_copy($template_user, $new_user, $new_realm=0) {
        $user_auth = db_fetch_row("select * from user_auth where username = '$template_user'");
        $user_auth['username'] = $new_user;
	$user_auth['realm'] = $new_realm;
        $old_id = $user_auth['id'];
        $user_auth['id'] = 0;
                                                                                                                                               
        $new_id = sql_save($user_auth, 'user_auth');
                                                                                                                                               
        $user_auth_perms = db_fetch_assoc("select * from user_auth_perms where user_id = '$old_id'");
        foreach ($user_auth_perms as $user_auth_perm) {
                $user_auth_perm['user_id'] = $new_id;
                sql_save($user_auth_perm, 'user_auth_perms', array('user_id', 'item_id', 'type'));
        }
                                                                                                                                               
        $user_auth_realm = db_fetch_assoc("select * from user_auth_realm where user_id = '$old_id'");
        foreach ($user_auth_realm as $row) {
                $row['user_id'] = $new_id;
                sql_save($row, 'user_auth_realm', array('realm_id', 'user_id'));
        }
                                                                                                                                               
        $settings_graphs = db_fetch_assoc("select * from settings_graphs where user_id = '$old_id'");
        foreach ($settings_graphs as $settings_graph) {
                $settings_graph['user_id'] = $new_id;
                sql_save($settings_graph, 'settings_graphs', array('user_id', 'name'));
        }
                                                                                                                                               
        $settings_tree = db_fetch_assoc("select * from settings_tree where user_id = '$old_id'");
        foreach ($settings_tree as $row) {
                $row['user_id'] = $new_id;
                sql_save($settings_tree, 'settings_tree', array('user_id', 'graph_tree_item_id'));
        }
}

?>
