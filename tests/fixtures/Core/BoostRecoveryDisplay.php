<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiBoostRecoveryDisplayFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

function __($message) {
	return $message;
}

function __esc($message) {
	return $message;
}

function form_alternate_row() {
	$GLOBALS['boost_display_calls'][] = 'row';
}

function form_start($url, $id) {
	$GLOBALS['boost_display_calls'][] = array('start', $url, $id);
}

function form_end($draw_buttons = true) {
	$GLOBALS['boost_display_calls'][] = array('end', $draw_buttons);
}

\cacti_fixture_source_block(
	dirname(__DIR__, 3) . '/utilities.php',
	"if (substr_count(\$boost_status_array[0], 'complete')) {",
	"\$status = '<span class=\"deviceDown\">' . __('Other') . '</span>';\n\t\t}",
	'boost_status_display',
	__NAMESPACE__
);

\cacti_fixture_source_block(
	dirname(__DIR__, 3) . '/utilities.php',
	'if ($has_queued_data) {',
	"print '</td>';\n\t\t}",
	'boost_recovery_control',
	__NAMESPACE__
);

$statuses = array();
foreach (array('complete', 'running', 'retry scheduled', 'failed', 'overrun', 'timeout', 'unexpected') as $name) {
	$result = boost_status_display(array('boost_status_array' => array($name)));
	$statuses[$name] = $result['status'];
}

$GLOBALS['boost_display_calls'] = array();
ob_start();
boost_recovery_control(array('has_queued_data' => true));
$control = ob_get_clean();

print json_encode(array(
	'statuses' => $statuses,
	'control'  => $control,
	'calls'    => $GLOBALS['boost_display_calls'],
), JSON_THROW_ON_ERROR);
