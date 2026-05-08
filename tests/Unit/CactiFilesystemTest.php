<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

use Cacti\Filesystem\CactiFilesystem;

beforeEach(function () {
    CactiFilesystem::reset();
});

it('detects existing files', function () {
    expect(CactiFilesystem::exists(__FILE__))->toBeTrue();
});

it('detects non-existent files', function () {
    expect(CactiFilesystem::exists('/path/to/nowhere/'.uniqid()))->toBeFalse();
});

it('can create and remove directories', function () {
    $dir = sys_get_temp_dir() . '/cacti_test_' . uniqid();
    
    CactiFilesystem::mkdir($dir);
    expect(is_dir($dir))->toBeTrue();
    
    CactiFilesystem::remove($dir);
    expect(is_dir($dir))->toBeFalse();
});

it('can dump content to a file', function () {
    $file = sys_get_temp_dir() . '/cacti_test_file_' . uniqid();
    $content = "test content";
    
    CactiFilesystem::dumpFile($file, $content);
    expect(file_get_contents($file))->toBe($content);

    // nosemgrep: php.lang.security.unlink-use.unlink-use - test cleanup of file we just created in temp dir
    unlink($file);
});
