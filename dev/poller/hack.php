<?
//include ("include/functions.php");
include ("include/database.php");
$i=0; $j=0;
       $sql_id = mysql_query("select id,srcid,dspath from rrd_ds where active='on' and subdsid=0",$cnn_id);
       $rows = mysql_num_rows($sql_id); $i = 0;
	mysql_query("delete from targets",$cnn_id);
        while ($i < $rows) {
	       unset($sql_id_field); $j=0;
               $sql_id_field = mysql_query("select d.fieldid, d.dsid, d.value, 
                        f.srcid, f.dataname
                        from src_data d
                        left join src_fields f
                        on d.fieldid=f.id
                        where d.dsid=" . mysql_result($sql_id, $i, "id") . "
                        and f.srcid=" . mysql_result($sql_id, $i, "srcid"),$cnn_id);
		$rows_field = mysql_num_rows($sql_id_field);
		$rrd=mysql_result($sql_id, $i, "dspath");
		$rrd = ereg_replace ("<path_rra>","/var/www/cacti/rra" ,$rrd);
		while($j < $rows_field){
		if(mysql_result($sql_id_field,$j,"dataname") == "ip") $host=mysql_result($sql_id_field,$j,"value"); 
		if(mysql_result($sql_id_field,$j,"dataname") == "community") $comm=mysql_result($sql_id_field,$j,"value");
		if(mysql_result($sql_id_field,$j,"dataname") == "ifnum") $int=mysql_result($sql_id_field,$j,"value");
		if(mysql_result($sql_id_field,$j,"dataname") == "inout") {
		  if(mysql_result($sql_id_field,$j,"value") == "hcin" || mysql_result($sql_id_field,$j,"value") == "in") $oid=".1.3.6.1.2.1.31.1.1.1.6.";
		  if(mysql_result($sql_id_field,$j,"value") == "hcout" || mysql_result($sql_id_field,$j,"value") == "out") $oid=".1.3.6.1.2.1.31.1.1.1.10.";
		}


		$j++;
		}

	mysql_query("insert into targets values('','$host','$comm','$oid$int','$rrd')",$cnn_id);
$i++;
}
?>
