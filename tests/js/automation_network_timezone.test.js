/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const source = fs.readFileSync(
	path.join(__dirname, '..', '..', 'automation_networks.php'),
	'utf8'
);

function timepickerNow(epoch, serverOffsetMinutes) {
	const selected = new Date(epoch);

	/* This is the bundled timepicker add-on's Now-button conversion. */
	selected.setMinutes(
		selected.getMinutes() + selected.getTimezoneOffset() + serverOffsetMinutes
	);

	return {
		hour: selected.getHours(),
		minute: selected.getMinutes(),
	};
}

test('Automation Network picker receives the current server offset', () => {
	const picker = source.match(/\$\('#start_at'\)\.datetimepicker\(\{[\s\S]*?\}\);/);

	assert.notEqual(picker, null, 'start_at datetimepicker configuration must exist');
	assert.match(picker[0], /timezone:\s*<\?php print intval\(date\('Z'\) \/ 60\); \?>,/);
});

test('Now uses UTC server wall time in a browser two hours east', () => {
	const originalTimezone = process.env.TZ;

	try {
		process.env.TZ = 'Europe/Amsterdam';
		assert.deepEqual(
			timepickerNow('2026-07-23T12:00:00Z', 0),
			{ hour: 12, minute: 0 }
		);
	} finally {
		process.env.TZ = originalTimezone;
	}
});

test('Now supports server timezones with half-hour offsets', () => {
	assert.deepEqual(
		timepickerNow('2026-07-23T12:00:00Z', 330),
		{ hour: 17, minute: 30 }
	);
});
