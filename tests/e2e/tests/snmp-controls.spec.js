/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8').split('\n');

// Keep every function on its original line so Chromium coverage maps onto layout.js.
function layoutFunctions(names) {
	const output = layout.map(() => '');
	for (const name of names) {
		const start = layout.findIndex(line => line.startsWith(`function ${name}(`));
		const end = layout.indexOf('}', start);
		if (start < 0 || end < 0) {
			throw new Error(`Missing layout.js function: ${name}`);
		}
		for (let number = start; number <= end; number++) {
			output[number] = layout[number];
		}
	}
	return output.join('\n') + '\n//# sourceURL=http://cacti-fixture/include/layout.js';
}

const field = name => `<div class="formRow" id="row_${name}"><input id="${name}" value="${name}-fixture"></div>`;
const select = (name, values) => `<div class="formRow" id="row_${name}"><select id="${name}" onchange="window.notifications++; setSNMP()">${values.map(value => `<option value="${value}">${value}</option>`).join('')}</select></div>`;
const markup = `<div id="snmp_form">${[
	select('snmp_version', ['0', '1', '2', '3']),
	field('snmp_community'), field('snmp_username'),
	select('snmp_security_level', ['noAuthNoPriv', 'authNoPriv', 'authPriv']),
	select('snmp_auth_protocol', ['[None]', 'MD5', 'SHA']),
	field('snmp_password'), field('snmp_password_confirm'), field('snmp_auth_password'),
	select('snmp_priv_protocol', ['[None]', 'DES', 'AES']),
	field('snmp_priv_passphrase'), field('snmp_priv_passphrase_confirm'),
	...['snmp_engine_id', 'snmp_context', 'snmp_port', 'snmp_timeout', 'snmp_retries', 'max_oids', 'bulk_walk_size'].map(field),
].join('')}</div>`;

async function setup(page, values) {
	await page.setContent(markup);
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery-ui.js') });
	await page.evaluate(() => {
		window.notifications = 0;
		window.snmp_password = '';
		window.snmp_auth_protocol = '';
		window.snmp_priv_protocol = '';
		window.snmp_priv_passphrase = '';
		window.snmp_security_initialized = false;
		window.defaultSNMPSecurityLevel = 'authPriv';
		window.defaultSNMPAuthProtocol = 'SHA';
		window.defaultSNMPPrivProtocol = 'AES';
		for (const name of ['passwordTooShort', 'passwordPass', 'passwordMatch', 'passwordMatchTooShort', 'passwordNotMatch', 'passwordNotMatchTooShort']) {
			window[name] = name;
		}
		$.fn.delayKeyup = function () {
			return this;
		};
	});
	await page.addScriptTag({ content: layoutFunctions(['storeSNMPSecurity', 'setSNMPSecurity', 'setSNMP', 'renderSNMPControls', 'checkSNMPPassphrase', 'checkSNMPPassphraseConfirm', 'toggleFields']) });
	await page.evaluate(entries => {
		for (const [name, value] of Object.entries(entries)) {
			$('#' + name).val(value);
		}
		// The first render derives the security level from the configured protocols.
		setSNMP();
		window.notifications = 0;
	}, values);
}

const credentials = { snmp_version: '3', snmp_auth_protocol: 'SHA', snmp_priv_protocol: 'AES', snmp_password: 'auth-fixture', snmp_priv_passphrase: 'privacy-fixture' };

test('SNMPv3 without authentication keeps the username row and hides credentials', async ({ page }) => {
	await setup(page, credentials);
	expect(await page.evaluate(() => $('#snmp_security_level').val())).toBe('authPriv');
	await page.evaluate(() => {
		$('#snmp_security_level').val('noAuthNoPriv');
		setSNMP();
	});
	await expect(page.locator('#row_snmp_username')).toBeVisible();
	await expect(page.locator('#row_snmp_context')).toBeVisible();
	for (const row of ['snmp_community', 'snmp_password', 'snmp_auth_protocol', 'snmp_priv_passphrase']) {
		await expect(page.locator('#row_' + row)).toBeHidden();
	}
	expect(await page.evaluate(() => [$('#snmp_auth_protocol').val(), $('#snmp_priv_protocol').val(), snmp_auth_protocol, setSNMP.rendering]))
		.toEqual(['[None]', '[None]', 'SHA', false]);
});

test('Select2 notifications re-enter setSNMP once without rendering recursively', async ({ page }) => {
	await setup(page, credentials);
	const result = await page.evaluate(() => {
		let renders = 0;
		const render = window.renderSNMPControls;
		window.renderSNMPControls = function () {
			renders++;
			return render();
		};
		$('#snmp_security_level, #snmp_auth_protocol, #snmp_priv_protocol').addClass('select2-hidden-accessible');
		setSNMP();
		return { renders, notifications: window.notifications, rendering: setSNMP.rendering, password: $('#snmp_password').val() };
	});
	expect(result).toEqual({ renders: 1, notifications: 3, rendering: false, password: 'auth-fixture' });
	await expect(page.locator('#row_snmp_priv_passphrase')).toBeVisible();
});

test('jQuery UI selectmenus refresh after a security level change without leaking globals', async ({ page }) => {
	await setup(page, credentials);
	await page.evaluate(() => {
		$('#snmp_security_level, #snmp_auth_protocol, #snmp_priv_protocol').selectmenu();
		$('#snmp_security_level').val('authNoPriv');
		setSNMP();
	});
	await expect(page.locator('#snmp_security_level-button')).toHaveText('authNoPriv');
	await expect(page.locator('#snmp_priv_protocol-button')).toHaveText('[None]');
	await expect(page.locator('#row_snmp_priv_passphrase')).toBeHidden();
	await expect(page.locator('#row_snmp_password')).toBeVisible();
	expect(await page.evaluate(() => ['selectmenu' in window, setSNMP.rendering])).toEqual([false, false]);
});
