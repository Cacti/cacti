/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

// Runs the production POST-transport map in a browser, so changed map entries
// carry browser coverage and every server-side POST-only action stays POST.
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

test('every POST-only action in the transport map is sent by POST', async ({ page }) => {
	await page.route('http://cacti-fixture/**', route => route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html><html><body></body></html>' }));
	await page.goto('http://cacti-fixture/cacti/index.php');
	const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	const start = layout.indexOf('function cactiIsPostActionUrl(');
	const end = layout.indexOf('\nfunction loadUrl(', start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	// Pad to the original line numbers so coverage maps onto include/layout.js.
	await page.addScriptTag({ content: '\n'.repeat(layout.slice(0, start).split('\n').length - 1) + layout.slice(start, end) + '\n//# sourceURL=http://cacti-fixture/include/layout.js' });
	const actions = JSON.parse(layout.slice(start, end).match(/var actions = (\{[\s\S]*?\});/)[1]);
	const results = await page.evaluate(map => {
		window.urlPath = '/cacti/';
		const check = (file, action) => [file, action, cactiIsPostActionUrl(`/cacti/${file}?action=${action}&id=1`)];
		return Object.entries(map).flatMap(([file, names]) => names.map(name => check(file, name)))
			.concat(Object.keys(map).map(file => check(file, 'edit')));
	}, actions);
	for (const [file, action, post] of results) {
		expect(post, `${file} ${action}`).toBe(action !== 'edit');
	}
});

test('logging out everywhere posts the session token and confirms completion', async ({ page }) => {
	const profile = fs.readFileSync(path.join(root, 'auth_profile.php'), 'utf8');
	const start = profile.indexOf('\t\tfunction logoutEverywhere()');
	const end = profile.indexOf('\n\t\t}', start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	// Render the two translated dialog strings as the profile template does.
	const script = execFileSync(process.env.CACTI_TEST_PHP || 'php', [], {
		input: "<?php function __($s) { return $s; } function __esc($s) { return htmlspecialchars($s, ENT_QUOTES); } ?>\n" +
			'\n'.repeat(profile.slice(0, start).split('\n').length - 1) + profile.slice(start, end + 4),
		encoding: 'utf8',
	});
	await page.route('http://cacti-fixture/auth_profile.php', route => route.fulfill({
		contentType: 'text/html',
		body: '<button id="logout_everywhere" onclick="logoutEverywhere()">Log out everywhere</button>',
	}));
	await page.route('http://cacti-fixture/auth_profile.php?action=logout_everywhere', route => route.fulfill({ body: '' }));
	await page.goto('http://cacti-fixture/auth_profile.php');
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery-ui.js') });
	await page.addScriptTag({ content: script + '\n//# sourceURL=http://cacti-fixture/auth_profile.php' });
	await page.evaluate(() => { window.csrfMagicToken = 'profile-fixture-token'; });
	const [request] = await Promise.all([
		page.waitForRequest(req => new URL(req.url()).searchParams.get('action') === 'logout_everywhere'),
		page.getByRole('button', { name: 'Log out everywhere' }).click(),
	]);
	expect(request.method()).toBe('POST');
	expect(request.postDataJSON()).toEqual({ __csrf_magic: 'profile-fixture-token' });
	await expect(page.getByRole('dialog')).toContainText('All your login sessions have been cleared.');
});
