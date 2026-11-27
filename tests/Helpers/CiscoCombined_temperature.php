<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require dirname(__DIR__, 2) . '/scripts/ss_temperature_slots.php';
$n = 0;
function check($condition, $name) { global $n; if (!$condition) throw new RuntimeException($name); echo 'PASS ' . $name . PHP_EOL; $n++; }
$ids = ['101','102','103','104','105','106'];
$values = ['101'=>41,'102'=>42,'103'=>37,'104'=>39,'105'=>44,'106'=>42];
$b = temperature_slot_binding($ids);
check($b === temperature_slot_binding(array_reverse($ids)), 'Reordered discovery retains binding');
foreach ($ids as $i=>$id) check(temperature_slot_value($b,$i+1,fn($key)=>$values[$key]??'U') === (string)$values[$id], 'Sensor '.($i+1).' maps to its own reading');
unset($values['103']);
check(temperature_slot_value($b,3,fn($key)=>$values[$key]??'U') === 'U', 'Missing sensor produces unknown');
check(temperature_slot_value($b,4,fn($key)=>$values[$key]??'U') === '39', 'Following sensor does not shift into missing history');
check(temperature_slot_value($b,7,fn($key)=>throw new RuntimeException('Must not poll unused slot')) === 'U', 'Unused slot does not poll');
check(temperature_slot_value(temperature_slot_binding(['102']),1,fn($key)=>$values[$key]??'U') === '42', 'One-sensor device');
check(temperature_slot_value($b,1,fn($key)=>'No Such Instance') === 'U', 'Invalid reading remains unknown');
check(temperature_slot_value($b,1,fn($key)=>0) === '0', 'Valid zero preserved');
foreach ([[],range(1,9),['1','1'],['1;exit'],['0']] as $bad) {
    $caught=false; try {temperature_slot_binding($bad);} catch (InvalidArgumentException $e) {$caught=true;}
    check($caught,'Reject unsupported/invalid binding '.json_encode($bad));
}
check(temperature_slot_binding(array_keys($values)) !== $b, 'Fresh inventory alone changes binding; saved membership is tested below');
echo "$n checks passed. \n";
check(temperature_slot_discover(array_keys($values),[$b]) === [$b], 'Saved binding survives rediscovery after removal');
check(temperature_slot_discover([],[$b]) === [$b], 'Saved binding survives discovery outage');
check(temperature_slot_discover([...$ids,'107'],[$b]) === [$b], 'Added sensor cannot silently replace historical slot');
check(temperature_slot_discover([],[]) === [], 'Unsupported new device has no graph row');
check(temperature_slot_discover(array_reverse($ids),[]) === [$b], 'New device discovery generates stable group');
echo "$n total checks passed.\n";
