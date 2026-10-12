/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');
const source = readFileSync(resolve(root, 'include/themes/midwinter/main.js'), 'utf8');
const start = source.indexOf('function setupTheme() {');
const end = source.indexOf('\nfunction setupThemeActions()', start);
const layout = readFileSync(resolve(root, 'include/layout.js'), 'utf8');

function layoutFunction(name: string) {
	const start = layout.indexOf(`function ${name}(`);
	const end = layout.indexOf('\nfunction ', start + 1);
	expect(start).toBeGreaterThan(0);
	expect(end).toBeGreaterThan(start);
	return layout.slice(start, end);
}

async function formFixture(page: Page) {
	await page.setContent('<!DOCTYPE html><form id="other"><select name="rows" style="display:none"><option>30</option></select></form>' +
		'<form id="edit" class="cactiFormStart"><input id="description" name="description" value="Original">' +
		'<select name="realm" style="display:none"><option>2</option></select>' +
		'<select name="groups[]" multiple style="display:none"><option value="1" selected>A</option><option value="2" selected>B</option></select>' +
		'<input type="checkbox" name="unchecked"><input type="checkbox" name="disabled" disabled>' +
		'<button type="submit">Save</button></form><select form="edit" name="associated" style="display:none"><option>4</option></select>' +
		'<div class="error"><span></span></div>');
	for (const file of ['jquery.js', 'jquery-ui.js', 'jquery.validate/jquery.validate.js']) {
		await page.addScriptTag({ path: resolve(root, 'include/js', file) });
	}
	await page.addScriptTag({ content: layout.slice(layout.indexOf('$.fn.serializeForm ='), layout.indexOf('\n// Borrowed')) });
	await page.addScriptTag({ content: layoutFunction('formValidate') + '\n' + layoutFunction('checkFormStatus') });
	await page.evaluate(() => {
		const browser = window as any;
		browser.formArray = {};
		browser.formRules = { '#edit': { description: { required: true } } };
		browser.warningCount = 0;
		browser.warningMessage = () => browser.warningCount++;
		browser.submissions = [];
		browser.postUrl = (_options: any, data: any) => {
			browser.submissions.push(data);
			return browser.$.Deferred().resolve().promise();
		};
		browser.$('button').button();
		browser.formValidate('#edit', 'edit.php');
	});
}

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

test('form serialization includes its hidden and associated controls without unrelated filters', async ({ page }) => {
	await formFixture(page);
	const serialized = await page.evaluate(() => {
		const browser = window as any;
		return { object: browser.$('#edit').serializeObject(), form: browser.$('#edit').serializeForm() };
	});
	expect(serialized.object).toEqual({ description: 'Original', realm: '2', 'groups[]': ['1', '2'], associated: '4' });
	expect(serialized.form).toEqual({ ...serialized.object, unchecked: '' });
});

test('edited forms warn before navigation and invalid submissions can be corrected', async ({ page }) => {
	await formFixture(page);
	const errors: string[] = [];
	page.on('pageerror', error => errors.push(error.message));
	expect(await page.evaluate(() => (window as any).checkFormStatus('index.php', 'noheader'))).toBe(true);
	await page.locator('#description').fill('Edited');
	expect(await page.evaluate(() => (window as any).checkFormStatus('index.php', 'noheader'))).toBe(false);
	expect(await page.evaluate(() => (window as any).warningCount)).toBe(1);
	await page.locator('#description').fill('');
	await page.getByRole('button', { name: 'Save' }).click();
	await expect(page.locator('label.error, label.txtErrorText')).toBeVisible();
	await expect(page.getByRole('button', { name: 'Save' })).toBeEnabled();
	await page.locator('#description').fill('Corrected');
	await page.locator('#description').press('Tab');
	await page.getByRole('button', { name: 'Save' }).click();
	expect(errors).toEqual([]);
	expect(await page.evaluate(() => (window as any).submissions)).toHaveLength(1);
});

test('closing or expiring a notice preserves persistent errors', async ({ page }) => {
	await page.setContent('<!DOCTYPE html>');
	for (const file of ['jquery.js', 'jquery.toast.js', 'ui-notices.js']) {
		await page.addScriptTag({ path: resolve(root, 'include/js', file) });
	}
	await page.clock.install();
	await page.evaluate(() => {
		const browser = window as any;
		browser.$.fx.off = true;
		browser.PopupError('Persistent error', 'Error');
		browser.PopupNotice('Saved', 'Success');
	});
	await page.locator('.jq-icon-success .close-jq-toast-single').click();
	await expect(page.locator('.jq-icon-error')).toContainText('Persistent error');
	await page.evaluate(() => (window as any).PopupNotice('Another save', 'Success'));
	await page.clock.fastForward(10001);
	await expect(page.locator('.jq-icon-success')).toHaveCount(0);
	await expect(page.locator('.jq-icon-error')).toHaveCount(1);
	await page.locator('.jq-icon-error .close-jq-toast-single').click();
	await expect(page.locator('.jq-toast-wrap')).toHaveCount(0);
});

test('installer headings and layout fit desktop and narrow screens', async ({ page }) => {
	await page.setContent('<!DOCTYPE html><h2>Prerequisites</h2><h3>Database</h3><div class="cactiInstallTable">Installer</div>');
	await page.addStyleTag({ path: resolve(root, 'install/install.css') });
	await expect(page.locator('h2')).toHaveCSS('font-size', '20.8px');
	await expect(page.locator('h3')).toHaveCSS('font-size', '19.2px');
	for (const width of [1440, 375, 320]) {
		await page.setViewportSize({ width, height: 700 });
		expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
	}
	await page.setViewportSize({ width: 1440, height: 700 });
	await expect(page.locator('.cactiInstallTable')).toHaveCSS('width', '900px');
});
