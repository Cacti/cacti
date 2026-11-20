<?php
error_reporting(E_ALL);

$execOutput = array();
exec('php '.dirname(dirname(__FILE__)).'/bin/export-plural-rules.php php --output='.dirname(__FILE__).'/data.php', $execOutput, $rc);
if ($rc !== 0) {
    throw new Exception(implode("\n", $execOutput));
}
exec('php '.dirname(dirname(__FILE__)).'/bin/export-plural-rules.php json --output='.dirname(__FILE__).'/data.json', $execOutput, $rc);
if ($rc !== 0) {
    throw new Exception(implode("\n", $execOutput));
}

require_once dirname(dirname(__FILE__)).'/src/autoloader.php';
