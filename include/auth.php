<?/* 
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
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
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?	/* NON-AUTH STUFF */
	header ("Cache-Control: no-cache, must-revalidate");
	header ("Pragma: no-cache");
	$form = $HTTP_POST_VARS;
	parse_str(getenv('QUERY_STRING'),$args);
	include("config.php");
	include("config_vars.php");
	include_once("database.php");
	
	include_once("browser_detect.inc");
	$browser = new BrowserDetector;
	
	/* check to see if this is a new installation */
	include_once ("include/version_functions.php");
	
	if (GetCurrentVersion() != $config[cacti_version]) {
		header ("Location: install.php");
		exit;
	}
	
	/* END OF NON-AUTH STUFF */
	include ("include/config.php");
	
	/* SESSION DATA */
	session_start();
	
        if (isset($_SESSION)) { $HTTP_SESSION_VARS = $_SESSION; }

#if (isset($_SESSION)) { print "Got _SESSION.<BR>\n";foreach (array_keys($_SESSION) as $key) { print "key of '$key', val of '$_SESSION[$key]'<BR>\n"; }  } else { print "No session.<BR>\n";}

	if ($config["global_auth"]["value"] == "on") {
		$user_id = $HTTP_SESSION_VARS['user_id'];
		$user_hash = $HTTP_SESSION_VARS['user_hash'];
		
		$host = getenv("REMOTE_ADDR");
		
		if (getenv("HTTP_REFERER") == "") {
			$referer = $HTTP_REFERER;
		}else{
			$referer = getenv("HTTP_REFERER");
		}
		
		if ($HTTP_SESSION_VARS['change_password'] == "1") {
			header ("Location: auth_changepassword.php?ref=$referer");
			exit;
		}
		
		if ($guest_account == true) {
			/* don't even bother with the guest code if we're already logged in */
			if ($user_id == "") {
				$sql_id = db_fetch_cell("select ID from auth_users where username=\"" . $config["guest_user"]["value"] . "\"");
				
				/* cannot find guest user */
				if (sizeof($sql_id) == 0) {
					print "<strong><font size=\"+1\" color=\"FF0000\">CANNOT FIND GUEST USER: " . $config["guest_user"]["value"] . "</font></strong>";
				}else{
					if ($user_id == "") {
						$user_id = $sql_id[ID];
					}
					
					$res_id = db_fetch_assoc("select a.SectionID, a.UserID, s.ID, s.Section from 
						auth_acl a left join auth_sections s on a.sectionid=s.id where s.section=\"$section\" 
						and a.userid=$user_id");
					
					if (sizeof($res_id) != 0) {
						$au = 1;
					}
				}
			}
		}
		
		if ($au != 1 && $user_id != 1) {
			$res_id = db_fetch_assoc("select a.SectionID, a.UserID, s.ID, s.Section  from
				auth_acl a left join auth_sections s on a.sectionid=s.id where s.section=\"$section\"
		 		and a.userid=\"$user_id\"");
			$rows = sizeof($res_id);
			
			/* Make sure user is logged in */
			if ($user_id == "") {
				include_once ("auth_login.php");
				exit;
			}
			
			/* Make sure they are authenticated */
			if ($rows != "") {
				$au = 1;
			}else{
				$au = 0;
				include_once ("auth_noauth.php");
				exit;
			}
		}
	}
	
	?>
