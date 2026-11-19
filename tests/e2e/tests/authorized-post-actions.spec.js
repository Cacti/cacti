/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

test('profile logout and graph maintenance use the POST action contract', async ({ page }) => {
	const origin = 'http://cacti-fixture.test';
	await page.route(origin + '/profile', route => route.fulfill({ contentType: 'text/html', body: '<button id="logout_everywhere">Clear sessions</button>' }));
	await page.goto(origin + '/profile');
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	const start = layout.indexOf('function cactiPreparePostRequest(');
	const end = layout.indexOf('\nfunction loadUrl(', start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	await page.addScriptTag({ content: '\n'.repeat(layout.slice(0, start).split('\n').length - 1) + layout.slice(start, end) + '\n//# sourceURL=' + origin + '/include/layout.js' });
	const routes = {
		'auth_profile.php': ['logout_everywhere', 'clear_user_settings', 'reset_default'],
		'automation_templates.php': ['ajax_dnd', 'graph_dnd', 'tree_dnd', 'exitonchange', 'item_add_agr', 'item_add_atr', 'item_add_ttr', 'item_remove_agr', 'item_remove_atr', 'item_remove_ttr'],
		'graphs.php': ['ajax_dnd'], 'graph_templates.php': ['ajax_dnd', 'input_remove'],
		'data_debug.php': ['run_debug', 'run_repair', 'purge'], 'utilities.php': ['purge_snmpagent_events']
	};
	for (const [file, actions] of Object.entries(routes)) {
		for (const action of actions) {
			expect(await page.evaluate(url => cactiIsPostActionUrl(url), file + '?action=' + action)).toBeTruthy();
		}
		expect(await page.evaluate(url => cactiIsPostActionUrl(url), file + '?action=view')).toBeFalsy();
	}
	const profile = fs.readFileSync(path.join(root, 'auth_profile.php'), 'utf8');
	const rendered = execFileSync('php', ['tests/fixtures/Core/AuthorizedActions.php', 'profile'], { cwd: root, encoding: 'utf8' });
	const signature = '\t\tfunction logoutEverywhere() {';
	const first = rendered.indexOf(signature);
	const last = rendered.indexOf('\n\t\tfunction graphSettings()', first);
	const original = profile.indexOf(signature);
	expect(Math.min(first, last, original)).toBeGreaterThan(-1);
	await page.addScriptTag({ content: '\n'.repeat(profile.slice(0, original).split('\n').length - 1) + rendered.slice(first, last) + '\n//# sourceURL=' + origin + '/auth_profile.php' });
	await page.evaluate(() => { window.csrfMagicToken = 'sample-token'; $.fn.dialog = function () { return this; }; });
	let posted;
	await page.route(origin + '/auth_profile.php', route => {
		posted = { method: route.request().method(), data: Object.fromEntries(new URLSearchParams(route.request().postData())) };
		return route.fulfill({ body: '' });
	});
	await page.evaluate(() => logoutEverywhere());
	await expect(page.locator('#cleared')).toContainText('All your login sessions have been cleared.');
	expect(posted).toEqual({ method: 'POST', data: { action: 'logout_everywhere', __csrf_magic: 'sample-token' } });
});
