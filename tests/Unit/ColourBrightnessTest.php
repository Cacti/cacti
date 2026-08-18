<?php

/**
 * Tests for colourBrightness() in lib/rrd.php
 *
 * Validates fix for integer percentages, negative percent math,
 * and RGB lower-bounds clamping.
 */

require_once dirname(__DIR__, 2) . '/lib/rrd.php';

test('colourBrightness returns valid hex for positive decimal percent', function () {
	$result = colourBrightness('808080', 0.5);
	expect($result)->toMatch('/^[0-9a-f]{6}$/i');
});

test('colourBrightness returns valid hex for negative decimal percent', function () {
	$result = colourBrightness('808080', -0.5);
	expect($result)->toMatch('/^[0-9a-f]{6}$/i');
});

test('colourBrightness handles integer percentage by normalizing to decimal', function () {
	$decimal = colourBrightness('808080', 0.5);
	$integer = colourBrightness('808080', 50);
	expect($integer)->toBe($decimal);
});

test('colourBrightness handles negative integer percentage', function () {
	$decimal = colourBrightness('808080', -0.5);
	$integer = colourBrightness('808080', -50);
	expect($integer)->toBe($decimal);
});

test('colourBrightness preserves hash prefix', function () {
	$result = colourBrightness('#808080', 0.5);
	expect($result)->toStartWith('#');
	expect(strlen($result))->toBe(7);
});

test('colourBrightness lighter makes white whiter', function () {
	$result = colourBrightness('ffffff', 0.5);
	expect($result)->toBe('ffffff');
});

test('colourBrightness darker black stays black', function () {
	$result = colourBrightness('000000', -0.5);
	expect($result)->toBe('000000');
});

test('colourBrightness does not produce negative RGB values', function () {
	$result = colourBrightness('010101', -0.99);
	// dechex() on a negative int yields a two's-complement string, so
	// assert well-formed 6-char hex before extracting individual channels.
	expect($result)->toMatch('/^[0-9a-f]{6}$/i');
	$r = hexdec(substr($result, 0, 2));
	$g = hexdec(substr($result, 2, 2));
	$b = hexdec(substr($result, 4, 2));
	expect($r)->toBeGreaterThanOrEqual(0);
	expect($g)->toBeGreaterThanOrEqual(0);
	expect($b)->toBeGreaterThanOrEqual(0);
});

test('colourBrightness does not exceed 255 for any channel', function () {
	$result = colourBrightness('fefefe', 0.99);
	$r = hexdec(substr($result, 0, 2));
	$g = hexdec(substr($result, 2, 2));
	$b = hexdec(substr($result, 4, 2));
	expect($r)->toBeLessThanOrEqual(255);
	expect($g)->toBeLessThanOrEqual(255);
	expect($b)->toBeLessThanOrEqual(255);
});

// Boundary: percent=0 routes to the darker branch with zero darkening;
// the result must be the original colour unchanged.
test('colourBrightness with percent 0 returns original colour', function () {
	expect(colourBrightness('4080c0', 0))->toBe('4080c0');
});

// Boundary: integer 1 is within [-1, 1] so the normalisation path is skipped.
// The lighter formula at percent=1.0 keeps 100% of original and adds 0% white
// — result equals the original colour. Integer 1 and decimal 1.0 must agree.
test('colourBrightness with integer percent 1 returns original colour', function () {
	expect(colourBrightness('4080c0', 1))->toBe(colourBrightness('4080c0', 1.0));
	expect(colourBrightness('4080c0', 1))->toBe('4080c0');
});

// Boundary: integer -1 is within [-1, 1] so the normalisation path is skipped.
// The darker formula at percent=-1.0 multiplies by 0 — result is pure black.
// Integer -1, decimal -1.0, and integer -100 must all agree.
test('colourBrightness with integer percent -1 returns black', function () {
	expect(colourBrightness('4080c0', -1))->toBe(colourBrightness('4080c0', -1.0));
	expect(colourBrightness('4080c0', -1))->toBe(colourBrightness('4080c0', -100));
	expect(colourBrightness('4080c0', -1))->toBe('000000');
});

// Exact-value regression: representative Cacti call with a mid-range color
// and the decimal percent used by the gradient renderer (-0.4 for darkening).
// Lighter (0.4): round(r*0.4)+round(255*0.6), darker (-0.4): round(r*0.6)
test('colourBrightness produces correct exact value for representative Cacti call (lighter)', function () {
	// '1a2b3c': r=26,g=43,b=60 → lighter by 0.4 → r=163(a3),g=170(aa),b=177(b1)
	expect(colourBrightness('1a2b3c', 0.4))->toBe('a3aab1');
});

test('colourBrightness produces correct exact value for representative Cacti call (darker)', function () {
	// '1a2b3c': r=26,g=43,b=60 → darker by -0.4 → r=16(10),g=26(1a),b=36(24)
	expect(colourBrightness('1a2b3c', -0.4))->toBe('101a24');
});
