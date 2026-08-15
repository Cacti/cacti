#!/usr/bin/env php
<?php
$bootstrap = __DIR__ . '/bootstrap-unit.php';
$pest = __DIR__ . '/vendor/bin/pest';
$files = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('Unit'));
foreach ($iter as $f) {
    if ($f->getExtension() === 'php') {
        $files[] = $f->getPathname();
    }
}
sort($files);

$pass = 0;
$fail = 0;
$empty = 0;

foreach ($files as $file) {
    $rel = $file;
    $cmd = $pest . ' --bootstrap=' . escapeshellarg($bootstrap) . ' --colors=never ' . escapeshellarg($rel) . ' 2>&1';
    $out = shell_exec($cmd);

    if (preg_match('/Tests:\s+(\d+) failed(?:, (\d+) passed)?/', $out, $m)) {
        $f = (int)$m[1];
        $p = isset($m[2]) ? (int)$m[2] : 0;
        $fail += $f;
        $pass += $p;
        echo "FAIL  $rel -> $f failed, $p passed\n";
    } elseif (preg_match('/Tests:\s+(\d+) passed/', $out, $m)) {
        $pass += (int)$m[1];
        echo "PASS  $rel -> {$m[1]} passed\n";
    } elseif (preg_match('/Tests:\s+(\d+) failed/', $out, $m)) {
        $fail += (int)$m[1];
        echo "FAIL  $rel -> {$m[1]} failed\n";
    } else {
        $empty++;
        echo "ERR   $rel -> (no test output)\n";
    }
}

echo "\n=== TOTAL: $pass passed, $fail failed, $empty errors (out of " . count($files) . " files) ===\n";