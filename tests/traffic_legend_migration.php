<?php
require __DIR__ . '/../lib/traffic_legend.php';
$data = json_decode(file_get_contents(__DIR__ . '/fixtures/traffic-legends.json'), true);
$original = $data;
function db_fetch_cell_prepared($sql, $args) {
 global $data;
 foreach ($data['templates'] as $r) if ($r['hash'] == $args[0]) return $r['id'];
 return false;
}
function db_fetch_assoc_prepared($sql, $args) {
 global $data;
 if (strpos($sql, 'graph_template_input_defs') !== false) return array_values(array_filter($data['inputs'], function($r) use ($args) {return $r['graph_template_item_id'] == $args[0];}));
 $rows = array_values(array_filter($data['items'], function($r) use ($args) {return $r['graph_template_id'] == $args[0];}));
 usort($rows, function($a,$b) {return array($a['local_graph_id'],$a['sequence'],$a['id']) <=> array($b['local_graph_id'],$b['sequence'],$b['id']);});
 return $rows;
}
function db_execute($sql) {return true;}
function db_execute_prepared($sql,$args) {
 global $data;
 if (strpos($sql,'REPLACE INTO')===0) {$data['inputs'][]=array('graph_template_input_id'=>$args[0],'graph_template_item_id'=>$args[1]);return true;}
 foreach ($data['items'] as &$r) if ($r['id']==$args[2]) {$r['sequence']=$args[0];$r['text_format']=$args[1];return true;}
 return false;
}
function sql_save($row,$table) {
 global $data;
 $row['id']=max(array_column($data['items'],'id'))+1;$data['items'][]=$row;return $row['id'];
}
function check($test,$message) {if(!$test) throw new RuntimeException($message);}
upgrade_interface_traffic_legends();
check(count($data['items'])==count($original['items'])+20,'Expected two peaks for each of five templates and five existing graphs');
foreach($original['items'] as $old) {
 $new=current(array_filter($data['items'],function($r) use($old) {return $r['id']==$old['id'];}));
 unset($old['sequence'],$old['text_format'],$new['sequence'],$new['text_format']);check($old==$new,'Original source/calculation changed');
}
foreach($data['templates'] as $t) {
 $rows=db_fetch_assoc_prepared('items',array($t['id']));$groups=array();foreach($rows as $r)$groups[$r['local_graph_id']][]=$r;
 foreach($groups as $gid=>$items) {
  $peaks=0;$avgs=0;$prev=null;
  foreach($items as $r) {
   if(trim($r['text_format'])=='Max peak:') {
    check($prev['graph_type_id']==4&&$prev['consolidation_function_id']==3,'Peak must follow MAX line');
    check($prev['task_item_id']==$r['task_item_id']&&$prev['cdef_id']==$r['cdef_id'],'Peak source mismatch');
    if($gid) {check($r['local_graph_template_item_id']>0,'Missing template linkage');} else {check(count(db_fetch_assoc_prepared('graph_template_input_defs',array($r['id'])))>0,'Missing input linkage');}
    check($r['hard_return']=='on'&&$r['text_format']==str_repeat(' ',53).'Max peak:   ','Alignment mismatch');$peaks++;
   }
   if($r['text_format']=='Max average:')$avgs++;$prev=$r;
  }
  check($peaks==2&&$avgs==2,'Missing legend statistics');
 }
}
$first=$data;upgrade_interface_traffic_legends();check($first==$data,'Second run changed data');
echo "PASS: five templates, five existing graphs, arbitrary IDs, preserved calculations, input links, alignment and idempotence\n";
