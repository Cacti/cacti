<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

use Cacti\Http\CactiRequest;

beforeEach(function () {
    CactiRequest::reset();
});

afterEach(function () {
    $_GET = [];
    $_POST = [];
    $_REQUEST = [];
    $_SESSION = [];
    CactiRequest::reset();
});

it('gets integer parameters correctly', function () {
    $_GET['test_id'] = '123';
    $_POST['post_id'] = '456';
    
    expect(CactiRequest::getInt('test_id'))->toBe(123);
    expect(CactiRequest::postInt('post_id'))->toBe(456);
});

it('gets alphanumeric strings correctly', function () {
    $_GET['name'] = 'User123!@#';
    expect(CactiRequest::getAlnum('name'))->toBe('User123');
});

it('handles session variables', function () {
    CactiRequest::setSession('my_var', 'my_val');
    expect($_SESSION['my_var'])->toBe('my_val');
    expect(CactiRequest::getSession('my_var'))->toBe('my_val');
});

it('round-trips set/has/get via attributes', function () {
    CactiRequest::set('roundtrip', 'value');
    expect(CactiRequest::has('roundtrip'))->toBeTrue();
    expect(CactiRequest::get('roundtrip'))->toBe('value');
});

it('does not write to $_POST on set()', function () {
    $_POST = [];
    CactiRequest::set('only_attr', 'v');
    expect($_POST)->not->toHaveKey('only_attr');
});

it('isAjax uses strict comparison', function () {
    $_GET = ['header' => '0'];
    CactiRequest::reset();
    expect(CactiRequest::isAjax())->toBeFalse();
});
