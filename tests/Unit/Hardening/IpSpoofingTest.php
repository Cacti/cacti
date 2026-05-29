<?php

uses(Tests\TestCase::class);

beforeEach(function () {
    global $config;
    // Reset server variables
    $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    $config['trusted_proxies'] = array();
});

test('get_client_addr ignores X-Forwarded-For by default (Unsafe)', function () {
    $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8';
    
    // Without trusted proxies, it should return the literal REMOTE_ADDR
    expect(get_client_addr())->toBe('1.1.1.1');
});

test('get_client_addr trusts X-Forwarded-For from allowed proxy', function () {
    global $config;
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1'; // The Proxy
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.50';
    $config['trusted_proxies'] = array('10.0.0.1');
    $config['proxy_headers'] = array('HTTP_X_FORWARDED_FOR');
    
    expect(get_client_addr())->toBe('192.168.1.50');
});

test('get_client_addr takes the last IP in the chain (closest to proxy)', function () {
    global $config;
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8, 172.16.0.5, 192.168.1.50';
    $config['trusted_proxies'] = array('10.0.0.1');
    $config['proxy_headers'] = array('HTTP_X_FORWARDED_FOR');
    
    expect(get_client_addr())->toBe('192.168.1.50');
});

test('get_client_addr rejects spoofed header from untrusted IP', function () {
    global $config;
    $_SERVER['REMOTE_ADDR'] = '1.2.3.4'; // Malicious attacker
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
    $config['trusted_proxies'] = array('10.0.0.1'); // Only trust internal proxy
    
    expect(get_client_addr())->toBe('1.2.3.4');
});
