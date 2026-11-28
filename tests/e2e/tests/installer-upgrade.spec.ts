/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

import { test, expect } from '@playwright/test';

test.skip(process.env.CACTI_INSTALLER_UPGRADE_TEST !== '1', 'Requires the populated disposable installer fixture');

async function login(page, username: string, password: string, realm: string) {
	await page.goto('./');
	await page.locator('#login_username').fill(username);
	await page.locator('#login_password').fill(password);
	await page.locator('#realm').selectOption(realm, { force: true });
	await page.locator('#auth button[type=submit]').click();
	await expect(page.locator('#auth')).toHaveCount(0);
}

test('upgraded local authentication and core pages load without failed assets or browser exceptions', async ({ page }) => {
	const errors: string[] = [];
	page.on('pageerror', error => errors.push(error.message));
	page.on('response', response => {
		if (/\.(js|css)(\?|$)/.test(response.url()) && response.status() >= 400) {
			errors.push(`Asset HTTP ${response.status()}: ${new URL(response.url()).pathname}`);
		}
	});
	await login(page, 'admin', 'installer-e2e-pass', '0');
	for (const path of ['index.php', 'host.php', 'graphs.php']) {
		const response = await page.goto(path, { waitUntil: 'networkidle' });
		expect(response?.status()).toBe(200);
		await expect(page.locator('#auth')).toHaveCount(0);
		await expect(page.locator('body')).not.toContainText('Fatal error');
	}
	expect(errors).toEqual([]);
});

test('migrated LDAP provider authenticates its existing user', async ({ page }) => {
	await login(page, 'ldapuser', 'ldap-e2e-pass', '1007');
	await expect(page.getByRole('banner')).toBeVisible();
	await expect(page.locator('body')).toContainText('Logged in as ldapuser');
});

test('post-upgrade sessions reject invalid CSRF and retain authenticated reads', async ({ page }) => {
	await login(page, 'admin', 'installer-e2e-pass', '0');
	const response = await page.request.post('host.php', {
		maxRedirects: 0,
		form: { action: 'save', id: '700', description: 'CSRF rejected mutation', __csrf_magic: 'invalid' },
	});
	expect(response.status()).toBe(302);
	expect(response.headers().location).toBe('/cacti/host.php');
	expect((await page.goto('host.php?action=edit&id=700'))?.status()).toBe(200);
	await expect(page.locator('#auth')).toHaveCount(0);
	await expect(page.locator('#description')).toHaveValue('Installer custom device');
});

test('installer diagnostics are downloadable by an administrator and excluded from anonymous responses', async ({ page, request }) => {
	const anonymous = await request.get('install/report.php');
	expect(anonymous.headers()['content-disposition']).toBeUndefined();
	await login(page, 'admin', 'installer-e2e-pass', '0');
	const response = await page.request.get('install/report.php');
	expect(response.status()).toBe(200);
	expect(response.headers()['content-disposition']).toContain('attachment');
	const report = await response.json();
	expect(report.state).toBe('succeeded');
	expect(report.operations.some(operation => operation.operation === 'authentication' && operation.succeeded)).toBe(true);
	expect(JSON.stringify(report)).not.toMatch(/testroot|installer-e2e-pass|ldap-e2e-pass|specific_password|dbpass/);
});
