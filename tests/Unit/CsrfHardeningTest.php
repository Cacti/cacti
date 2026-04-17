<?php

$globalPath = __DIR__ . '/../../include/global.php';
$csrfPath = __DIR__ . '/../../include/vendor/csrf/csrf-magic.php';

test('global.php GET deny-list covers state-mutating actions', function () use ($globalPath) {
    $source = file_get_contents($globalPath);
    $actions = array('save', 'delete', 'remove', 'purge', 'disable', 'enable',
        'install', 'uninstall', 'moveup', 'movedown', 'kill', 'clear');
    foreach ($actions as $action) {
        expect($source)->toContain("'" . $action . "'");
    }
});

test('csrf-magic fallback cookie sets httponly flag', function () use ($csrfPath) {
    $source = file_get_contents($csrfPath);
    expect($source)->toContain("'httponly'");
});

test('csrf-magic fallback cookie sets samesite flag', function () use ($csrfPath) {
    $source = file_get_contents($csrfPath);
    expect($source)->toContain("'samesite'");
});

test('csrf-magic uses hash_equals for token comparison', function () use ($csrfPath) {
    $source = file_get_contents($csrfPath);
    expect($source)->toContain('hash_equals');
});
