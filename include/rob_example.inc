<?

function security_error($error) {
  print "<P ALIGN=CENTER>$error</P>\n";
}

function general_error($error) {
  print "<P ALIGN=CENTER>$error</P>\n";
}

##  This function gets a specified template file and replaces " with \" in preparation for eval'ing it.
function get_template($template_file) {
  static $templates;

  if ($templates[$template_file] == '') {
    if (file_exists($template_file)) {
      $templates[$template_file] = join("",file($template_file));
      $templates[$template_file] = preg_replace('/"/','\"',$templates[$template_file]);
    } else {
      return("<P ALIGN=CENTER>Can't find '$template_file'</P>\n");
    }
  } 
#  print "<PRE>$templates[$template_file]</PRE>\n";
  return($templates[$template_file]);
}

##  Generate the project form.  This will generate the form to add, modify, or delete a project based 
##  on what's passed as 'mode'.
function project_form($mode,$project_id = 0) {
  global $system,$colors;
  
  if ($mode == "add") {
    $form_args = "action=addconf";
    $submit_text = "Add Project";
    $project_info = array();
#    get_template("./project_form.tmpl");
    eval('$form = "'.get_template("./project_form.tmpl").'";');
    return($form);
  }
  if ($mode == "mod") {
    if ($project_id < 1) { 
      error("Missing project ID.  I can't generate the form.");
    } else {
      $sql = "SELECT * FROM projects WHERE project_id=$project_id";
      $project_query = mysql_query($sql);
      $project_info = @mysql_fetch_assoc($project_query);
      if ($project_info[project_id] < 1) {
        error("Can't find project ID in the database.  I can't generate the form.");
      } else {
        $form_args = "action=modconf&project_id=$project_id";
        $submit_text = "Modify Project Details";
        eval('$form = "'.get_template("./project_form.tmpl").'";');
        return($form);
      }
    }
  }
  if ($mode == "del") {
    if ($project_id < 1) { 
      error("Missing project ID.  I can't generate the form.");
    } else {
      $sql = "SELECT * FROM projects WHERE project_id=$project_id";
      $project_query = mysql_query($sql);
      $project_info = @mysql_fetch_assoc($project_query);
      if ($project_info[project_id] < 1) {
        error("Can't find project ID in the database.  I can't generate the form.");
      } else {
        $submit_text = "Delete Project";
        $form_args = "action=delconf&project_id=$project_id";
	$form = "<FORM ACTION='projects.php?$form_args' METHOD=POST>
	<TABLE BORDER=0 CELLPADDING=3 CELLSPACING=0 WIDTH='100%'><TR><TD><BR>
	<P ALIGN=CENTER>Are your sure you want to delete the project '$project_info[title]' and ALL of its associated data?</P>
	<BR>
	</TD>
	</TR><TR>
	<TD BGCOLOR='$colors[box_border]'COLSPAN=2 ALIGN=CENTER><INPUT TYPE=SUBMIT VALUE='$submit_text'></TD>
	</TR></TABLE>
	</FORM>	";
	return($form);
      }
    }
  }
  
}


## This function replaces tokens (!!token_name!!) in a file with their values from the hash ($arry[token_name]).
function replace_tokens($line,$arry) {
  $tokens = explode('!!',$line);
  for ($ct=1;$ct < sizeof($tokens); $ct += 2) {
    $line = ereg_replace("!!$tokens[$ct]!!",$arry[$tokens[$ct]],$line);
  }
  return $line;
}


##  This function parses the arguments out of the URL and puts them into the $args hash.
##  It's all based on a name=value scheme.
function getargs() {
  $argstmp = explode("&",urldecode(getenv('QUERY_STRING')));
  while (list($stuff,$crap) = each($argstmp)) {
    list($idx,$val) = explode("=",$crap);
    $args[$idx] = $val;
  }
  return($args);
}


##  This function opens a box with a colored border, a thick colored section at the top as a 'title bar' 
##  and, optionally, a 'Close' link for closing the box if it's a popup box.  The default width is 400 px.
##  If a div_name is passed, a 'close' link is created that will call 'popup' to close the box.  Also,
##  specifying a div_name will wrap the whole thing in a white border of 5 pixels to set the box apart 
##  from whatever is behind it.
function start_box($box_title,$box_width = '400',$div_name = '',$border_color = "default") {
  global $colors, $drag_inits;
  
  ##  I can't put the default color in the function def because it's a global variable so I've got to work around it.
  if ($border_color == "default") { $border_color = $colors[box_border]; }
  
  if ($div_name != '') { 
    $drag_inits[sizeof($drag_inits)] = array($div_name."_handle",$div_name);
    $output .= "<TABLE BORDER=0 CELLPADDING=5 CELLSPACING=0 WIDTH='$box_width'><TR><TD
     BGCOLOR='WHITE'><TABLE BORDER=0 CELLPADDING=3 CELLSPACING=0 WIDTH='$box_width'><TR><TD
     BGCOLOR='$border_color'><DIV ID=".$div_name."_handle><TABLE BORDER=0 CELLPADDING=0 CELLSPACING=2 WIDTH='100%'><TR><TD>
     <P><B><FONT COLOR='$colors[box_title]'><SPAN CLASS=big>$box_title</SPAN></FONT></B></P></TD><TD ALIGN=RIGHT><P><B><A 
     HREF='javascript:popup(\"$div_name\",\"0\");'><FONT COLOR='$colors[box_title]'>Close</FONT></A></B></P>
     </TD></TR></TABLE></DIV><TABLE BORDER=0 CELLPADDING=0 CELLSPACING=2 WIDTH='100%'><TR><TD BGCOLOR=WHITE COLSPAN=2>\n";
  } else {
    $output .= "<TABLE BORDER=0 CELLPADDING=1 CELLSPACING=0 WIDTH='$box_width'><TR><TD 
     BGCOLOR='$border_color'><TABLE BORDER=0 CELLPADDING=0 CELLSPACING=2 WIDTH='100%'><TR><TD ALIGN=CENTER>
     <P><B><FONT COLOR='$colors[box_title]'><SPAN CLASS=big>$box_title</SPAN></FONT></B></P></TD></TR><TR><TD BGCOLOR=WHITE>\n";
  }
  return($output);
}


##  Used with start_box, this function just closes the tables we open in start_box.
function end_box($shaded = 0) {
  $output .= "</TD></TR></TABLE></TD>";
  if ($shaded) { 
    $output .= "</TR></TABLE></TD>";
  }
  $output .= "</TR></TABLE>\n";
  return($output);
}


##  This function decides what 'level' a given id is on based on the characters per level.
##  For example:  Called with ('1000','1'), it would return '1'.
##                Called with ('1010','1'), it would return '3'.
##                Called with ('1010','2'), it would return '2'.
##
##  Note:  'level' is determined from left to right.
function tree_level($id,$chars_per_level) {
  $root_test = str_pad('',$chars_per_level,'0');
  if (preg_match("/^$root_test/",$id)) {
    $level = 0;
  } else {
    $level = (strlen($id)/$chars_per_level);
    for($ct = -$chars_per_level; abs($ct) < strlen($id); $ct -= $chars_per_level) {
      if(substr($id,$ct,$chars_per_level) == "00") { 
        $level = (strlen($id)/$chars_per_level)-(abs($ct)/$chars_per_level) ;
      } else {
	break;
      }
    }
  }
  return($level);
}


function increment_id($id) {
  $level = tree_level($id,'2');
  if ($level > 1) {
    $parent_root = substr($id,0,(($level - 1) * 2) );
  }
  $id_chunk = substr($id,(($level - 1) * 2),'2');
  $id_chunk += 1;
#  $id_chunk = (strrev($id_chunk) + 1);
  $id_chunk = str_pad($id_chunk,2,'0',STR_PAD_RIGHT);
#  $id_chunk = strrev($id_chunk);
  $next_id = str_pad("$parent_root$id_chunk",20,'0');
  return($next_id);
}

##  This function queries the database with the table and field specified to calculate the next 
##  available id ON THE SAME LEVEL.
function get_next_tree_id($id,$table,$field) {
  if (preg_match("/^00/",$id)) {
    $tree_level = 0;
    $parent_root = '';
  } else {
    $tree_level = tree_level($id,'2');
    $parent_root = substr($id,0,($tree_level * 2));
  }
  $sql = "SELECT $field FROM $table WHERE $field LIKE '$parent_root%' ORDER BY $field DESC LIMIT 1";
  $tmp = db_fetch_assoc($sql);
  $last_id = $tmp[0][$field];
#  if ($last_id == '') { $last_id = '00'; }
  if (substr($last_id,($tree_level * 2),'2') != '00') {
    $next_id = increment_id($last_id);  
  } else {
    $next_id = str_pad($parent_root."01",20,'0',STR_PAD_RIGHT);
  }
  
  return($next_id);
}


##  This function compares the bitmask of the given permission to the permission data supplied or, 
##  by default, the user's own permission data.
function has_permission($perm_name,$perm_data = 'userdata') {
  global $userdata,$perms;
  
  if ($perm_data == 'userdata') { $perm_data = $userdata[permissions]; }
  if ($perm_data & $perms[$perm_name][val]) { return(1); } else { return (0); }
}

?>
