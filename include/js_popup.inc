  <SCRIPT LANGUAGE=JAVASCRIPT>
  <!--
  var loaded = 0;

  function startup() { 
   loaded = 1;
   drag_prep();
<?
    if ($PHP_SELF == '/task_tree.php' || $PHP_SELF == '/gantt.php') {
      print "   setInterval(\"scroll_tasklist();\",10);\n";
      print "js_alerts();\n";
    }	
  print "  }\n";
?>

function get_abs_x(obj) {
  var curleft = 0;
  if (document.getElementById || document.all) {
    while (obj.offsetParent) {
      curleft += obj.offsetLeft
      obj = obj.offsetParent;
    }
  }
  return curleft;			
}

function get_abs_y(obj) {
  var curtop = 0;
  if (document.getElementById || document.all) {
    while (obj.offsetParent) {
      curtop += obj.offsetTop
      obj = obj.offsetParent;
    }
  }
  return curtop;			
}

function pop_dragger(handle,target) {
//alert("Calling pop_dragger");
  popup(handle,"1");

  if (typeof handle == "string") { handle = document.all ? document.all[handle] : document.getElementById(handle); }
  if (typeof target == "string") { target = document.all ? document.all[target] : document.getElementById(target); }

  var tmp_x = get_abs_x(handle) - get_abs_x(target);
  var tmp_y = get_abs_y(handle) - get_abs_y(target);

//alert(tmp_x+" , "+tmp_y);

  target.style.left = (parseInt(target.style.left) + tmp_x) + "px";
  target.style.top  = (parseInt(target.style.top)  + tmp_y) + "px";
}

<?
#print "<P>Browser = '".$browser->BROWSER."'</P>\n";

if ($browser->BROWSER == 'Netscape') {
## Netscape Support Starts Here
  
?>
  function popup(arg,newvis) {
    if (loaded == 1 && arg) {
      var theobj = arg;
      if (typeof arg == "string") {
         theobj = document.getElementById(arg).style;
      }

      var vis = theobj.visibility;
//      alert(vis);
      if (newvis == "1") {
        theobj.visibility = 'visible';
      } else {
        theobj.visibility = 'hidden';
      }
    } else {
      if (loaded != 1) { alert("Page not loaded yet."); }
    }
  }
	
  function color_row(arg,row_state) {
    if (loaded == 1 && arg) {
      while (arg.tagName!="TR") {
	{arg=arg.parentNode;}
      }
      if (row_state == "on") {
	arg.className = "H";
      } else {
	arg.className = '';
      }
    }
  }
			   
<?
  
} elseif ($browser->BROWSER == 'MSIE' || $browser->BROWSER == 'IE') {
## MSIE Support Starts Here
#
?>  
	
function scroll_tasklist() {
  var arg = "tasklist";
  var tl = arg;
  if (typeof arg == "string") {
    tl = eval("document.all."+ arg +".style");
  }	
  tl.left = document.body.scrollLeft + 3;
}	
	
function popup(arg,newvis) {
if (loaded == 1 && arg) {
  if (typeof arg == "string") {
    theobj = eval("document.all."+ arg +".style");
  } else {
     theobj = arg;
  }	
  theobj.top  = document.body.scrollTop  + 50;
  theobj.left = document.body.scrollLeft + 100;
  var vis = theobj.visibility;
  if (newvis == 1) {
    theobj.visibility = 'visible';
  } else {
    theobj.visibility = 'hidden';
  }
} else {
  if (loaded != 1) { 
   alert("Page not loaded yet."); 
  } else {
    alert("Usage:  popup(name_of_div,mode); where 'mode' is 1 for visible of 0 for hidden.");
  }	
}
}

  function color_row(arg,row_state) {
//	alert("here is " + arg);
    if (loaded == 1 && arg) {
      while (arg.tagName!="TR") {
//	alert("here is " + arg.tagName);
	{arg=arg.parentElement;}
      }
      if (row_state == "on") {
	arg.className = "h";
      } else {
	arg.className = '';
      }
    }
  }
<?
  
} elseif ($browser->BROWSER == 'OPERA') {
##  Opera Support Starts Here.

?>

function scroll_tasklist() {
  var arg = "tasklist";
  var tl = arg;
  if (typeof arg == "string") {
    tl = eval("document.all."+ arg +".style");
  }	
  tl.left = document.body.scrollLeft + 3;
}	
	
  function popup(arg,newvis) {
    if (loaded == 1 && arg) {
      var theobj = arg;
      if (typeof arg == "string") {
<?
  if ($browser->MAJORVER > 4) {
    print "        theobj = document.getElementById(arg).style;\n";
  } else {
    if ($browser->MINORVER > 76) {
      print "      theobj = eval(\"document.\"+arg);\n";
    } else {
      print "      theobj = document.getElementById(arg).style;\n";
    }
  }
?>
      }
      theobj.top  = window.pageYOffset + 100;
      theobj.left = window.pageXOffset + 100;
      var vis = theobj.visibility;
//      alert(vis);
      if (newvis == \"1\") {
        theobj.visibility = 'visible';
      } else {
        theobj.visibility = 'hidden';
      }
    } else {
      if (loaded != 1) { alert("Page not loaded yet."); }
    }
  }

  function color_row(arg,row_state) {
    if (loaded == 1 && arg) {
      while (arg.tagName!="TR") {
	{arg=arg.parentElement;}
      }
      if (row_state == "on") {
	arg.className = "h";
      } else {
	arg.className = '';
      }
    }
  }
<?
}
?>
  //-->
  </SCRIPT>

