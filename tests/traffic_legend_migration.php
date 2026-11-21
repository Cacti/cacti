<?php
namespace CactiTrafficMigrationFixture;
use RuntimeException;
use Throwable;
require __DIR__ . '/Helpers/RequestCoverage.php';
require __DIR__ . '/Helpers/SourceSlice.php';
cacti_fixture_source_function(__DIR__ . '/../lib/traffic_legend.php', 'traffic_legend_plan', __NAMESPACE__);
cacti_fixture_source_function(__DIR__ . '/../lib/traffic_legend.php', 'traffic_legend_peak_hash', __NAMESPACE__);
cacti_fixture_source_function(__DIR__ . '/../lib/traffic_legend.php', 'traffic_legend_update_graph', __NAMESPACE__);
cacti_fixture_source_function(__DIR__ . '/../lib/traffic_legend.php', 'update_interface_traffic_legends', __NAMESPACE__);
$data = json_decode(file_get_contents(__DIR__ . '/fixtures/traffic-legends.json'), true);
$original = $data;
function db_fetch_cell_prepared($sql, $args) {
 global $data;
 foreach ($data['templates'] as $r) if ($r['hash'] == $args[0]) return $r['id'];
 return false;
}
function db_fetch_assoc_prepared($sql, $args) {
 global $data;
 if (strpos($sql, 'FROM graph_local') !== false) {
  $ids = array_unique(array_column(array_filter($data['items'], fn($r) => $r['graph_template_id']==$args[0] && $r['local_graph_id']>$args[1]), 'local_graph_id'));
  sort($ids); preg_match('/LIMIT ([0-9]+)/', $sql, $m);
  return array_map(fn($id) => ['id'=>$id], array_slice($ids,0,(int)$m[1]));
 }
 if (strpos($sql, 'graph_template_input_defs') !== false) return array_values(array_filter($data['inputs'], function($r) use ($args) {return $r['graph_template_item_id'] == $args[0];}));
 $rows = array_values(array_filter($data['items'], function($r) use ($args) {return $r['graph_template_id'] == $args[0] && (!isset($args[1]) || $r['local_graph_id']==$args[1]);}));
 usort($rows, function($a,$b) {return array($a['local_graph_id'],$a['sequence'],$a['id']) <=> array($b['local_graph_id'],$b['sequence'],$b['id']);});
 return $rows;
}
function db_execute($sql) {
 global $data, $transaction, $transactions;
 $transactions[] = $sql;
 if ($sql === 'START TRANSACTION') $transaction = $data;
 if ($sql === 'ROLLBACK') $data = $transaction;
 return true;
}
function db_execute_prepared($sql,$args) {
 global $data, $failure, $failure_graph;
 if ($failure_graph !== null && strpos($sql, 'UPDATE') === 0) {
  foreach ($data['items'] as $item) if ($item['id'] == $args[2] && $item['local_graph_id'] == $failure_graph) return false;
 }
 if ($failure === 'update' && strpos($sql, 'UPDATE') === 0) return false;
 if ($failure === 'link' && strpos($sql, 'REPLACE INTO') === 0) return false;
 if (strpos($sql,'REPLACE INTO')===0) {
  $input=array('graph_template_input_id'=>$args[0],'graph_template_item_id'=>$args[1]);
  if (!in_array($input,$data['inputs'])) $data['inputs'][]=$input;
  return true;
 }
 foreach ($data['items'] as &$r) if ($r['id']==$args[2]) {$r['sequence']=$args[0];$r['text_format']=$args[1];return true;}
 return false;
}
function sql_save($row,$table) {
 global $data, $failure;
 if ($failure === 'save') return false;
 if (isset($row['id'])) {
  foreach ($data['items'] as &$item) if ($item['id']==$row['id']) {$item=$row;return $row['id'];}
  return false;
 }
 $row['id']=max(array_column($data['items'],'id'))+1;$data['items'][]=$row;return $row['id'];
}
function check($test,$message) {if(!$test) throw new RuntimeException($message);}
$failure = null;
$failure_graph = null;
$transactions = [];
update_interface_traffic_legends(false, 1);
check($data === $original, 'Dry run wrote data');
check($transactions === [], 'Dry run started a write transaction');
update_interface_traffic_legends(true, 1);
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
$first=$data;update_interface_traffic_legends(true, 1);check($first==$data,'Second run changed data');

// Signed package imports restore stock template items without removing peaks.
foreach (array(false,true) as $restore_graphs) {
 foreach ($original['items'] as $old) {
  if (!$restore_graphs && $old['local_graph_id']!=0) continue;
  foreach ($data['items'] as &$item) if ($item['id']==$old['id']) $item=$old;
  unset($item);
 }
 update_interface_traffic_legends(true, 1);
 check($first==$data,'Reimport must reuse peak IDs, hashes and input links');
 update_interface_traffic_legends(true, 1);
 check($first==$data,'Migration after reimport is not idempotent');
}

// An extra Minimum GPRINT must keep its original AVERAGE-series context.
foreach (array(0,700) as $custom_gid) {
 $data=$original;
 $rows=array_values(array_filter($data['items'],function($r) use($custom_gid) {return $r['graph_template_id']==900&&$r['local_graph_id']==$custom_gid;}));
 $minimum=$rows[3];$minimum['id']=99999;$minimum['text_format']='Minimum:';
 $minimum['consolidation_function_id']=2;$minimum['sequence']=4;
 $minimum['local_graph_template_item_id']=0;
 $data['items'][]=$minimum;
 check(traffic_legend_plan(array_merge($rows,array($minimum)))===false,'Custom GPRINT layout must be rejected');
 $custom=array_values(array_filter($data['items'],function($r) use($custom_gid) {return $r['graph_template_id']==900&&$r['local_graph_id']==$custom_gid;}));
 update_interface_traffic_legends(true, 1);
 $after=array_values(array_filter($data['items'],function($r) use($custom_gid) {return $r['graph_template_id']==900&&$r['local_graph_id']==$custom_gid;}));
 check($custom===$after,'Custom graph items changed during migration');
}
// A failed first transaction must leave all rows unchanged.
foreach (array('update', 'save', 'link') as $failure) {
 $data = $original;
 try {
  update_interface_traffic_legends(true, 1);
  throw new RuntimeException('Expected failed migration to throw');
 } catch (Throwable $error) {
  check(strpos($error->getMessage(), 'Unable to') === 0, 'Unexpected failure');
  check($data === $original, 'Failed migration left partially changed legends');
 }
}
// Failure in a later graph keeps the template committed, and a rerun resumes.
$data = $original;
$failure = null;
$failure_graph = 700;
try {
 update_interface_traffic_legends(true, 1);
 throw new RuntimeException('Expected later graph failure');
} catch (Throwable $error) {
 check($error->getMessage()==='Unable to update traffic legend', 'Unexpected later failure');
}
$failed_graph = array_values(array_filter($data['items'],fn($r) => $r['local_graph_id']==700));
$before_graph = array_values(array_filter($original['items'],fn($r) => $r['local_graph_id']==700));
check($failed_graph===$before_graph, 'Failed graph was not rolled back');
check(count($data['items'])===count($original['items'])+2, 'Earlier template transaction was lost');
$failure_graph = null;
update_interface_traffic_legends(true, 1);
check($data===$first, 'Resume after partial failure did not finish cleanly');
echo "PASS: five templates, five existing graphs, preserved calculations and linkage, reimport reuse, idempotence, custom GPRINT preservation and rollback\n";
