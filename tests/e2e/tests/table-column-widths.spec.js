/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

function render(request) {
	return JSON.parse(execFileSync(process.env.CACTI_TEST_PHP || 'php', ['-d', 'display_errors=stderr', path.join(root, 'tests/fixtures/Core/TableColumnSizing.php'), JSON.stringify(request)], { encoding: 'utf8' })).html;
}

test('PHP column identity persists across refreshes and isolates logical views', async () => {
	const key = request => /data-colsizing='([^']+)'/.exec(render(request))[1];
	for (const logical of [{}, { tab: 'devices' }, { report: 'summary' }]) {
		for (const action of ['go', 'change', 'clear', 'reset']) {
			expect(key({ ...logical, action })).toBe(key(logical));
		}
	}
	expect(key({ tab: 'devices', action: 'go' })).not.toBe(key({ tab: 'graphs', action: 'go' }));
	expect(key({ report: 'summary', action: 'go' })).not.toBe(key({ report: 'details', action: 'go' }));
	expect(key({ action: 'edit' })).not.toBe(key({}));
	expect(key({ action: 'edit' })).not.toBe(key({ action: 'view' }));
});

test('column widths survive replacement, skip wrappers and support plugin tables', async ({ page }) => {
	await page.goto('about:blank');
	await page.setContent('<div id="transient-wrapper" class="cactiTable"><table id="monitor_go1_child" data-colsizing="monitor1_child" class="cactiTable"><tr><th class="ui-resizable" style="width:150px">Name</th><th class="ui-resizable" style="width:90px">Status</th></tr></table></div><table id="plugin" class="cactiTable"><tr><th class="ui-resizable" style="width:75px">Plugin</th></tr></table>');
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	const source = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	const start = source.indexOf('function saveTableWidths(');
	const end = source.indexOf('\n/**', start);
	await page.addScriptTag({ content: '\n'.repeat(source.slice(0, start).split('\n').length - 1) + source.slice(start, end) + '\n//# sourceURL=http://cacti-fixture/include/layout.js' });
	// The application's storage interface, backed by a Map so the fixture does
	// not depend on about:blank origin storage permissions.
	await page.evaluate(() => {
		window.widths = new Map();
		window.Storages = { localStorage: { isSet: key => widths.has(key), get: key => widths.get(key), set: (key, value) => widths.set(key, [...value]), remove: key => widths.delete(key) } };
		saveTableWidths(false);
	});
	const saved = await page.evaluate(() => [...widths.entries()]);
	expect(saved.map(([key]) => key).sort()).toEqual(['monitor1_child', 'plugin']);
	expect(saved.find(([key]) => key === 'monitor1_child')[1].slice(1)).toEqual([150, 90]);
	await page.evaluate(() => {
		const table = document.querySelector('#monitor_go1_child');
		table.id = 'monitor_clear1_child';
		$(table).find('th').css('width', 40);
		saveTableWidths(true);
	});
	await expect(page.locator('#monitor_clear1_child th').first()).toHaveAttribute('resizeWidth', '150');
	await expect(page.locator('#monitor_clear1_child th').last()).toHaveAttribute('resizeWidth', '90');
	await page.evaluate(() => { widths.set('monitor1_child', undefined); saveTableWidths(true); widths.set('plugin', [1, 0]); saveTableWidths(true); });
	await page.evaluate(() => { document.querySelector('#plugin').setAttribute('data-colsizing', ''); saveTableWidths(false); });
	expect(await page.evaluate(() => widths.has('plugin'))).toBe(true);
});
