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
	
<?
#print "<P>Browser = '".$browser->BROWSER."'</P>\n";

if ($browser->BROWSER == 'Netscape') {
## Netscape Support Starts Here
  
?>

  function scroll_tasklist() {
    var arg = "tasklist";
    var tl = arg;
      if (typeof arg == "string") {
<?
	
  if ($browser->MAJORVER > 4) {
    print "        tl = document.getElementById(arg).style;\n";
  } else {
    if ($browser->MINORVER > 76) {
      print "      tl = eval(\"document.\"+arg);\n";
    } else {
      print "      tl = document.getElementById(arg).style;\n";
    }
  }
print "
      }

      tl.left  = window.pageXOffset + 3;
	
  }	
		

  function popup(arg,vis) {
    if (loaded == 1 && arg) {
      var theobj = arg;
      if (typeof arg == \"string\") {
";
  if ($browser->MAJORVER > 4) {
    print "        theobj = document.getElementById(arg).style;\n";
  } else {
    if ($browser->MINORVER > 76) {
      print "      theobj = eval(\"document.\"+arg);\n";
    } else {
      print "      theobj = document.getElementById(arg).style;\n";
    }
  }
  print "      }

      theobj.top  = window.pageYOffset + 100;
      theobj.left = window.pageXOffset + 100;
      var vis = theobj.visibility;
//      alert(vis);
      if (vis == 'hidden' || vis == \"hide\") {
        theobj.visibility = 'visible';
      } else {
        theobj.visibility = 'hidden';
      }
    } else {
      if (loaded != 1) { alert(\"Page not loaded yet.\"); }
    }
  }
	
  function color_row(arg,row_state) {
    if (loaded == 1 && arg) {
      while (arg.tagName!=\"TR\") {
	{arg=arg.parentNode;}
      }
      if (row_state == \"on\") {
	arg.className = \"H\";
      } else {
	arg.className = '';
      }
    }
  }
			   
";
  
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
	
function popup(arg,vis) {
if (loaded == 1 && arg) {
  if (typeof arg == "string") {
    theobj = eval("document.all."+ arg +".style");
  } else {
     theobj = arg;
  }	
  theobj.top  = document.body.scrollTop  + 50;
  theobj.left = document.body.scrollLeft + 100;
  var vis = theobj.visibility;
  if (vis == 1 || vis == "hidden") {
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
	
  function popup(arg,vis) {
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
      if (vis == 'hidden' || vis == "hide") {
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

