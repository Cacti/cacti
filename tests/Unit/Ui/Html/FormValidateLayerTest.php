<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

use Symfony\Component\Validator\Constraints\Email;

require_once dirname(__DIR__, 4) . '/lib/form_validate.php';

beforeEach(function () : void {
	$_SESSION = [];
});

test('explicit constraint entries resolve only to Symfony constraints', function () : void {
	$email = new Email();

	expect(form_validate_build_constraint($email))->toBe($email)
		->and(form_validate_build_constraint(new stdClass()))->toBeNull()
		->and(form_validate_build_constraint('Regex'))->toBeNull()
		->and(form_validate_build_constraint([42]))->toBeNull()
		->and(form_validate_build_constraint(['NoSuchConstraint', []]))->toBeNull()
		->and(form_validate_build_constraint([stdClass::class, []]))->toBeNull()
		->and(form_validate_build_constraint([Email::class, []]))->toBeInstanceOf(Email::class);
});

test('fields without a user value or a constraint are not validated', function () : void {
	$fields = [
		'header'  => 'not a field definition',
		'id'      => ['method' => 'hidden'],
		'save'    => ['method' => 'submit'],
		'comment' => ['method' => 'textarea', 'optional' => true],
		'mode'    => ['method' => 'drop_array', 'default' => '1', 'array' => [1 => 'One']],
	];

	expect(form_validate_fields($fields, ['mode' => '1']))->toBe([])
		->and(form_validate_fields($fields, ['mode' => '9']))->toHaveKey('mode');
});

test('stashing errors flags the fields, keeps the input and raises the save error', function () : void {
	expect(form_validate_stash([], ['hex' => 'zz']))->toBeFalse()
		->and($_SESSION)->toBe([]);

	expect(form_validate_stash(['hex' => [2 => 'bad hex', 5 => 'too long']], ['name' => 'Lime', 'hex' => 'zz']))->toBeTrue()
		->and($_SESSION[SESS_FIELD_VALUES])->toBe(['name' => 'Lime', 'hex' => 'zz'])
		->and($_SESSION[SESS_ERROR_FIELDS])->toBe(['hex' => 3])
		->and($_SESSION[SESS_FIELD_ERRORS])->toBe(['hex' => ['bad hex', 'too long']])
		->and(is_error_message())->toBeTrue();
});

test('a stashed field block is escaped and shown exactly once', function () : void {
	$_SESSION[SESS_FIELD_ERRORS] = ['hex' => ['<b>bad</b> hex', 'too long'], 'name' => 'not a list'];

	expect(form_validate_field_block('missing'))->toBe('')
		->and(form_validate_field_block('hex'))->toBe("<div class='formError txtErrorTextBox' role='alert'><ul><li>&lt;b&gt;bad&lt;/b&gt; hex</li><li>too long</li></ul></div>")
		->and(form_validate_field_block('hex'))->toBe('')
		->and(form_validate_field_block('name'))->toBe('')
		->and($_SESSION[SESS_FIELD_ERRORS])->toBe([]);
});

// The color editor's save path and the bootstrap wiring only run inside a web
// request, so they are exercised by a source-slice probe in its own process.
test('the color editor validates through the layer and falls back without it', function () : void {
	$pipes   = [];
	$process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/Core/ColorFormValidateCoverage.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');
	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toBe(['passed' => 35]);
});
