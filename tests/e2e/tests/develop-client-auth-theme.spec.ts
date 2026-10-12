/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');
const source = readFileSync(resolve(root, 'include/themes/midwinter/main.js'), 'utf8');
const start = source.indexOf('function setupTheme() {');
const end = source.indexOf('\nfunction setupThemeActions()', start);

for (const enhanced of [false, true]) {
	test(`Midwinter keeps authentication controls with ${enhanced ? 'Select2' : 'native'} realms`, async ({ page }) => {
		await page.route('http://cacti.test/**', route => route.fulfill({
			contentType: 'text/html',
			body: `<!DOCTYPE html><div class="cactiAuthBody"><div class="cactiAuthArea">
				<legend>User Login</legend><form id="auth" method="post">
				<input type="hidden" name="action" value="login">
				<div class="cactiAuthTitle"><table class="cactiAuthTable"><tr><td>Enter your credentials</td></tr></table></div>
				<div class="cactiAuth"><table class="cactiAuthTable">
				<tr><td><label for="login_username">Username</label><input id="login_username" type="text" name="login_username" value="alice"></td></tr>
				<tr><td><input id="login_password" type="password" name="login_password" value="fixture-password"></td></tr>
				<tr><td><label for="realm">Realm</label><select id="realm" name="realm"><option value="0">Local</option><option value="2" selected>LDAP</option></select></td></tr>
				<tr><td><input type="hidden" name="__csrf_magic" value="fixture-token"><textarea name="provider_note">Test account</textarea></td></tr>
				<tr><td><a class="sso-login-link" href="login_sso.php?action=login&amp;realm=1001">Login with SSO</a><button type="submit">Login</button></td></tr>
				</table></div></form><div class="versionInfo">Fixture version</div></div></div>`,
		}));
		await page.goto('http://cacti.test/auth_login.php');
		await page.addScriptTag({ path: resolve(root, 'include/js/jquery.js') });
		await page.addScriptTag({ path: resolve(root, 'include/js/select2.js') });
		await page.addStyleTag({ path: resolve(root, 'include/themes/midwinter/select2.css') });
		await page.evaluate(enhanced => {
			const browser = window as any;
			browser.mdw = { domMap: {
				cactiAuthBody: '.cactiAuthBody', cactiAuthArea: '.cactiAuthArea legend',
				cactiAuthTable: '.cactiAuthTable', cactiAuthForm: '.cactiAuth',
				versionInfo: '.versionInfo', loginUsername: '#login_username', cactiContent: '#cactiContent',
			} };
			browser.$.fn.rebind = function(event: string, handler: Function) { return this.off(event).on(event, handler); };
			if (enhanced) browser.$('#realm').select2();
		}, enhanced);
		expect(start).toBeGreaterThan(0);
		expect(end).toBeGreaterThan(start);
		await page.addScriptTag({ content: source.slice(start, end) });
		await page.evaluate(() => {
			const browser = window as any;
			browser.setupTheme();
			browser.setupTheme();
		});
		await expect(page.locator('#auth .sso-login-link')).toBeVisible();
		await expect(page.locator('#auth .sso-login-link')).toHaveAttribute('href', 'login_sso.php?action=login&realm=1001');
		if (enhanced) {
			await expect(page.locator('#auth .select2-container')).toHaveCount(1);
			await expect(page.locator('#auth .select2-selection')).toBeVisible();
			await page.locator('#auth .select2-selection').click();
			await page.getByRole('option', { name: 'Local', exact: true }).click();
		} else {
			await expect(page.locator('#realm')).toBeVisible();
			await page.locator('#realm').selectOption('0');
		}
		expect(await page.locator('#auth').evaluate(form => Object.fromEntries(new FormData(form as HTMLFormElement))))
			.toEqual({ action: 'login', login_username: 'alice', login_password: 'fixture-password', realm: '0', __csrf_magic: 'fixture-token', provider_note: 'Test account' });
		await expect(page.locator('#auth button[type="submit"]')).toBeVisible();
	});
}
