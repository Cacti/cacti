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
<?
$current_path = dirname(__FILE__);

/* ------------------ Header/Footer Data ---------------------- */

/* draws <BODY> tag and CSS data */
function DrawBodyHeader($page_title, $page_css_path, $page_start_table) { 
	if ($page_css_path == "") {
		$page_css_path = "css/main.css";
	} ?>
<html>
<head>
	<title><?print $page_title;?></title>
	<link href="<?print $page_css_path;?>" rel="stylesheet" TYPE="text/css">
</head>

<body>

<?if ($page_start_table == true) {?><table><?}
}

/* draws </BODY> */
function DrawBodyFooter($page_end_table) { ?>
<?if ($page_end_table == true) {?></table><?}?>
</body>
</html>

<?}

/* draws header and html form tag */
function DrawFormHeader($form_top_title, $form_encoding_upload, $form_left_column) { 
	global $current_path; include ("$current_path/config.php"); ?>
<table border="0" width="96%">
	<?if ($form_left_column == true) {?><td bgcolor="#<?print $color_light;?>" width="1%" rowspan="99999"></td><?}?>
	<tr>
		<td colspan="2" bgcolor="#<?print $color_dark_bar;?>">
			<font class="header"><?print $form_top_title;?></font>
		</td>
	</tr>
	
	<form method="post"<?if ($form_encoding_upload==true) {?> enctype="multipart/form-data"<?}?>>
<?}

/* draws a plain dark header w/o form info */
function DrawPlainFormHeader($title_text, $background_color, $column_span, $bold_text) { ?>
							<tr>
								<td colspan="<?print $column_span;?>" bgcolor="#<?print $background_color;?>" style="color: white;"><?if ($bold_text==true){?><strong><?}?><?print $title_text;?><?if ($bold_text==true){?></strong><?}?></td>
							</tr>
<?}

/* draws a vertical space and a save button */
function DrawFormSaveButton($form_action = "save", $cancel_action) { 
    print "
	<input type='hidden' name='action' value='$form_action'>
	<a href='$cancel_action'><img src='images/button_cancel2.gif' alt='Cancel' align='absmiddle' border='0'></a>\n
	<input type='image' src='images/button_save.gif' alt='Save' align='absmiddle'>\n";
}

/* draw the ending form tag */
function DrawFormFooter() {?>
<input type="hidden" name="action" value="save">
</form>
</table>
</body>
</html>
<?}

/* ------------------ Form Objects Data ---------------------- */

/* creates a new form item with a title and description */
function DrawFormItem($form_title, $form_description) { 
	global $current_path; include ("$current_path/config.php");?>
		<td width="50%">
			<?if ($form_title != "") {?><font class="textEditTitle"><?print $form_title;?></font><br><?}?><font class="textEditComment"><?print $form_description;?></font>
		
		</td>
<?}

/* creates a standard html textbox */
function DrawFormItemTextBox($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size = 30) {
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<td>
			<input type="text" name="<?print $form_name;?>" size="<?print $form_size;?>"<?if ($form_max_length!=""){?> maxlength="<?print $form_max_length;?>"<?}?><?if ($form_previous_value!=""){?> value="<?print $form_previous_value;?>"<?}?>>
		</td>
<?}

/* creates a standard html password textbox */
function DrawFormItemPasswordTextBox($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<td>
			<input type="password" name="<?print $form_name;?>" size="<?print $form_size;?>"<?if ($form_max_length!=""){?> maxlength="<?print $form_max_length;?>"<?}?><?if ($form_previous_value!=""){?> value="<?print $form_previous_value;?>"<?}?>>
		</td>
<?}

/* creates a standard hidden html textbox */
function DrawFormItemHiddenTextBox($form_name, $form_previous_value, $form_default_value) { 
    if (substr($form_name, 0, 1) == "_") {
	$form_db_name = substr($form_name, 1, strlen($form_name));
    }else{
	$form_db_name = $form_name;
    }
	
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }

	?>
		<input type="hidden" name="<?print $form_name;?>"<?if ($form_previous_value!=""){?> value="<?print $form_previous_value;?>"<?}?>>
<?}

/* creates a dropdown box from a sql string */
function DrawFormItemDropdownFromSQL($form_name, $form_data, $column_display,$column_id, $form_previous_value, $form_none_entry, $form_default_value) { 
	global $current_path; include_once ("$current_path/functions.php");
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<td>
			<select name="<?print $form_name;?>">
				<?if ($form_none_entry!="") {?><option value='0'<?if ($form_previous_value=="0"){print " selected";}?>><?print $form_none_entry;?></option><?}?>
				<?CreateList($form_data,$column_display,$column_id,$form_previous_value);?>
			</select>
		</td>
<?}

/* creates a checkbox */
function DrawFormItemCheckBox($form_name, $form_previous_value, $form_caption, $form_default_value) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<td>
			<input type="checkbox" name="<?print $form_name;?>"<?if ($form_previous_value=="on"){?> checked<?}?>> <?print $form_caption;?>
		</td>
<?}

/* creates a radio */
function DrawFormItemRadioButton($form_name, $form_previous_value, $form_current_value, $form_caption, $form_default_value) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<td>
			<input type="radio" name="<?print $form_name;?>" value="<?print $form_current_value;?>"<?if ($form_previous_value==$form_current_value){?> checked<?}?>> <?print $form_caption;?>
		</td>
<?}

/* creates a text area with a user defined rows and cols */
function DrawFormItemTextArea($form_name, $form_previous_value, $form_rows, $form_columns,
	$form_default_value) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<td>
			<textarea cols="<?print $form_columns;?>" rows="<?print $form_rows;?>" name="<?print $form_name;?>"><?print $form_previous_value;?></textarea>
		</td>
<?}


/* creates a user defined drop down box header */
function DrawFormItemDropDownCustomHeader($form_name) { ?>
		<td>
			<select name="<?print $form_name;?>">
<?}

/* creates a user defined drop down box item */
function DrawFormItemDropDownCustomItem($form_name, $form_item_value, $form_item_display, $form_previous_value) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
    ?>
			<option value="<?print $form_item_value;?>"<?if ($form_item_value==$form_previous_value) {?> selected<?}?>><?print $form_item_display;?></option>
<?}

/* creates a user defined drop down box footer */
function DrawFormItemDropDownCustomFooter() { ?>
			</select>
		</td>
<?}

/* creates a hidden text box containing the ID */
function DrawFormItemHiddenIDField($form_name, $form_id) { 
    if ($form_id=="") {
	$form_id = 0;
    }
    ?>
		<input type="hidden" name="<?print $form_name;?>" value="<?print $form_id;?>">
<?}

/* creates an HR object */
function DrawHR($width, $size) { ?>
	<tr><td><hr width="<?print $width;?>" size="<?print $size;?>" noshade></td></tr>
<?}

/* creates a file upload box */
function DrawFormItemFileBox($form_name) { ?>
		<td>
			&nbsp;<input type=file name="<?print $form_name;?>">
		</td>
<?}

/* creates a dropdown box from a sql string */
function DrawFormItemColorSelect($form_name, $form_previous_value, $form_none_entry, $form_default_value) { 
    global $current_path; 
    include_once ("$current_path/functions.php");
	
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
    
    $colors_list = db_fetch_assoc("select id,hex from def_colors order by hex desc");

    ?>
    
		<td>
			<select name="<?print $form_name;?>">
				<?if ($form_none_entry != "") {?><option value="0"><?print $form_none_entry;?></option><?}?>
				<?
				if (sizeof($colors_list) > 0) {
					foreach ($colors_list as $color) {
						print "<option style='background: #$color[hex];' value='$color[id]'";
						
						if ($form_previous_value == $color[id]) {
							print " selected";
						}
						
						print ">$color[hex]</option>\n";
					}
				}
				?>
			</select>
		</td>
<?}
    
    
/* create a multiselect listbox */
function DrawFormItemMultipleList($form_name,  $sql_string_display, $sql_display_name,
	$sql_display_value, $sql_string_previous_values, $sql_previous_value) {
	global $current_path; include_once ("$current_path/functions.php");?>
		<td>
			<select name="<?print $form_name;?>[]" multiple>
				<?CreateMultipleList($sql_string_display,$sql_display_name,$sql_display_value,$sql_string_previous_values,$sql_previous_value);?>
			</select>
		</td>
<?}

/* create a date select */
function DrawFormDateSelect($form_month_name, $form_day_name, $form_year_name, $database_conn_id,
	$sql_previous_value, $sql_month_string) { ?>
		<td bgcolor="#DEE3E7">
		&nbsp;
		<?DrawStrippedFormItemDropdownFromSQL($form_month_name,$database_conn_id,$sql_month_string,"name","id",$sql_previous_value,"",date('n'));?>
		 / 
		<?DrawStrippedFormItemDropdownFromNumberList($form_day_name,31,$sql_previous_value);?>
		 / 
		<?DrawStrippedFormItemDropdownFromYearList($form_year_name,date('Y'),10,10,$sql_previous_value);?>
		</td>
<?}

function DrawFormPreformatedText($form_background_color, $text) { ?>
<tr><td<?if ($form_background_color != "") {?> colspan="2" bgcolor="#<?print $form_background_color;?>"<?}?>>
	<pre><?print $text;?></pre>
	</tr></td>
<?}

function DrawFormArea($text) { ?>
	<tr>
		<td bgcolor="#E1E1E1" class="textArea">
			<?print $text;?>
		</td>
	</tr>
<?}

function DrawConfirmForm($title_text, $body_text, $cancel_url, $action_url) { ?>
		<br>
		<table align="center" cellpadding=1 cellspacing=0 border=0 bgcolor="#B61D22" width="60%">
			<tr>
				<td bgcolor="#B61D22" colspan="10">
					<table width="100%" cellpadding="3" cellspacing="0">
						<tr>
							<td bgcolor="#B61D22" class="textHeaderDark"><?print $title_text;?></td>
						</tr>
						<?
						DrawFormArea($body_text);
						DrawConfirmButtons($action_url, $cancel_url);
						?>
					</table>
				</td>
			</tr>
		</table>

<?}

function DrawConfirmButtons($action_url, $cancel_url) { ?>
	<tr>
		<td bgcolor="#E1E1E1">
			<a href="<?print $cancel_url;?>"><img src="images/button_cancel.gif" border="0" alt="Cancel" align="absmiddle"></a>
			<a href="<?print $action_url . "&confirm=yes";?>"><img src="images/button_delete.gif" border="0" alt="Delete" align="absmiddle"></a>
		</td>
	</tr>
<?}

function start_box($title, $style, $add_text) {
	global $colors; ?>
	<table align="center" <?if ($style == "dialog") {?>width="60%"<?}else{?>width="98%"<?}?> cellpadding=1 cellspacing=0 border=0 bgcolor="#<?print $colors["header"];?>">
		<tr>
			<td>
				<table cellpadding=3 cellspacing=0 border=0 bgcolor="#<?print $colors["form_background_dark"];?>" width="100%">
					<?if ($title != "") {?><tr>
						<td bgcolor="#<?print $colors["header"];?>" colspan="10">
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td bgcolor="#<?print $colors["header"];?>" class="textHeaderDark"><?print $title;?></td>
										<?if ($add_text != "") {?><td class="textHeaderDark" align="right" bgcolor="#<?print $colors["header"];?>"><strong><a class="linkOverDark" href="<?print $add_text;?>">Add</a>&nbsp;</strong></td><?}?>
								</tr>
							</table>
						</td>
					</tr><?}?>

<?}


function end_box() { ?>
				</table>
			</td>
		</tr>
	</table>
	<br>
<?}

/* ------------------ Stripped Form Objects Data ---------------------- */

/* creates a standard html textbox */
function DrawStrippedFormItemTextBox($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size) {
	if (substr($form_previous_value,0,13)=="Resource id #") {
		$form_previous_value = mysql_result($form_previous_value, 0, $form_name);
	}else{
		if ($form_previous_value=="") {
			$form_previous_value = $form_default_value;
		}
	}?>
			<input type="text" name="<?print $form_name;?>" size="<?print $form_size;?>"<?if ($form_max_length!=""){?> maxlength="<?print $form_max_length;?>"<?}?><?if ($form_previous_value!=""){?> value="<?print $form_previous_value;?>"<?}?>>
<?}

/* creates a standard html password textbox */
function DrawStrippedFormItemPasswordTextBox($form_name, $form_previous_value, $form_default_value, $form_max_length, $form_size) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
    ?>
			<input type="password" name="<?print $form_name;?>" size="<?print $form_size;?>"<?if ($form_max_length!=""){?> maxlength="<?print $form_max_length;?>"<?}?><?if ($form_previous_value!=""){?> value="<?print $form_previous_value;?>"<?}?>>
<?}

/* creates a dropdown box from a sql string */
function DrawStrippedFormItemDropdownFromSQL($form_name, $sql_string, $column_display,$column_id, $form_previous_value, $form_none_entry, $form_default_value) { 
    global $current_path; include_once ("$current_path/functions.php");
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
    ?>
		<select name="<?print $form_name;?>">
			<?if ($form_none_entry!="") {?><option value="0"><?print $form_none_entry;?></option><?}?>
			<?CreateList($sql_string,$column_display,$column_id,$form_previous_value);?>
		</select>
<?}

function DrawStrippedFormItemDropdownFromNumberList($form_name,$form_numbers,$form_previous_value) {
    global $current_path; include_once ("$current_path/functions.php");
    $form_default_value = date('j'); /* today */
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<select name="<?print $form_name;?>">
			<?DrawNumberList($form_numbers,$form_previous_value);?>
		</select>
<?}

function DrawStrippedFormItemDropdownFromYearList($form_name,$form_year,$form_year_before,$form_year_after, $form_previous_value) {
    global $current_path; include_once ("$current_path/functions.php");
    $form_default_value = date('Y'); /* today */
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
		<select name="<?print $form_name;?>">
			<?DrawYearList($form_year,$form_year_before,$form_year_after,$form_previous_value);?>
		</select>
<?}

/* creates a checkbox */
function DrawStrippedFormItemCheckBox($form_name, $form_previous_value, $form_caption, $form_default_value, $hard_return) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
?>
			<input type="checkbox" name="<?print $form_name;?>"<?if ($form_previous_value=="on"){?> checked<?}?>> <?print $form_caption;?><?if ($hard_return==true){?><br><?}?>
<?}

/* creates a radio */
function DrawStrippedFormItemRadioButton($form_name, $form_previous_value, $form_current_value, $form_caption, 
	$form_default_value, $hard_return) { 
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
    ?>
			<input type="radio" name="<?print $form_name;?>" value="<?print $form_current_value;?>"<?if ($form_previous_value==$form_current_value){?> checked<?}?>> <?print $form_caption;?><?if ($hard_return==true){?><br><?}?>
<?}

/* ------------------ Data Matrix ---------------------- */

function DrawMatrixRowBegin() { ?>
	<tr>
<?}

function DrawMatrixRowEnd() { ?>
	</tr>
<?}

function DrawMatrixRowAlternateColorBegin($row_color1, $row_color2, $row_value) {
	if (($row_value % 2) == 1) {
		$current_color = $row_color1;
	}else{
		$current_color = $row_color2;
	}
	?>
	<tr bgcolor="#<?print $current_color;?>">
	<?return $current_color;
}

function DrawMatrixTableBegin($table_width = false) {
	if ($table_width == true) {
		$table_width = " width=\"$table_width\"";
	}
	
	?>
<table border="0"<?print $table_width;?>>
<?
}

function DrawMatrixTableEnd() { ?>
</table>
<?}

function DrawMatrixHeaderTop($matrix_name, $matrix_background_color, $matrix_text_color, 
	$matrix_colspan) { ?>
	<td colspan="<?print $matrix_colspan;?>" bgcolor="#<?print $matrix_background_color;?>" height="1">
			<font color="#<?print $matrix_text_color;?>" class="header"><?print $matrix_name;?></font>
	</td>
<?}

function DrawMatrixHeaderAdd($matrix_background_color, $matrix_text_color, $matrix_custom_url) { 
	if ($matrix_custom_url == "") {
		global $SCRIPT_FILENAME;
		$matrix_custom_url = basename($SCRIPT_FILENAME) . "?action=edit";
	}
	 ?>
	<td bgcolor="#<?print $matrix_background_color;?>" height="1" align="center" width="1%">
		<font class="header"><a href="<?print $matrix_custom_url;?>"><?if ($matrix_text_color != ""){?><font color="#<?print $matrix_text_color;?>"><?}?>Add<?if ($matrix_text_color != ""){?></font><?}?></a></font>
	</td>
<?}

function DrawMatrixRemove($matrix_custom_url, $counter, $color1, $color2) {
	if (($counter % 2) == 1) {
		$color = $color1;
	}else{
		$color = $color2;
	}  ?>
	<td align="center" width=1% bgcolor="#<?print $color;?>"><a href="<?print $matrix_custom_url;?>"><img src="images/delete.gif" border="0" alt="Delete"></a></td>
<?}

function DrawMatrixHeaderItem($matrix_name, $matrix_text_color, $column_span = 1) { ?>
		<td height="1" colspan="<?print $column_span;?>">
			<strong><font color="#<?print $matrix_text_color;?>"><?print $matrix_name;?></font></strong>
		</td>
<?}

function DrawMatrixLoopItem($matrix_name, $matrix_format_bold, $matrix_custom_url) { 
	
	if ($matrix_custom_url == "") {
		$matrix_custom_url = $matrix_name;
	}else{
		$matrix_custom_url = "<a href=\"$matrix_custom_url\">$matrix_name</a>";
	}
	?>
		
		<td>
			<?if ($matrix_format_bold==true){?><strong><?}?><?print $matrix_custom_url;?><?if ($matrix_format_bold==true){?></strong><?}?>
		
		</td>
<?}

function DrawMatrixLoopItemAction($matrix_name, $matrix_background_color, $matrix_text_color, $matrix_format_bold, $matrix_custom_url) { ?>
		
		<td align="center" <?if ($matrix_background_color != ""){?>bgcolor="#<?print $matrix_background_color;?>"<?}?> height="1">
			<?if ($matrix_format_bold==true){?><strong><?}?><?if ($matrix_custom_url != ""){?><a href="<?print $matrix_custom_url;?>"><?}?><?if ($matrix_text_color != ""){?><font color="#<?print $matrix_text_color;?>"><?}?><?print $matrix_name;?><?if ($matrix_text_color != ""){?></font><?}?><?if ($matrix_custom_url != ""){?></a><?}?><?if ($matrix_format_bold==true){?></strong><?}?>
		
		</td>
<?}

function DrawMatrixCustom($custom_item) {
	print $custom_item;
}

/* ------------------ Useful Functions ---------------------- */

function html_boolean($html_boolean) {
	if ($html_boolean == "on") {
		return true;
	}else{
		return false;
	}
}

function html_boolean_friendly($html_boolean) {
	if ($html_boolean == "on") {
		return "Selected";
	}else{
		return "Not Selected";
	}
}


/* ------------------ Header Search ---------------------- */

function DrawFilterTextBox($form_name, $form_previous_value, $form_default_value, $form_size) {
    if ($form_previous_value=="") {
	$form_previous_value = $form_default_value;
    }
    ?>
<td>
		<input type="text" name="<?print $form_name;?>" size="<?print $form_size;?>"<?if ($form_previous_value!=""){?> value="<?print $form_previous_value;?>"<?}?>>
	</td>
<?}

function DrawFilterBlank() { ?>
<td>&nbsp;</td>
<?}

function DrawFilterSubmit() { 
	global $current_page;?>
<td align="right">
		<input type="submit" value="Filter">
		<input type="hidden" name="current_page" value="1">
	</td></form>
<?}

function DrawFilterRowBegin() { ?>
<form><tr><td colspan="2"><table width="100%"><tr>
<?}

function DrawFilterRowEnd() { ?>
</tr></table></td></tr>
<?}

function DrawFilterNavigation($total_rows, $current_page, $filter_amount, $background_color, $column_span, $script_filename, $additional_variables) { 
	global $current_path; include_once ("$current_path/config.php");
?>
<td bgcolor="#<?print $background_color;?>" colspan="<?print $column_span;?>">
	<table width="100%" cellspacing="0" cellpadding="0">
		<tr>
			<td class="textHeaderBar" align="left"><strong><< <?if ($current_page >= 2){?><a href="<?print $script_filename;?>?current_page=<?print $current_page-1;?><?print $additional_variables;?>"><?}?>Previous<?if ($current_page >= 2){?></a><?}?></strong></td>
			<td class="textHeaderBar" align="center">Showing Row<?if ($total_rows != 1){?>s<?}?> <?print (($current_page-1)*$filter_amount);?> to <?if (($current_page*$filter_amount) > $total_rows){ print $total_rows; }else{ print ($current_page*$filter_amount); }?> of <?print $total_rows;?></td>
			<td class="textHeaderBar" align="right"><strong><?if (($current_page*$filter_amount) <= $total_rows){?><a href="<?print $script_filename;?>?current_page=<?print $current_page+1;?><?print $additional_variables;?>"><?}?>Next<?if (($current_page*$filter_amount) <= $total_rows){?></a><?}?> >></strong></td>
		</tr>
	</table>
</td>
<?}

function GetFilterSearchSQL($search_fields) {
	$first = " WHERE"; $i = 0;
	
	while ($i < count($search_fields)) {
		if ($search_fields[$i]["db_value"] != "") {
			$sql_where = $sql_where . "$first " . $search_fields[$i]["db_name"] . " like \"%%" . $search_fields[$i]["db_value"] . "%%\"";
			$first = " AND";
		}
		
		$i++;
	}
	
	return $sql_where;
}

?>
