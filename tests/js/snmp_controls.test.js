/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../include/layout.js'), 'utf8');
function extract(name) {
	const start = source.indexOf(`function ${name}(`);
	assert.notEqual(start, -1);
	let depth = 0;
	for (let i = source.indexOf('{', start); i < source.length; i++) {
		if (source[i] === '{') depth++;
		if (source[i] === '}' && --depth === 0) return source.slice(start, i + 1);
	}
	throw new Error(`Missing function body: ${name}`);
}

function fixture() {
	const values = new Map(Object.entries({
		snmp_version: '3', snmp_security_level: 'authPriv', snmp_auth_protocol: 'SHA',
		snmp_priv_protocol: 'AES', snmp_password: 'auth-fixture',
		snmp_password_confirm: 'auth-fixture', snmp_priv_passphrase: 'privacy-fixture',
		snmp_priv_passphrase_confirm: 'privacy-fixture', snmp_username: 'user-fixture',
		snmp_community: 'community-fixture', snmp_context: 'context-fixture',
	}));
	const rows = new Map();
	let notifications = 0;
	let throwNext = false;
	const context = { snmp_password: '', snmp_auth_protocol: '', snmp_priv_protocol: '',
		snmp_priv_passphrase: '', snmp_security_initialized: false,
		defaultSNMPSecurityLevel: 'authPriv', defaultSNMPAuthProtocol: 'SHA', defaultSNMPPrivProtocol: 'AES',
		checkSNMPPassphrase() {}, checkSNMPPassphraseConfirm() {},
		toggleFields(fields) { for (const [name, visible] of Object.entries(fields)) rows.set(`row_${name}`, visible); },
		$(selector) {
			const name = selector.slice(1);
			return {
				val(value) { if (arguments.length) { values.set(name, value); return this; } return values.get(name) ?? ''; },
				prop() { return this; }, is() { return false; },
				hide() { rows.set(name, false); return this; }, show() { rows.set(name, true); return this; },
				selectmenu() { if (throwNext) { throwNext = false; throw new Error('fixture refresh failure'); } return undefined; },
				hasClass() { return true; }, delayKeyup() { return this; }, on() { return this; },
				trigger(event) {
					assert.equal(event, 'change.select2');
					if (++notifications > 20) throw new Error('Recursive SNMP change notifications');
					// jQuery's namespaced trigger also dispatches the DOM0 onchange.
					context.setSNMP();
					return this;
				},
			};
		},
	};
	vm.createContext(context);
	vm.runInContext(['storeSNMPSecurity', 'setSNMPSecurity', 'setSNMP'].map(extract).join('\n'), context);
	return { context, values, rows, render() { notifications = 0; context.setSNMP(); return notifications; },
		failRefresh() { throwNext = true; } };
}

test('SNMP rendering bounds nested DOM0 handlers dispatched by Select2 notifications', () => {
	const f = fixture();
	assert.equal(f.render(), 4);
	assert.equal(f.context.snmp_security_initialized, true);
	assert.equal(f.rows.get('row_snmp_context'), true);
	assert.equal(f.rows.get('row_snmp_community'), false);
	assert.equal(f.values.get('snmp_password'), 'auth-fixture');
	assert.equal(f.values.get('snmp_priv_passphrase'), 'privacy-fixture');
});

test('SNMP rendering does not leak locals into the global scope', () => {
	const f = fixture();
	f.render();
	assert.equal('selectmenu' in f.context, false);
});

test('SNMP rendering releases its reentrancy guard after a refresh exception', () => {
	const f = fixture();
	f.failRefresh();
	assert.throws(() => f.render(), /fixture refresh failure/);
	assert.doesNotThrow(() => f.render());
	assert.equal(f.rows.get('row_snmp_context'), true);
});

test('SNMP version changes update applicable rows without losing configured credentials', () => {
	const f = fixture();
	f.render();
	for (const version of ['1', '2', '0', '3']) {
		f.values.set('snmp_version', version);
		f.render();
		assert.equal(f.rows.get('row_snmp_community'), version === '1' || version === '2');
		assert.equal(f.rows.get('row_snmp_context'), version === '3');
		for (const [name, value] of [['snmp_password', 'auth-fixture'], ['snmp_priv_passphrase', 'privacy-fixture'],
			['snmp_username', 'user-fixture'], ['snmp_community', 'community-fixture'], ['snmp_context', 'context-fixture']]) {
			assert.equal(f.values.get(name), value);
		}
	}
});

test('SNMP security-level changes finish rendering appropriate authentication and privacy fields', () => {
	const f = fixture();
	f.render();
	f.values.set('snmp_security_level', 'authNoPriv');
	f.render();
	assert.equal(f.values.get('snmp_auth_protocol'), 'SHA');
	assert.equal(f.values.get('snmp_password'), 'auth-fixture');
	assert.equal(f.values.get('snmp_priv_protocol'), '[None]');
	assert.equal(f.rows.get('row_snmp_password'), true);
	assert.equal(f.rows.get('row_snmp_priv_passphrase'), false);
	f.values.set('snmp_security_level', 'noAuthNoPriv');
	f.render();
	assert.equal(f.values.get('snmp_auth_protocol'), '[None]');
	assert.equal(f.rows.get('row_snmp_password'), false);
	assert.equal(f.rows.get('row_snmp_priv_passphrase'), false);
	assert.equal(f.rows.get('row_snmp_username'), true);
	assert.equal(f.values.get('snmp_username'), 'user-fixture');
});
