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

/* set default action */
if (isset($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
}else{
	$action = '';
}

/* Get the username */
if (read_config_option('auth_method') == '2') {
	/* Get the Web Basic Auth username and set action so we login right away */
	$action = 'login';
	if (isset($_SERVER['PHP_AUTH_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['PHP_AUTH_USER']);
	}elseif (isset($_SERVER['REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['REMOTE_USER']);
	}elseif (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['REDIRECT_REMOTE_USER']);
	}elseif (isset($_SERVER['HTTP_PHP_AUTH_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['HTTP_PHP_AUTH_USER']);
	}elseif (isset($_SERVER['HTTP_REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['HTTP_REMOTE_USER']);
	}elseif (isset($_SERVER['HTTP_REDIRECT_REMOTE_USER'])) {
		$username = str_replace("\\", "\\\\", $_SERVER['HTTP_REDIRECT_REMOTE_USER']);

	}else{
		/* No user - Bad juju! */
		$username = '';
		cacti_log('ERROR: No username passed with Web Basic Authentication enabled.', false, 'AUTH');
		auth_display_custom_error_message('Web Basic Authentication configured, but no username was passed from the web server.  Please make sure you have authentication enabled on the web server.');
		exit;
	}
}else{
	if ($action == 'login') {
		/* LDAP and Builtin get username from Form */
		$username = get_request_var_post('login_username');
	}else{
		$username = '';
	}
}

$username = sanitize_search_string($username);
$version  = db_fetch_cell('SELECT cacti FROM version');

/* process login */
$copy_user    = false;
$user_auth    = false;
$user_enabled = 1;
$ldap_error   = false;
$ldap_error_message = '';
$realm        = 0;

if ($action == 'login') {
	switch (read_config_option('auth_method')) {
	case '0':
		/* No auth, no action, also shouldn't get here */
		exit;

		break;
	case '2':
		/* Web Basic Auth */
		$copy_user = true;
		$user_auth = true;
		$realm = 2;
		/* Locate user in database */
		$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = 2', array($username));

		break;
	case '3':
		/* LDAP Auth */
 		if ((get_request_var_post('realm') == 'ldap') && (strlen(get_request_var_post('login_password')) > 0)) {
			/* include LDAP lib */
			include_once('./lib/ldap.php');

			/* get user DN */
			$ldap_dn_search_response = cacti_ldap_search_dn($username);
			if ($ldap_dn_search_response['error_num'] == '0') {
				$ldap_dn = $ldap_dn_search_response['dn'];
			}else{
				/* Error searching */
				cacti_log('LOGIN: LDAP Error: ' . $ldap_dn_search_response['error_text'], false, 'AUTH');
				$ldap_error = true;
				$ldap_error_message = 'LDAP Search Error: ' . $ldap_dn_search_response['error_text'];
				$user_auth = false;
				$user = array();
			}

			if (!$ldap_error) {
				/* auth user with LDAP */
				$ldap_auth_response = cacti_ldap_auth($username,stripslashes(get_request_var_post('login_password')),$ldap_dn);

				if ($ldap_auth_response['error_num'] == '0') {
					/* User ok */
					$user_auth = true;
					$copy_user = true;
					$realm = 1;
					/* Locate user in database */
					cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated", false, 'AUTH');
					$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = 1', array($username));
				}else{
					/* error */
					cacti_log('LOGIN: LDAP Error: ' . $ldap_auth_response['error_text'], false, 'AUTH');
					$ldap_error = true;
					$ldap_error_message = 'LDAP Error: ' . $ldap_auth_response['error_text'];
					$user_auth = false;
					$user = array();
				}
			}

		}

		break;
	case '4':
		domains_login_process();

		break;
	default:
		if (!api_plugin_hook_function('login_process', false)) {
			/* Builtin Auth */
			if ((!$user_auth) && (!$ldap_error)) {
				/* if auth has not occured process for builtin - AKA Ldap fall through */
				$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND password = ? AND realm = 0', array($username, md5(get_request_var_post('login_password'))));
			}
		}
	}
	/* end of switch */

	/* Create user from template if requested */
	if ((!sizeof($user)) && ($copy_user) && (read_config_option('user_template') != '0') && (strlen($username) > 0)) {
		cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, 'AUTH');
		/* check that template user exists */
		if (db_fetch_row_prepared('SELECT id FROM user_auth WHERE username = ? AND realm = 0', array(read_config_option('user_template')))) {
			/* template user found */
			user_copy(read_config_option('user_template'), $username, 0, $realm);
			/* requery newly created user */
			$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = ?', array($username, $realm));
		}else{
			/* error */
			cacti_log("LOGIN: Template user '" . read_config_option('user_template') . "' does not exist.", false, 'AUTH');
			auth_display_custom_error_message("Template user '" . read_config_option('user_template') . "' does not exist.");
			exit;
		}
	}

	/* Guest account checking - Not for builtin */
	$guest_user = false;
	if ((!sizeof($user)) && ($user_auth) && (read_config_option('guest_user') != '0')) {
		/* Locate guest user record */
		$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ?', array(read_config_option('guest_user')));
		if ($user) {
			cacti_log("LOGIN: Authenicated user '" . $username . "' using guest account '" . $user['username'] . "'", false, 'AUTH');
			$guest_user = true;
		}else{
			/* error */
			auth_display_custom_error_message('Guest user "' . read_config_option('guest_user') . '" does not exist.');
			cacti_log("LOGIN: Unable to locate guest user '" . read_config_option('guest_user') . "'", false, 'AUTH');
			exit;
		}
	}

	/* Process the user  */
	if (sizeof($user)) {
		cacti_log("LOGIN: User '" . $user['username'] . "' Authenticated", false, 'AUTH');
		db_execute_prepared('INSERT INTO user_log (username, user_id, result, ip, time) VALUES (?, ?, 1, ?, NOW())', array($username, $user['id'], $_SERVER['REMOTE_ADDR']));

		/* is user enabled */
		$user_enabled = $user['enabled'];
		if ($user_enabled != 'on') {
			/* Display error */
			auth_display_custom_error_message('Access Denied, user account disabled.');
			exit;
		}

		/* remember this user */
		if (isset($_POST['remember_me']) && read_config_option('auth_cache_enabled') == 'on') {
			set_auth_cookie($user);
		}

		/* set the php session */
		$_SESSION['sess_user_id'] = $user['id'];

		/* handle 'force change password' */
		if (($user['must_change_password'] == 'on') && (read_config_option('auth_method') == 1)) {
			$_SESSION['sess_change_password'] = true;
		}

		$group_options = db_fetch_cell_prepared('SELECT MAX(login_opts)
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id=uagm.group_id
			WHERE user_id=?', array($_SESSION['sess_user_id']));

		if ($group_options > 0) {
			$user['login_opts'] = $group_options;
		}

		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */
		switch ($user['login_opts']) {
			case '1': /* referer */
				/* because we use plugins, we can't redirect back to graph_view.php if they don't
				 * have console access
				 */
				if (isset($_SERVER['HTTP_REFERER'])) {
					$referer = $_SERVER['HTTP_REFERER'];
					if (basename($referer) == 'logout.php') {
						$referer = $config['url_path'] . 'index.php';
					}
				} else if (isset($_SERVER['REQUEST_URI'])) {
					$referer = $_SERVER['REQUEST_URI'];
					if (basename($referer) == 'logout.php') {
						$referer = $config['url_path'] . 'index.php';
					}
				} else {
					$referer = $config['url_path'] . 'index.php';
				}

				if (substr_count($referer, 'plugins')) {
					header('Location: ' . $referer);
				} elseif (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm WHERE realm_id = 8 AND user_id = ?', array($_SESSION['sess_user_id']))) == 0) {
					header('Location: graph_view.php');
				} else {
					header("Location: $referer");
				}

				break;
			case '2': /* default console page */
				header('Location: ' . $config['url_path'] . 'index.php');

				break;
			case '3': /* default graph page */
				header('Location: ' . $config['url_path'] . 'graph_view.php');

				break;
			default:
				api_plugin_hook_function('login_options_navigate', $user['login_opts']);
		}
		exit;
	}else{
		if ((!$guest_user) && ($user_auth)) {
			/* No guest account defined */
			auth_display_custom_error_message('Access Denied, please contact you Cacti Administrator.');
			cacti_log('LOGIN: Access Denied, No guest enabled or template user to copy', false, 'AUTH');
			exit;
		}else{
			/* BAD username/password builtin and LDAP */
			db_execute_prepared('INSERT INTO user_log (username, user_id, result, ip, time) VALUES (?, 0, 0, ?, NOW())', array($username, $_SERVER['REMOTE_ADDR']));
		}
	}
}

/* auth_display_custom_error_message - displays a custom error message to the browser that looks like
     the pre-defined error messages
   @arg $message - the actual text of the error message to display */
function auth_display_custom_error_message($message) {
	/* kill the session */
	setcookie(session_name(),'',time() - 3600,'/');
	/* print error */
	print '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';
	print "<html>\n<head>\n";
	print '     <title>' . 'Cacti' . "</title>\n";
	print "     <meta http-equiv='Content-Type' content='text/html;charset=utf-8'>";
	print "     <link href=\"include/main.css\" type=\"text/css\" rel=\"stylesheet\">";
	print "</head>\n";
	print "<body>\n<br><br>\n";
	display_custom_error_message($message);
	print "</body>\n</html>\n";
}

function domains_login_process() {
	global $user, $realm, $username, $user_auth, $ldap_error, $ldap_error_message;
	if (is_numeric(get_request_var_post('realm')) && (strlen(get_request_var_post('login_password')) > 0)) {
		/* include LDAP lib */
		include_once('./lib/ldap.php');

		/* get user DN */
		$ldap_dn_search_response = domains_ldap_search_dn($username, get_request_var_post('realm'));
		if ($ldap_dn_search_response['error_num'] == '0') {
			$ldap_dn = $ldap_dn_search_response['dn'];
		}else{
			/* Error searching */
			cacti_log('LOGIN: LDAP Error: ' . $ldap_dn_search_response['error_text'], false, 'AUTH');
			$ldap_error = true;
			$ldap_error_message = 'LDAP Search Error: ' . $ldap_dn_search_response['error_text'];
			$user_auth = false;
			$user = array();
		}

		if (!$ldap_error) {
			/* auth user with LDAP */
			$ldap_auth_response = domains_ldap_auth($username, stripslashes(get_request_var_post('login_password')), $ldap_dn, get_request_var_post('realm'));

			if ($ldap_auth_response['error_num'] == '0') {
				/* User ok */
				$user_auth = true;
				$copy_user = true;
				$realm = get_request_var_post('realm');
				/* Locate user in database */
				cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated from Domain '" . db_fetch_cell('SELECT domain_name FROM user_domains WHERE domain_id=' . ($realm-1000)) . "'", false, 'AUTH');
				$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = ?', array($username, $realm));

				/* Create user from template if requested */
				$template_user = db_fetch_cell_prepared('SELECT user_id FROM user_domains WHERE domain_id = ?', array(get_request_var_post('realm')-1000));
				$template_username = db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($template_user));
				if ((!sizeof($user)) && ($copy_user) && ($template_user != '0') && (strlen($username) > 0)) {
					cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, 'AUTH');
					/* check that template user exists */
					if (db_fetch_row_prepared('SELECT id FROM user_auth WHERE id = ? AND realm = 0', array($template_user))) {
						/* template user found */
						user_copy($template_username, $username, 0, $realm);
						/* requery newly created user */
						$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = ?', array($username, $realm));
					}else{
						/* error */
						cacti_log("LOGIN: Template user '" . $template_username . "' does not exist.", false, 'AUTH');
						auth_display_custom_error_message("Template user '" . $template_username . "' does not exist.");
						exit;
					}
				}
			}else{
				/* error */
				cacti_log('LOGIN: LDAP Error: ' . $ldap_auth_response['error_text'], false, 'AUTH');
				$ldap_error = true;
				$ldap_error_message = 'LDAP Error: ' . $ldap_auth_response['error_text'];
				$user_auth = false;
				$user = array();
			}
		}

	}
}

function domains_ldap_auth($username, $password = '', $dn = '', $realm) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;
	if (!empty($password)) $ldap->password = $password;
	if (!empty($dn))       $ldap->dn       = $dn;

	$ld = db_fetch_row_prepared('SELECT * FROM user_domains_ldap WHERE domain_id = ?', array($realm-1000));

	if (sizeof($ld)) {
		if (!empty($ld['dn']))                $ldap->dn                = $ld['dn'];
		if (!empty($ld['server']))            $ldap->host              = $ld['server'];
		if (!empty($ld['port']))              $ldap->port              = $ld['port'];
		if (!empty($ld['port_ssl']))          $ldap->port_ssl          = $ld['port_ssl'];
		if (!empty($ld['proto_version']))     $ldap->version           = $ld['proto_version'];
		if (!empty($ld['encryption']))        $ldap->encryption        = $ld['encryption'];
		if (!empty($ld['referrals']))         $ldap->referrals         = $ld['referrals'];
		if (!empty($ld['group_require']))     $ldap->group_require     = $ld['group_require'];
		if (!empty($ld['group_dn']))          $ldap->group_dn          = $ld['group_dn'];
		if (!empty($ld['group_attrib']))      $ldap->group_attrib      = $ld['group_attrib'];
		if (!empty($ld['group_member_type'])) $ldap->group_member_type = $ld['group_member_type'];

		return $ldap->Authenticate();
	}else{
		return false;
	}
}

function domains_ldap_search_dn($username, $realm) {
	$ldap = new Ldap;

	if (!empty($username)) $ldap->username = $username;

	$ld = db_fetch_row_prepared('SELECT * FROM user_domains_ldap WHERE domain_id = ?', array($realm-1000));

	if (sizeof($ld)) {
		if (!empty($ld['dn']))                $ldap->dn                = $ld['dn'];
		if (!empty($ld['server']))            $ldap->host              = $ld['server'];
		if (!empty($ld['port']))              $ldap->port              = $ld['port'];
		if (!empty($ld['port_ssl']))          $ldap->port_ssl          = $ld['port_ssl'];
		if (!empty($ld['proto_version']))     $ldap->version           = $ld['proto_version'];
		if (!empty($ld['encryption']))        $ldap->encryption        = $ld['encryption'];
		if (!empty($ld['referrals']))         $ldap->referrals         = $ld['referrals'];
		if (!empty($ld['mode']))              $ldap->group_require     = $ld['mode'];
		if (!empty($ld['search_base']))       $ldap->group_dn          = $ld['search_base'];
		if (!empty($ld['search_filter']))     $ldap->group_attrib      = $ld['search_filter'];
		if (!empty($ld['specific_dn']))       $ldap->group_member_type = $ld['specific_dn'];
		if (!empty($ld['specific_password'])) $ldap->group_member_type = $ld['specific_password'];

		return $ldap->Search();
	}else{
		return false;
	}
}

if (api_plugin_hook_function('custom_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title><?php print api_plugin_hook_function('login_title', 'Login to Cacti');?></title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/main.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print read_config_option('selected_theme');?>/jquery-ui.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>images/favicon.ico' rel='shortcut icon'>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery-ui.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.cookie.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.hotkeys.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/layout.js'></script>
	<script type='text/javascript'>var theme='<?php print read_config_option('selected_theme');?>';</script>
	<script type='text/javascript'>
	$(function() {
			$('#login_username').focus();
	});
	</script>
</head>
<body class='loginBody'>
	<div class='loginLeft'></div>
	<div class='loginCenter'>
	<div class='loginArea'>
		<div class='cactiLoginLogo'></div>
			<legend>User Login</legend>
			<form name='login' method='post' action='<?php print basename($_SERVER['PHP_SELF']);?>'>
				<input type='hidden' name='action' value='login'>
				<?php api_plugin_hook_function('login_before', 
					array(
						'ldap_error' => $ldap_error, 
						'ldap_error_message' => $ldap_error_message, 
						'username' => $username, 
						'user_enabled' => $user_enabled, 
						'action' => $action)); 
				?>
				<div class='loginTitle'>
					<p>Enter your Username and Password below</p>
				</div>
				<div class='cactiLogin'>
					<table cellpadding='0' cellspacing='0' border='0' class='cactiLoginTable'>
						<tr>
							<td>
								<label for='login_username'>Username</label>
							</td>
							<td>
								<input type='text' id='login_username' name='login_username' size='20' value='<?php print htmlspecialchars($username); ?>' placeholder='Username'>
							</td>
						</tr>
						<tr>
							<td>
								<label for='login_password'>Password</label>
							</td>
							<td>
								<input type='password' id='login_password' name='login_password' size='20' placeholder='********'>
							</td>
						</tr>
						<?php
						if (read_config_option('auth_method') == '3' || read_config_option('auth_method') == '4') {
							if (read_config_option('auth_method') == '3') {
								$realms = api_plugin_hook_function('login_realms', 
									array(
										'local' => array(
											'name' => 'Local', 
											'selected' => false
										), 
										'ldap' => array(
											'name' => 'LDAP', 
											'selected' => true
										)
									)
								);
							}else{
								$realms = db_fetch_assoc('SELECT * FROM user_domains WHERE enabled="on" ORDER BY domain_name');
								$default_realm = db_fetch_cell('SELECT domain_id FROM user_domains WHERE defdomain=1 AND enabled="on"');
	
								if (sizeof($realms)) {
									$new_realms['local'] = array('name' => 'Local', 'selected' => false);
									foreach($realms as $realm) {
										$new_realms[1000+$realm['domain_id']] = array('name' => $realm['domain_name'], 'selected' => false);
									}
	
									if (!empty($default_realm)) {
										$new_realms[1000+$default_realm]['selected'] = true;
									}else{
										$new_realms['local']['selected'] = true;
									}
	
									return $new_realms;
								}else{
									return $auth_realms;
								}
							}
						?>
						<tr>
							<td>
								<label for='realm'>Realm</label>
							</td>
							<td>
								<select id='realm' name='realm' style='width: 295px;'><?php
									if (sizeof($realms)) {
									foreach($realms as $name => $realm) {
										print "\t\t\t\t\t<option value='" . $name . "'" . ($realm['selected'] ? ' selected':'') . '>' . htmlspecialchars($realm['name']) . "</option>\n";
									}
									}
									?>
								</select>
							</td>
						</tr>
					<?php } if (read_config_option('auth_cache_enabled') == 'on') { ?>

						<tr>
							<td colspan='2'>
								<label for='remember_me'><input style='vertical-align:-3px;' type='checkbox' id='remember_me' name='remember_me' <?php print (isset($_COOKIE['cacti_remembers']) ? 'checked':'');?>>Keep me signed in</label>
							</td>
						</tr>
					<?php } ?>
						<tr>
							<td cospan='2'>
								<input type='submit' value='Login'>
							</td>
						</tr>
					</table>
				</div>
			<?php api_plugin_hook('login_after'); ?>
			</form>
			<div class='loginErrors'>
				<?php
				if ($ldap_error) {?>
				<?php print $ldap_error_message; ?>
				<?php }else{
				if ($action == 'login') {?>
				Invalid User Name/Password Please Retype
				<?php }
				if ($user_enabled == '0') {?>
				<strong>User Account Disabled</strong>
				<?php } } ?>
			</div>
		</div>
		<div class='versionInfo'>Version <?php print $version;?> | Copyright 2014, The Cacti Group, Inc.</div>
	</div>
	<div class='loginRight'></div>
	<script type='text/javascript'>
	$(function() {
		$('body').css('height', $(window).height());
		$('.loginLeft').css('width',parseInt($(window).width()*0.33)+'px');
		$('.loginRight').css('width',parseInt($(window).width()*0.33)+'px');
	});
	</script>
</body>
</html>
