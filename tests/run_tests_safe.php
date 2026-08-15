<?php
// Run each test file in its own isolated PHP process, collecting results.
// Single parent process, one child at a time, lightweight.
$bootstrap = __DIR__ . '/bootstrap-unit.php';
$pest = __DIR__ . '/vendor/bin/pest';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/Unit'));
$files = [];
foreach ($it as $f) {
    if ($f->getExtension() === 'php') $files[] = $f->getPathname();
}
sort($files);

$total_pass = 0; $total_fail = 0; $errors = [];
foreach ($files as $file) {
    $rel = str_replace(__DIR__ . '/', '', $file);
    $cmd = escapeshellarg($pest) . ' --bootstrap=' . escapeshellarg($bootstrap) . ' --colors=never ' . escapeshellarg($file) . ' 2>&1';
    $out = shell_exec($cmd);
    if (preg_match('/Tests:\s+(\d+) failed(?:,\s+(\d+) passed)?/', $out, $m)) {
        $fail = (int)$m[1]; $pass = isset($m[2]) ? (int)$m[2] : 0;
        $total_fail += $fail; $total_pass += $pass;
        if ($fail > 0) $errors[] = "$rel -> $fail failed, $pass passed";
    } elseif (preg_match('/Tests:\s+(\d+) passed/', $out, $m)) {
        $pass = (int)$m[1];
        $total_pass += $pass;
    } elseif (strpos($out, 'describe(') !== false || strpos($out, 'undefined function') !== false) {
        $errors[] = "$rel -> ERROR (describe/compat)";
    } else {
        $errors[] = "$rel -> NO RESULT";
    }
}
echo "=== TOTAL: $total_pass passed, $total_fail failed ===\n";
echo "=== " . count($files) . " files processed ===\n";
if (count($errors) > 0) {
    echo "=== Issues (" . count($errors) . ") ===\n";
    foreach ($errors as $e) echo "  $e\n";
}
