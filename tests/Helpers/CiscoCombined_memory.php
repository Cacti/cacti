<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require dirname(__DIR__, 2).'/scripts/ss_memory_combined.php';
$n=0;
function verify($v,$label){global $n;if(!$v)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;$n++;}
foreach([['1','2'],['1000.1','1000.2'],['1001.0.1','1001.0.2']] as $ids){
 $b=memory_combined_binding($ids);
 verify($b===memory_combined_binding(array_reverse($ids)),'Ordering independent: '.$b);
 verify(memory_combined_ids($b)===$ids,'Exact index roundtrip: '.$b);
 $values=[$ids[0]=>['used'=>100,'free'=>900],$ids[1]=>['used'=>200,'free'=>800]];
 verify(memory_combined_value($b,2,'used',fn($id,$m)=>$values[$id][$m]??'U')==='200','Used maps to second pool');
 unset($values[$ids[0]]);
 verify(memory_combined_value($b,1,'free',fn($id,$m)=>$values[$id][$m]??'U')==='U','Missing first pool unknown');
 verify(memory_combined_value($b,2,'free',fn($id,$m)=>$values[$id][$m]??'U')==='800','Second pool keeps history');
}
$b=memory_combined_binding(['1']);
verify(memory_combined_value($b,2,'used',fn()=>throw new RuntimeException('Unused polled'))==='U','Unused slot not polled');
foreach(['0'=>'0','4294967297'=>'4294967297','-1'=>'U','No Such Instance'=>'U','INF'=>'U'] as $in=>$expected) verify(memory_combined_value($b,1,'used',fn()=>$in)===$expected,'Validate value '.$in);
foreach([[],['1','1'],['1;bad'],range(1,9),['1.2.3.4.5']] as $ids){$threw=false;try{memory_combined_binding($ids);}catch(InvalidArgumentException $e){$threw=true;}verify($threw,'Reject malformed/oversized '.json_encode($ids));}

$long=array_map(fn($i)=>$i.'.1234567890.1234567890.1234567890',range(1000000001,1000000008));
$threw=false;try{memory_combined_binding($long);}catch(InvalidArgumentException $e){$threw=true;}verify($threw,'Reject binding beyond database index length');
echo "$n checks passed.\n";
