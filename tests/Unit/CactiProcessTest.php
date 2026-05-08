<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

use Cacti\Process\CactiProcess;

it('splits a simple command line correctly', function () {
    $command = "ls -l /tmp";
    $argv = CactiProcess::split($command);
    expect($argv)->toBe(['ls', '-l', '/tmp']);
});

it('splits a command line with quoted arguments', function () {
    $command = 'grep "some pattern" file.txt';
    $argv = CactiProcess::split($command);
    expect($argv)->toBe(['grep', 'some pattern', 'file.txt']);
});

it('splits a command line with escaped spaces', function () {
    $command = 'ls "My Documents" \'Program Files\'';
    $argv = CactiProcess::split($command);
    expect($argv)->toBe(['ls', 'My Documents', 'Program Files']);
});

it('runs a simple command', function () {
    $argv = ['echo', 'hello'];
    $process = CactiProcess::run($argv);
    expect($process->isSuccessful())->toBeTrue();
    expect(trim($process->getOutput()))->toBe('hello');
});

it('handles command failure', function () {
    $argv = ['false'];
    $process = CactiProcess::run($argv);
    expect($process->isSuccessful())->toBeFalse();
});

it('split does not handle escaped quotes (documented limitation)', function () {
    // Documents current behavior. If tokenizer is replaced with a real one, update.
    $argv = CactiProcess::split('echo "a\\"b"');
    expect($argv)->not->toBe(['echo', 'a"b']);
});

it('split returns empty array for empty input', function () {
    expect(CactiProcess::split(''))->toBe([]);
});
