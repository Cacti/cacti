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

/* set default action */
set_default_action();

/* Get the username */
if (read_config_option('auth_method') == '2') {
	/* Get the Web Basic Auth username and set action so we login right away */
	set_request_var('action', 'login');

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
		auth_display_custom_error_message( __('Web Basic Authentication configured, but no username was passed from the web server. Please make sure you have authentication enabled on the web server.') );
		exit;
	}
}else{
	if (get_nfilter_request_var('action') == 'login') {
		/* LDAP and Builtin get username from Form */
		$username = get_nfilter_request_var('login_username');
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

if (get_nfilter_request_var('action') == 'login') {
	if (get_nfilter_request_var('realm') == 'local') {
		$auth_method = 1;
	}else{
		$auth_method = read_config_option('auth_method');
	}

	switch ($auth_method) {
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

		if (!$user && read_config_option('user_template') == '0' && read_config_option('guest_user') == '0') {
			cacti_log("ERROR: User '" . $username . "' authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.  Exiting.", false, 'AUTH');
			$username = htmlspecialchars($username);
			auth_display_custom_error_message( __('%s authenticated by Web Server, but both Template and Guest Users are not defined in Cacti.', $username) );
			exit;			
		}

		break;
	case '3':
		/* LDAP Auth */
 		if ((get_nfilter_request_var('realm') == 'ldap') && (strlen(get_nfilter_request_var('login_password')) > 0)) {
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
				$ldap_error_message =  __('LDAP Search Error: %s', $ldap_dn_search_response['error_text']);
				$user_auth = false;
				$user = array();
			}

			if (!$ldap_error) {
				/* auth user with LDAP */
				$ldap_auth_response = cacti_ldap_auth($username,stripslashes(get_nfilter_request_var('login_password')),$ldap_dn);

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
					$ldap_error_message = __('LDAP Error: %s', $ldap_auth_response['error_text']);
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
		secpass_login_process();

		if (!api_plugin_hook_function('login_process', false)) {
			/* Builtin Auth */
			$user = array();
			if ((!$user_auth) && (!$ldap_error)) {
				$stored_pass = db_fetch_cell_prepared('SELECT password FROM user_auth WHERE username = ? AND realm = 0', array($username));

				if ($stored_pass != '') {
					if (function_exists('password_verify')) {
						$p = get_nfilter_request_var('login_password');

						if (password_verify($p, $stored_pass)) {
							$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = 0', array($username));

							if (password_needs_rehash($p, PASSWORD_DEFAULT)) {
								$p = password_hash($p, PASSWORD_DEFAULT);
								db_execute_prepared('UPDATE user_auth SET password = ? WHERE username = ?', array($p, $username));
							}
						}
					}

					if (!sizeof($user)) {
						$p = md5(get_nfilter_request_var('login_password'));
						$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND password = ? AND realm = 0', array($username, $p));
					}
				}else{
					$user = array();
				}
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
			auth_display_custom_error_message( __('Template user %s does not exist.', read_config_option('user_template')) );
			exit;
		}
	}

	/* Guest account checking - Not for builtin */
	$guest_user = false;
	if ((!sizeof($user)) && ($user_auth) && (read_config_option('guest_user') != '0')) {
		/* Locate guest user record */
		$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ?', array(read_config_option('guest_user')));
		if ($user) {
			cacti_log("LOGIN: Authenticated user '" . $username . "' using guest account '" . $user['username'] . "'", false, 'AUTH');
			$guest_user = true;
		}else{
			/* error */
			auth_display_custom_error_message( __('Guest user %s does not exist.', read_config_option('guest_user')) );
			cacti_log("LOGIN: Unable to locate guest user '" . read_config_option('guest_user') . "'", false, 'AUTH');
			exit;
		}
	}

	/* Process the user  */
	if (sizeof($user)) {
		cacti_log("LOGIN: User '" . $user['username'] . "' Authenticated", false, 'AUTH');
		db_execute_prepared('INSERT IGNORE INTO user_log (username, user_id, result, ip, time) VALUES (?, ?, 1, ?, NOW())', array($username, $user['id'], $_SERVER['REMOTE_ADDR']));

		/* is user enabled */
		$user_enabled = $user['enabled'];
		if ($user_enabled != 'on') {
			/* Display error */
			auth_display_custom_error_message( __('Access Denied, user account disabled.') );
			exit;
		}

		/* remember this user */
		if (isset_request_var('remember_me') && read_config_option('auth_cache_enabled') == 'on') {
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

		$newtheme = false;
		if (user_setting_exists('selected_theme', $_SESSION['sess_user_id']) && read_config_option('selected_theme') != read_user_setting('selected_theme')) {
			unset($_SESSION['selected_theme']);
			$newtheme = true;
		}

		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */
		switch ($user['login_opts']) {
			case '1': /* referer */
				/* because we use plugins, we can't redirect back to graph_view.php if they don't
				 * have console access
				 */
				if (isset($_SERVER["REDIRECT_URL"])) {
					$referer = $_SERVER["REDIRECT_URL"];
					if (isset($_SERVER["REDIRECT_QUERY_STRING"])) {
						$referer .= '?' . $_SERVER["REDIRECT_QUERY_STRING"] . ($newtheme ? '?newtheme=1':'');
					}
				} else if (isset($_SERVER['HTTP_REFERER'])) {
					$referer = $_SERVER['HTTP_REFERER'];
					if (basename($referer) == 'logout.php') {
						$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
					}
				} else if (isset($_SERVER['REQUEST_URI'])) {
					$referer = $_SERVER['REQUEST_URI'];
					if (basename($referer) == 'logout.php') {
						$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
					}
				} else {
					$referer = $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':'');
				}

				if (substr_count($referer, 'plugins')) {
					header('Location: ' . $referer);
				} elseif (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm WHERE realm_id = 8 AND user_id = ?', array($_SESSION['sess_user_id']))) == 0) {
					header('Location: graph_view.php' . ($newtheme ? '?newtheme=1':''));
				} else {
					header("Location: $referer" . ($newtheme ? '?newtheme=1':''));
				}

				break;
			case '2': /* default console page */
				header('Location: ' . $config['url_path'] . 'index.php' . ($newtheme ? '?newtheme=1':''));

				break;
			case '3': /* default graph page */
				header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1':''));

				break;
			default:
				api_plugin_hook_function('login_options_navigate', $user['login_opts']);
		}
		exit;
	}else{
		if ((!$guest_user) && ($user_auth)) {
			/* No guest account defined */
			auth_display_custom_error_message( __('Access Denied, please contact you Cacti Administrator.') );
			cacti_log('LOGIN: Access Denied, No guest enabled or template user to copy', false, 'AUTH');
			exit;
		}else{
			/* BAD username/password builtin and LDAP */
			db_execute_prepared('INSERT IGNORE INTO user_log (username, user_id, result, ip, time) VALUES (?, 0, 0, ?, NOW())', array($username, $_SERVER['REMOTE_ADDR']));
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

	if (is_numeric(get_nfilter_request_var('realm')) && (strlen(get_nfilter_request_var('login_password')) > 0)) {
		/* include LDAP lib */
		include_once('./lib/ldap.php');

		/* get user DN */
		$ldap_dn_search_response = domains_ldap_search_dn($username, get_nfilter_request_var('realm'));
		if ($ldap_dn_search_response['error_num'] == '0') {
			$ldap_dn = $ldap_dn_search_response['dn'];
		}else{
			/* Error searching */
			cacti_log('LOGIN: LDAP Error: ' . $ldap_dn_search_response['error_text'], false, 'AUTH');
			$ldap_error = true;
			$ldap_error_message = __('LDAP Search Error: %s', $ldap_dn_search_response['error_text']);
			$user_auth = false;
			$user = array();
		}

		if (!$ldap_error) {
			/* auth user with LDAP */
			$ldap_auth_response = domains_ldap_auth($username, stripslashes(get_nfilter_request_var('login_password')), $ldap_dn, get_nfilter_request_var('realm'));

			if ($ldap_auth_response['error_num'] == '0') {
				/* User ok */
				$user_auth = true;
				$copy_user = true;
				$realm = get_nfilter_request_var('realm');
				/* Locate user in database */
				cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated from Domain '" . db_fetch_cell('SELECT domain_name FROM user_domains WHERE domain_id=' . ($realm-1000)) . "'", false, 'AUTH');
				$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE username = ? AND realm = ?', array($username, $realm));

				/* Create user from template if requested */
				$template_user = db_fetch_cell_prepared('SELECT user_id FROM user_domains WHERE domain_id = ?', array(get_nfilter_request_var('realm')-1000));
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
						auth_display_custom_error_message( __('Template user %s does not exist.', $template_username) );
						exit;
					}
				}
			}else{
				/* error */
				cacti_log('LOGIN: LDAP Error: ' . $ldap_auth_response['error_text'], false, 'AUTH');
				$ldap_error = true;
				$ldap_error_message = __('LDAP Error: %s', $ldap_auth_response['error_text']);
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
	<title><?php print api_plugin_hook_function('login_title', __('Login to Cacti'));?></title>
	<meta http-equiv='Content-Type' content='text/html;charset=utf-8'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print get_selected_theme();?>/main.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>include/themes/<?php print get_selected_theme();?>/jquery-ui.css' type='text/css' rel='stylesheet'>
	<link href='<?php echo $config['url_path']; ?>images/favicon.ico' rel='shortcut icon'>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery-ui.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.cookie.js' language='javascript'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.hotkeys.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.metadata.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/js/jquery.tablesorter.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/layout.js'></script>
	<script type='text/javascript' src='<?php echo $config['url_path']; ?>include/themes/<?php print get_selected_theme();?>/main.js'></script>
	<script type='text/javascript'>var theme='<?php print get_selected_theme();?>';</script>
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
			<legend><?php print __('User Login');?></legend>
			<form name='login' method='post' action='<?php print basename($_SERVER['PHP_SELF']);?>'>
				<input type='hidden' name='action' value='login'>
				<?php api_plugin_hook_function('login_before', 
					array(
						'ldap_error' => $ldap_error, 
						'ldap_error_message' => $ldap_error_message, 
						'username' => $username, 
						'user_enabled' => $user_enabled, 
						'action' => get_nfilter_request_var('action'))); 
				?>
				<div class='loginTitle'>
					<p><? print __('Enter your Username and Password below');?></p>
				</div>
				<div class='cactiLogin'>
					<table class='cactiLoginTable'>
						<tr>
							<td>
								<label for='login_username'><?php print __('Username');?></label>
							</td>
							<td>
								<input type='text' id='login_username' name='login_username' value='<?php print htmlspecialchars($username, ENT_QUOTES); ?>' placeholder='<?php print __('Username');?>'>
							</td>
						</tr>
						<tr>
							<td>
								<label for='login_password'><?php print __('Password');?></label>
							</td>
							<td>
								<input type='password' id='login_password' name='login_password' placeholder='********'>
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
								$realms = get_auth_realms(true);
							}
						?>
						<tr>
							<td>
								<label for='realm'><?php print __('Realm');?></label>
							</td>
							<td>
								<select id='realm' name='realm'><?php
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
								<label for='remember_me'><input style='vertical-align:-3px;' type='checkbox' id='remember_me' name='remember_me' <?php print (isset($_COOKIE['cacti_remembers']) ? 'checked':'');?>><?php print __('Keep me signed in');?></label>
							</td>
						</tr>
					<?php } ?>
						<tr>
							<td cospan='2'>
								<input type='submit' value='<?php print __('Login');?>'>
							</td>
						</tr>
					</table>
				</div>
			<?php api_plugin_hook('login_after'); ?>
			</form>
			<div class='loginErrors'>
				<?php
				if ($ldap_error) {
					print $ldap_error_message;
				}else{
					if (get_nfilter_request_var('action') == 'login') {
						print __('Invalid User Name/Password Please Retype');
					}
					if ($user_enabled == '0') {
						print __('User Account Disabled');
					} 
				}
				?>
			</div>
		</div>
		<div class='versionInfo'><?php print __('Version %1$s | %2$s', $version, COPYRIGHT_YEARS_SHORT);?></div>
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
