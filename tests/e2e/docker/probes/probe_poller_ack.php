<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
// Run only in the dedicated disposable installation, after the CLI installer.
chdir(dirname(__DIR__, 4));
require './include/cli_check.php';
require_once './lib/poller.php';
if ($database_default !== 'cacti_poller_ack_e2e') {
	throw new RuntimeException('This test requires cacti_poller_ack_e2e.');
}

function ack_assert($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
}
function ack_command(array $args) {
	$p = proc_open($args, [1=>['pipe','w'],2=>['redirect',1]], $pipes);
	$out = stream_get_contents($pipes[1]); fclose($pipes[1]);
	ack_assert(proc_close($p) === 0, 'Command failed: ' . implode(' ', $args) . ': ' . $out);
	return $out;
}
$observer = new PDO("mysql:host=$database_hostname;dbname=$database_default", $database_username, $database_password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$observer->exec("SET SESSION sql_mode=''; SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$observer->exec("UPDATE automation_networks SET enabled=''");
require_once './install/functions.php';
require_once './install/upgrades/1_2_32.php';
$cacti_upgrade_version = '1.2.32';
$observer->exec('ALTER TABLE poller_output DROP INDEX local_data_id_time');
ob_start();
upgrade_to_1_2_32();
upgrade_to_1_2_32();
ob_end_clean();
$index = $observer->query('SHOW INDEX FROM poller_output WHERE Key_name="local_data_id_time"')->fetchAll(PDO::FETCH_ASSOC);
ack_assert(count($index)===3 && $index[0]['Index_type']==='BTREE','Upgrade did not install the ordered BTREE index');

// Interrupt the real PDO connection; use Cacti's actual retry/reconnect helpers.
class AckDisconnectPDO extends PDO {
	public string $phase;
	public PDO $observer;
	public int $connection_id;
	public bool $disconnected = false;
	public function prepare(string $query, array $options = []): PDOStatement|false {
		$match = match ($this->phase) {
			'clear' => str_starts_with($query, 'TRUNCATE TABLE poller_output_ack_keys'),
			'stage' => str_starts_with($query, 'INSERT INTO poller_output_ack_keys'),
			'delete' => str_starts_with($query, 'DELETE po FROM poller_output AS po') && str_contains($query, 'poller_output_ack_keys'),
			default => false,
		};
		if ($match && !$this->disconnected) {
			$this->disconnected = true;
			$this->observer->exec('KILL CONNECTION ' . $this->connection_id);
		}
		return parent::prepare($query, $options);
	}
}
$session_key = "$database_hostname:$database_port:$database_default";
$reconnects = [];
foreach (['MEMORY','InnoDB'] as $engine) {
	$observer->exec('ALTER TABLE poller_output ENGINE=' . $engine);
	foreach (['clear','stage','delete'] as $phase) {
		$observer->exec('TRUNCATE TABLE poller_output');
		$observer->exec("INSERT INTO poller_output VALUES (900001,'in','2023-11-14 22:13:20','42')");
		$old = $database_sessions[$session_key];
		$pdo = new AckDisconnectPDO("mysql:host=$database_hostname;dbname=$database_default", $database_username, $database_password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
		// Match the session initialized by db_connect_real(), including its
		// legacy timestamp mode, before substituting the fault-injection handle.
		$pdo->exec('SET SESSION sql_mode=' . $pdo->quote($old->query('SELECT @@SESSION.sql_mode')->fetchColumn()));
		$pdo->exec('SET SESSION time_zone=' . $pdo->quote($old->query('SELECT @@SESSION.time_zone')->fetchColumn()));
		$pdo->phase = $phase; $pdo->observer = $observer;
		$pdo->connection_id = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
		$detail = $database_details[spl_object_hash($old)];
		$detail['database_conn'] = $pdo;
		$database_details[spl_object_hash($pdo)] = $detail;
		$database_sessions[$session_key] = $pdo;
		$keys = [['local_data_id'=>900001,'rrd_name'=>'in','time'=>'2023-11-14 22:13:20']];
		poller_acknowledge_output($keys, [], true);
		ack_assert($pdo->disconnected, 'Fault did not disconnect the session');
		ack_assert((int)$observer->query('SELECT COUNT(*) FROM poller_output')->fetchColumn() === 1, "$engine/$phase deleted unstaged keys");
		poller_acknowledge_output($keys, [], true);
		ack_assert((int)$observer->query('SELECT COUNT(*) FROM poller_output')->fetchColumn() === 0, "$engine/$phase retry failed");
		$reconnects[] = "$engine/$phase";
	}
}

$rrd = $config['base_path'] . '/rra/ack-poller.rrd';
$start = time() - 1860;
if (is_file($rrd)) unlink($rrd);
ack_command(['rrdtool','create',$rrd,'--start',(string)($start-60),'--step','60','DS:in:GAUGE:120:U:U','DS:out:GAUGE:120:U:U','RRA:AVERAGE:0.5:1:1000']);
// Two real script collection items, plus a backlog spanning acknowledgement batches.
$observer->exec('DELETE FROM poller_item');
$observer->exec("UPDATE host SET disabled='on'");
$observer->exec("INSERT INTO host (id,description,hostname,poller_id,status,availability_method,disabled) VALUES (900000,'Acknowledgement fixture','127.0.0.1',1,3,0,'')");
$s=$observer->prepare('INSERT INTO poller_item (local_data_id,host_id,action,rrd_name,rrd_path,rrd_num,rrd_step,arg1) VALUES (?,900000,1,?,?,?,?,?)');
$sources=[];
for($id=900001;$id<901501;$id++) {
	$observer->exec("INSERT INTO data_local (id,data_template_id,host_id) VALUES ($id,1,900000)");
	$path=$id===900001?$rrd:$config['base_path'] . '/rra/ack-' . $id . '.rrd';
	$sources[$id]=$path;
	foreach($id===900001?['in'=>'42','out'=>'43']:['in'=>'42'] as $name=>$value) $s->execute([$id,$name,$path,$id===900001?2:1,60,'/bin/echo ' . $value]);
}
foreach(['poller_enabled'=>'on','poller_type'=>'1','poller_interval'=>'60','cron_interval'=>'60','concurrent_processes'=>'1','path_php_binary'=>PHP_BINARY,'path_rrdtool'=>'/usr/bin/rrdtool','storage_location'=>'0','boost_rrd_update_enable'=>'','boost_redirect'=>'','log_verbosity'=>'5'] as $name=>$value) set_config_option($name,$value);

$runs=[];
foreach (['MEMORY','InnoDB'] as $engine) {
	$observer->exec('ALTER TABLE poller_output ENGINE=' . $engine);
	$observer->exec('TRUNCATE TABLE poller_output');
	// Advance the starting window for the second run beyond the RRD's last write.
	if ($engine==='InnoDB') {
		unlink($rrd);
		ack_command(['rrdtool','create',$rrd,'--start',(string)($start-60),'--step','60','DS:in:GAUGE:120:U:U','DS:out:GAUGE:120:U:U','RRA:AVERAGE:0.5:1:1000']);
	}
	$template=$config['base_path'] . '/rra/ack-template.rrd';
	if(is_file($template)) unlink($template);
	ack_command(['rrdtool','create',$template,'--start',(string)($start-60),'--step','60','DS:in:GAUGE:120:U:U','RRA:AVERAGE:0.5:1:1000']);
	$s=$observer->prepare('INSERT INTO poller_output VALUES (?,?,FROM_UNIXTIME(?),?)');
	foreach($sources as $id=>$path) {
		if($id!==900001) copy($template,$path);
		for($i=0;$i<30;$i++) foreach($id===900001?['in'=>'42','out'=>'43']:['in'=>'42'] as $name=>$value) $s->execute([$id,$name,$start+$i*60,$value]);
	}
	$plan=$observer->query('EXPLAIN SELECT po.output,po.time,UNIX_TIMESTAMP(po.time),po.local_data_id,dl.data_template_id,pi.rrd_path,pi.rrd_name,pi.rrd_num FROM poller_output po INNER JOIN poller_item pi ON po.local_data_id=pi.local_data_id AND po.rrd_name=pi.rrd_name INNER JOIN data_local dl ON dl.id=po.local_data_id ORDER BY po.local_data_id,po.time,po.rrd_name LIMIT 40001')->fetchAll(PDO::FETCH_ASSOC);
	$observer->exec("SET GLOBAL log_output='TABLE'; SET GLOBAL general_log=ON");
	$log_start=$observer->query('SELECT NOW(6)')->fetchColumn();
	$t=microtime(true);
	$output=ack_command([PHP_BINARY,'poller.php','--poller=1','--force','--debug']);
	$elapsed=microtime(true)-$t;
	$observer->exec('SET GLOBAL general_log=OFF');
	ack_assert((int)$observer->query('SELECT COUNT(*) FROM poller_output')->fetchColumn()===0,'Poller left queued samples: ' . $output);
	$last=ack_command(['rrdtool','lastupdate',$rrd]);
	ack_assert((bool)preg_match('/(\d+): 42 43/', $last, $last_match) && (int)$last_match[1]>=(int)$t,'Fresh collection did not reach RRD: ' . $last);
	ack_assert((int)$observer->query("SELECT COUNT(*) FROM poller_time WHERE end_time>'0000-00-00 00:00:00'")->fetchColumn()>0,'cmd.php did not finish');
	foreach($sources as $id=>$path) {
		if($id!==900001) ack_assert((bool)preg_match('/\d+: 42/',ack_command(['rrdtool','lastupdate',$path])),'Missing RRD sample for ' . $id);
	}
	$s=$observer->prepare("SELECT argument FROM mysql.general_log WHERE event_time>=? AND command_type IN ('Query','Execute') AND argument LIKE 'INSERT INTO poller_output_ack_keys%'");
	$s->execute([$log_start]); $sizes=[];
	foreach($s->fetchAll(PDO::FETCH_COLUMN) as $query) $sizes[]=substr_count(substr($query,strpos($query,'VALUES')+6),'(');
	ack_assert(array_sum($sizes)===46531 && max($sizes)<=1000,'Backlog was lost or acknowledgement was not bounded: ' . json_encode($sizes));
	$s=$observer->query('SHOW INDEX FROM poller_output WHERE Key_name="local_data_id_time"');
	ack_assert(count($s->fetchAll())===3,'Ordered-read index missing');
	$runs[]=['engine'=>$engine,'sources'=>count($sources),'backlog_rows'=>45030,'elapsed_seconds'=>$elapsed,'batch_sizes'=>$sizes,'lastupdate'=>$last,'explain'=>$plan,'poller_output'=>$output];
}
echo json_encode(['php'=>PHP_VERSION,'database'=>$observer->query('SELECT VERSION()')->fetchColumn(),'reconnects'=>$reconnects,'runs'=>$runs],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . PHP_EOL;
