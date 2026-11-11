<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\CardWidgetMarkup;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Changed-line coverage probe for the shared card-widget render helpers in
 * lib/html.php.  card_gauge(), card_bar(), card_bullet(), card_stackbar(),
 * card_stat() and card_severity() are pure string builders reached only from a
 * loaded page or poller, so the unit/e2e suites do not drive them.  Each helper
 * is loaded in isolation with its lone dependency (html_escape) stubbed, then
 * called across the inputs that reach every branch so the production markup -
 * not handwritten HTML - is exercised and asserted.
 */

function html_escape($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$source = dirname(__DIR__, 3) . '/lib/html.php';

foreach (['card_severity', 'card_gauge', 'card_bar', 'card_stat', 'card_stackbar', 'card_bullet'] as $function) {
	\cacti_fixture_source_function($source, $function, __NAMESPACE__);
}

$passed = 0;

// --- card_severity: every token family normalises to its modifier suffix ----
foreach (['up' => 'up', 'good' => 'up', 'warn' => 'warn', 'warning' => 'warn', 'recovering' => 'warn',
	'down' => 'down', 'bad' => 'down', 'error' => 'down', 'disabled' => 'none', '' => 'none'] as $token => $expected) {
	if (card_severity($token) !== $expected) {
		throw new \RuntimeException("card_severity('$token') did not normalise to '$expected'");
	}
}
$passed++;

// --- card_gauge: value clamps and the severity arc modifier are emitted ------
$gauge = card_gauge(150, 'Load', 'last hour', 'up');
if (!str_contains($gauge, 'ccGaugeValue--up') || !str_contains($gauge, 'ccGaugeSub')) {
	throw new \RuntimeException('card_gauge did not render the value arc and sub label');
}
// The empty sub-label branch must drop the ccGaugeSub element.
if (str_contains(card_gauge(-5, 'Load', '', 'warn'), 'ccGaugeSub')) {
	throw new \RuntimeException('card_gauge emitted an empty sub label');
}
$passed++;

// --- card_bar: optional caption branch ---------------------------------------
if (!str_contains(card_bar(50, 'Caption', 'down'), 'ccBarLabel')) {
	throw new \RuntimeException('card_bar dropped its caption');
}
if (str_contains(card_bar(200, '', 'none'), 'ccBarLabel')) {
	throw new \RuntimeException('card_bar emitted an empty caption');
}
$passed++;

// --- card_stat ---------------------------------------------------------------
if (!str_contains(card_stat('42', 'Devices up'), 'ccStatValue')) {
	throw new \RuntimeException('card_stat did not render the value');
}
$passed++;

// --- card_stackbar: labelled segments produce a chip legend ------------------
$stack = card_stackbar([
	['percent' => 40, 'severity' => 'up', 'label' => 'Up'],
	['severity' => 'down'],
], true);
if (!str_contains($stack, 'ccStackLegend') || !str_contains($stack, 'ccStackChip--up')) {
	throw new \RuntimeException('card_stackbar did not render the labelled legend');
}
$passed++;

// --- card_bullet: zero-span scale and the summary branch ---------------------
if (!str_contains(card_bullet(10, 50, 90, 'Latency', '10/50/90', 'warn', 0, 100), 'ccBulletAvg')) {
	throw new \RuntimeException('card_bullet dropped its summary');
}
$flat = card_bullet(5, 5, 5, 'Flat', '', 'none', 5, 5);
if (str_contains($flat, 'ccBulletAvg') || !str_contains($flat, 'ccBulletMarker')) {
	throw new \RuntimeException('card_bullet mishandled a zero-span scale');
}
$passed++;

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
