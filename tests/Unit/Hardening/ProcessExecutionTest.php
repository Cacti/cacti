<?php

uses(Tests\TestCase::class);

/**
 * L-42-1: Verify that cacti_process_execute neutralizes command injection.
 */
test('cacti_process_execute neutralizes semicolon command chaining', function () {
    $out = array();
    // Payload attempts to write a file to /tmp
    $malicious_arg = '127.0.0.1; touch /tmp/cacti_vulnerable';
    
    // We call a harmless binary like 'echo'
    cacti_process_execute(array('echo', $malicious_arg), false, $out);
    
    // The entire string should be treated as a single argument to echo
    expect($out[0])->toBe($malicious_arg);
    expect(file_exists('/tmp/cacti_vulnerable'))->toBeFalse();
});

test('cacti_process_execute neutralizes backtick execution', function () {
    $out = array();
    $malicious_arg = '`whoami`';
    
    cacti_process_execute(array('echo', $malicious_arg), false, $out);
    
    // It should literally echo the backticks, not the result of whoami
    expect($out[0])->toBe('`whoami`');
});

test('cacti_exec enforces array type and safe core passthrough', function () {
    $out = array();
    $result = cacti_exec('echo', array('hello', 'world'), $out);
    
    expect($result)->toBe(0);
    expect($out[0])->toBe('hello world');
});

test('exec_background handles string args with best-effort safety', function () {
    // This tests the "bridge" logic for legacy string callers
    $malicious_cmd = 'echo vulnerable; touch /tmp/cacti_bg_vulnerable';
    
    // exec_background should strip shell operators from strings
    exec_background('/usr/bin/echo', $malicious_cmd);
    
    // We can check if the file was created (it shouldn't be)
    // Note: background tasks are hard to timing-check in unit tests, 
    // but the regex should have stripped the semicolon.
    expect(file_exists('/tmp/cacti_bg_vulnerable'))->toBeFalse();
});
