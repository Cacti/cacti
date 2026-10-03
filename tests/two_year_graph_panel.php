<?php
function get_request_var($name) {global $requested;return $requested;}
function __n($one,$many,$count) {return $count==1?$one:$many;}
function __($text,$arg) {return sprintf($text,$arg);}
function check($test,$message) {if(!$test)throw new RuntimeException($message);}
$source=file_get_contents(__DIR__.'/../graph.php');
$begin=strpos($source,"\t\t// Add a longer view");
$end=strpos($source,"\t\tforeach (" . '$rras as $rra)',$begin);
check($begin!==false&&$end!==false,'Missing panel code');
$snippet=substr($source,$begin,$end-$begin);
foreach(array('2026-10-04','2025-10-04') as $date) {
 $graph_end=strtotime($date);$span=$graph_end-strtotime('-2 years',$graph_end);
 foreach(array('all','1') as $requested) {
  foreach(array(365,730,731) as $retention) {
   $rras=array(array('id'=>4,'name'=>'Yearly','step'=>300,'steps'=>288,'rows'=>$retention,'timespan'=>31536000));
   eval($snippet);
   $expected=$requested=='all'&&$retention*86400>=$span;
   check(count($rras)==($expected?2:1),'Retention or selected-view gate failed');
   if($expected)check($rras[1]['timespan']==$span&&$rras[1]['name']=='2 Years (1 Day Average)','Panel range or heading mismatch');
  }
 }
}
echo "PASS: two-year panel retention, leap year, heading and all-view gating\n";
