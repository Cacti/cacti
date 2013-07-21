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

global $colors, $config, $menu, $refresh;

$oper_mode = api_plugin_hook_function('top_header', OPER_MODE_NATIVE);
if ($oper_mode == OPER_MODE_RESKIN) {
	return;
}

$page_title = api_plugin_hook_function('page_title', draw_navigation_text("title"));

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE7">
	<title><?php echo $page_title; ?></title>
	<link href="<?php echo $config['url_path']; ?>include/main.css" type="text/css" rel="stylesheet">
	<link href="<?php echo $config['url_path']; ?>images/favicon.ico" rel="shortcut icon">
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/layout.js"></script>
	<?php
	if (isset($refresh)) {
		if (is_array($refresh)) {
			print "<meta http-equiv=refresh content='" . htmlspecialchars($refresh["seconds"],ENT_QUOTES) . "'; url='" . htmlspecialchars($refresh["page"],ENT_QUOTES) . "'>\r\n";
		}else{
			print "<meta http-equiv=refresh content='" . htmlspecialchars($refresh,ENT_QUOTES) . "'>\r\n";
		}
	}
	api_plugin_hook('page_head'); ?>
</head>

<?php if ($oper_mode == OPER_MODE_NATIVE) {?>
<body style="background-image:url('<?php print $config['url_path'];?>images/left_border.gif');background-repeat:repeat-y;" <?php print api_plugin_hook_function("body_style", "");?>>
<?php }else{?>
<body style="background-image:url('<?php print $config['url_path'];?>images/left_border.gif');background-repeat:repeat-y;" <?php print api_plugin_hook_function("body_style", "");?>>
<?php }?>

<table width="100%" cellspacing="0" cellpadding="0">
<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<tr style="height:1px;" bgcolor="#a9a9a9">
		<td valign="bottom" colspan="3" nowrap>
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr style="background: transparent url('<?php echo $config['url_path']; ?>images/cacti_backdrop.gif') no-repeat center right;">
					<td id="tabs" valign="bottom">
						&nbsp;<a href="<?php echo $config['url_path']; ?>index.php"><img src="<?php echo $config['url_path']; ?>images/tab_console_down.gif" alt="Console" align="absmiddle" border="0"></a><a href="<?php echo $config['url_path']; ?>graph_view.php"><img src="<?php echo $config['url_path']; ?>images/tab_graphs.gif" alt="Graphs" align="absmiddle" border="0"></a><?php
						api_plugin_hook('top_header_tabs');
					?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr style="height:2px;" bgcolor="#183c8f">
		<td colspan="3">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" style="height:2px;" border="0"><br>
		</td>
	</tr>
	<tr style="height:5px;" bgcolor="#e9e9e9">
		<td colspan="3">
			<table width="100%">
				<tr>
					<td>
						<?php echo draw_navigation_text();?>
					</td>
					<td align="right">
						<?php if (read_config_option("auth_method") != 0) { api_plugin_hook('nav_login_before'); ?>
							Logged in as <strong><?php print db_fetch_cell("select username from user_auth where id=" . $_SESSION["sess_user_id"]);?></strong> (<a href="<?php echo $config['url_path']; ?>logout.php">Logout</a>)&nbsp;
							<?php api_plugin_hook('nav_login_after'); } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td bgcolor="#f5f5f5" colspan="1" style="height:8px;width:135px;background-image: url(<?php echo $config['url_path']; ?>images/shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" style="height:2px;width:135px;" border="0"><br>
		</td>
		<td colspan="2" style="height:8px;background-image: url(<?php echo $config['url_path']; ?>images/shadow.gif); background-repeat: repeat-x;" bgcolor="#ffffff">

		</td>
	</tr>
	<tr>
		<td valign="top" colspan="1" rowspan="2" style="width:135px;padding:5px;border-right:#aaaaaa 1px solid;" bgcolor='#f5f5f5'>
			<table bgcolor="#f5f5f5" width="100%" cellpadding="1" cellspacing="0" border="0" style="width:135px;">
				<?php draw_menu();?>
			</table>

			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" style="height:5px;width:135px;" border="0"><br>
			<p style="width:135px;" align="center"><a href='<?php echo $config['url_path']; ?>about.php'><img src="<?php echo $config['url_path']; ?>images/cacti_logo.gif" border="0"></a></p>
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" style="height:5px;width:135px;" border="0"><br>
		</td>
		<td width="100%" colspan="2" valign="top" style="padding: 5px; border-right: #aaaaaa 1px solid;"><?php display_output_messages();?><div style='position:relative;' id='main'>
<?php }else{ ?>
	<tr>
		<td width="100%" valign="top"><?php display_output_messages();?>
<?php } ?>
