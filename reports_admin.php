<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

$guest_account = true;
include("./include/auth.php");
include($config["base_path"] . "/lib/reports.php");
include($config["base_path"] . "/lib/html_reports.php");
define("MAX_DISPLAY_PAGES", 21);

input_validate_input_number(get_request_var_request('id'));

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		reports_form_save();

		break;
	case 'send':
		reports_send($_REQUEST["id"]);

		header("Location: reports_admin.php?action=edit&tab=" . $_REQUEST["tab"] . "&id=" . $_REQUEST["id"]);
		break;
	case 'actions':
		reports_form_actions();

		break;
	case 'item_movedown':
		reports_item_movedown();

		header("Location: reports_admin.php?action=edit&tab=items&id=" . $_REQUEST["id"]);
		break;
	case 'item_moveup':
		reports_item_moveup();

		header("Location: reports_admin.php?action=edit&tab=items&id=" . $_REQUEST["id"]);
		break;
	case 'item_remove':
		reports_item_remove();

		header("Location: reports_admin.php?action=edit&tab=items&id=" . $_REQUEST["id"]);
		break;
	case 'item_edit':
		general_header();
		reports_item_edit();
		bottom_footer();
		break;
	case 'edit':
		general_header();
		reports_edit();
		bottom_footer();
		break;
	default:
		general_header();
		reports();
		bottom_footer();
		break;
}
