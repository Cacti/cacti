<form method="post" action="<?print basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);?>">

<table width="100%" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td>
			<table align="center" <?if ($style == "dialog") {?>width="60%"<?}else{?>width="98%"<?}?> cellpadding=1 cellspacing=0 border=0 bgcolor="#00438C">
				<tr>
					<td width="100%">
						<table cellpadding=3 cellspacing=0 border=0 bgcolor="#E1E1E1" width="100%">
							<tr>
								<td bgcolor="#00438C" colspan="10">
									<table width="100%" cellpadding="0" cellspacing="0">
										<tr>
											<td bgcolor="#00438C" class="textHeaderDark"><?print $title_text;?></td>
											<?if (isset($add_text)) {?><td class="textHeaderDark" align="right" bgcolor="#00438C"><strong><a class="linkOverDark" href="<?print $add_text;?>">Add</a>&nbsp;</strong></td><?}?>
										</tr>
									</table>
								</td>
							</tr>