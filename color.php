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
$section = "Add/Edit Graphs"; 
include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
 case 'save':
    $sql_id = db_execute("replace into def_colors (id,hex) values ($id,\"$form[Hex]\")");
    
    header ("Location: color.php");
    break;
 case 'remove':
    db_execute("delete from def_colors where id=$id");
    
    header ("Location: color.php");
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$color = db_fetch_row("select * from def_colors where id=$id");
    }
    
    DrawFormHeader("rrdtool Colors Configuration","",false);
    
    DrawFormItem("Hex Value","The hex value for this color; valid range: 000000-FFFFFF.");
    DrawFormItemTextBox("Hex",$color[Hex],"","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Defined rrdtool Colors",$colors[dark_bar],"","2");
    DrawMatrixHeaderAdd($colors[dark_bar],"","color.php?action=edit");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Hex Value",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Color",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $color_list = db_fetch_assoc("select * from def_colors order by hex");
    $rows = sizeof($color_list);
    
    $i = 0;
    while ($i < $rows) { 
	$color = $color_list[$i];
	DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	DrawMatrixLoopItem("$color[Hex]",html_boolean($config["vis_main_column_bold"]["value"]),"color.php?action=edit&id=$color[ID]");
	DrawMatrixCustom("<td bgcolor=\"#$color[Hex]\" width=\"1%\">&nbsp;</td>");
	DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"color.php?action=remove&id=$color[ID]");
	DrawMatrixRowEnd();
	$i++;
    }
    
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
