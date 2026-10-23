/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

// Later scenarios consume the configuration and discoveries created by the first.
test.describe.configure({ mode: 'serial' });
if (!process.version.startsWith('v22.')) {
	throw new Error(`Discovery browser worker requires Node 22, got ${process.version}`);
}
console.log(`Discovery browser worker Node: ${process.version} (${process.execPath})`);

const root = path.resolve(__dirname, '../../..');
const project = process.env.DISCOVERY_COMPOSE_PROJECT;
if (!project || !/^cacti-discovery-[a-z0-9-]+$/.test(project)) {
	throw new Error('Use the task-owned discovery Docker runner');
}
const compose = ['compose', '-p', project, '-f', 'tests/e2e/discovery/compose.yml'];
function cli(args: string[]) {
	return execFileSync('docker', [...compose, 'exec', '-T', 'cacti', 'php', ...args], { cwd: root, encoding: 'utf8', timeout: 90_000 });
}
function snapshot() {
	return JSON.parse(cli(['tests/e2e/discovery/probe.php', 'status']).trim());
}
async function login(page: Page, user = 'admin', password = 'discovery-admin-fixture') {
	await page.goto('/index.php');
	await page.locator('#login_username').fill(user);
	await page.locator('#login_password').fill(password);
	await page.getByRole('button', { name: 'Login', exact: true }).click();
	await expect(page.locator('#login_username')).toHaveCount(0);
}
async function check(page: Page, id: string) {
	const input = page.locator(`#${id}`);
	if (!(await input.isChecked())) {
		const wrapper = page.locator('label.checkboxSwitch').filter({ has: input });
		await (await wrapper.count() ? wrapper : page.locator(`label[for="${id}"]`)).click();
	}
	await expect(input).toBeChecked();
}
async function password(page: Page, name: string, value: string) {
	for (const id of [name, `${name}_confirm`]) {
		await page.locator(`#${id}`).click();
		await page.locator(`#${id}`).fill(value);
	}
}
async function save(page: Page) {
	await page.locator('#submit').click();
	await page.waitForLoadState('networkidle');
}
async function scan() {
	const stateBefore = snapshot();
	const fixtureIPs = new Set(['172.30.251.10', '172.30.251.11', '172.30.251.12', '172.30.251.13', '172.30.250.255']);
	if (stateBefore.networks.length !== 1 || !stateBefore.networks[0].subnet_range.split(',').every((ip: string) => fixtureIPs.has(ip.trim()))) {
		throw new Error('Refusing discovery outside the isolated fixture network');
	}
	const before = stateBefore.networks[0].last_started;
	cli(['poller_automation.php', '-M', '--force']);
	await expect.poll(() => {
		const state = snapshot();
		return state.processes.length === 0 && state.networks[0].last_started !== before && Number(state.networks[0].last_runtime) > 0;
	}, { timeout: 90_000, intervals: [1000] }).toBe(true);
	return snapshot();
}

test('LTS UI config drives SNMP discovery, selective graphs and repeat scans', async ({ page }) => {
	page.on('pageerror', error => console.error('Discovery browser error:', error.message));
	await login(page);
	await page.goto('/automation_snmp.php?action=edit');
	await page.locator('#name').fill('Discovery fixture credentials');
	await save(page);
	const credentials = snapshot().credentials.find((row: any) => row.name === 'Discovery fixture credentials');
	expect(credentials).toBeTruthy();
	await page.goto(`/automation_snmp.php?action=item_edit&id=${credentials.id}`);
	await page.locator('#snmp_version').selectOption('2');
	await page.locator('#snmp_community').fill('wrong-fixture-community');
	await page.locator('#snmp_port').fill('1161');
	await page.locator('#snmp_timeout').fill('500');
	await page.locator('#snmp_retries').fill('0');
	await save(page);
	await page.goto(`/automation_snmp.php?action=item_edit&id=${credentials.id}`);
	await page.locator('#snmp_version').selectOption('2');
	await page.locator('#snmp_community').fill('discovery-fixture');
	await page.locator('#snmp_port').fill('1161');
	await page.locator('#snmp_timeout').fill('500');
	await page.locator('#snmp_retries').fill('0');
	await save(page);
	await page.goto(`/automation_snmp.php?action=item_edit&id=${credentials.id}`);
	await page.locator('#select2-snmp_version-container').click();
	await page.getByRole('option', { name: 'Version 3', exact: true }).click();
	await page.locator('#snmp_security_level').selectOption('authPriv');
	await page.locator('#snmp_username').fill('discovery');
	await password(page, 'snmp_password', 'discovery-auth-fixture');
	await page.locator('#snmp_auth_protocol').selectOption('SHA');
	await password(page, 'snmp_priv_passphrase', 'discovery-priv-fixture');
	await page.locator('#snmp_priv_protocol').selectOption('AES');
	await page.locator('#snmp_port').fill('1161');
	await page.locator('#snmp_timeout').fill('500');
	await page.locator('#snmp_retries').fill('0');
	await save(page);

	const profile = snapshot().profiles.find((row: any) => /Generic SNMP/.test(row.name));
	await page.goto('/automation_templates.php?action=edit');
	await page.locator('#host_template').selectOption(String(profile.id));
	await page.locator('#sysDescr').fill('Discovery Fixture Switch');
	await page.locator('#availability_method').selectOption('2');
	await save(page);
	const rule = snapshot().rules.find((row: any) => row.sysDescr === 'Discovery Fixture Switch');
	expect(rule).toBeTruthy();

	const query = snapshot().queries.find((row: any) => /Interface/.test(row.name));
	await page.goto('/automation_graph_rules.php?action=edit');
	await page.locator('#name').fill('Discovery fixture eth0 traffic');
	await page.locator('#snmp_query_id').selectOption(String(query.id));
	await page.waitForLoadState('networkidle');
	await save(page);
	const graphRule = snapshot().graph_rules.find((row: any) => row.name === 'Discovery fixture eth0 traffic');
	expect(graphRule).toBeTruthy();
	await page.goto(`/automation_graph_rules.php?action=edit&id=${graphRule.id}`);
	await page.locator('#graph_type_id').selectOption({ label: 'In/Out Bits (64-bit)' });
	await page.waitForLoadState('networkidle');
	await check(page, 'enabled');
	await save(page);
	await page.goto(`/automation_graph_rules.php?action=item_edit&id=${graphRule.id}&rule_type=1`);
	await page.locator('#field').selectOption('ht.name');
	await page.locator('#operator').selectOption({ label: 'matches' });
	await page.locator('#pattern').fill(profile.name);
	await save(page);
	await page.goto(`/automation_graph_rules.php?action=item_edit&id=${graphRule.id}&rule_type=2`);
	await page.locator('#field').selectOption('ifDescr');
	await page.locator('#operator').selectOption({ label: 'matches' });
	await page.locator('#pattern').fill('eth0');
	await save(page);

	await page.goto('/automation_networks.php?action=edit');
	await page.locator('#name').fill('Discovery isolated network');
	await page.locator('#subnet_range').fill('172.30.251.10,172.30.251.11,172.30.251.12,172.30.251.13,172.30.250.255');
	await page.locator('#snmp_id').selectOption(String(credentials.id));
	await page.locator('#ping_method').selectOption({ label: 'SNMP Get' });
	await page.locator('#threads').selectOption('2');
	await check(page, 'enabled');
	await check(page, 'add_to_cacti');
	await save(page);
	expect(Number(snapshot().networks[0].total_ips)).toBe(5);

	const live = await scan();
	const hosts = live.hosts.filter((row: any) => row.hostname.startsWith('172.30.25'));
	// A missing description does not disqualify a rule matched by available fields.
	expect(hosts.map((row: any) => row.hostname).sort()).toEqual(['172.30.250.255', '172.30.251.10', '172.30.251.11', '172.30.251.13']);
	expect(Number(hosts.find((row: any) => row.hostname === '172.30.250.255').snmp_version)).toBe(3);
	const full = hosts.find((row: any) => row.hostname === '172.30.251.10');
	const partial = hosts.find((row: any) => row.hostname === '172.30.251.11');
	expect(live.graphs.filter((row: any) => row.host_id === full.id)).toHaveLength(1);
	expect(live.graphs.filter((row: any) => row.host_id === partial.id)).toHaveLength(0);
	expect(Number(live.devices.find((row: any) => row.ip === '172.30.251.12').known)).toBe(0);
	const missing = hosts.find((row: any) => row.hostname === '172.30.251.13');
	expect(Number(missing.host_template_id)).toBe(Number(profile.id));
	expect(live.devices.some((row: any) => row.ip === '172.30.251.13')).toBe(false);
	const repeated = await scan();
	expect(repeated.hosts).toEqual(live.hosts);
	expect(repeated.graphs).toEqual(live.graphs);
	expect(repeated.data_sources).toBe(live.data_sources);
	await page.goto('/host.php');
	await expect(page.getByText('discovery-known', { exact: true }).first()).toBeVisible();
});

test('unknown discovery remains available for an explicit manual template selection', async ({ page }) => {
	await login(page);
	const before = snapshot();
	const unknown = before.devices.find((row: any) => row.ip === '172.30.251.12');
	expect(Number(unknown.known)).toBe(0);
	await page.goto('/automation_devices.php');
	await page.getByRole('row').filter({ hasText: unknown.ip }).getByRole('checkbox').check();
	await page.locator('[name="drp_action"]').selectOption('1');
	await page.getByRole('button', { name: 'Go', exact: true }).last().click();
	const profile = before.profiles.find((row: any) => /Generic SNMP/.test(row.name));
	await page.locator('#host_template').selectOption(String(profile.id));
	await page.locator('#availability_method').selectOption('2');
	await page.getByRole('button', { name: 'Continue', exact: true }).click();
	await page.waitForLoadState('networkidle');
	const added = snapshot().hosts.filter((row: any) => row.hostname === unknown.ip);
	expect(added).toHaveLength(1);
	expect(Number(added[0].host_template_id)).toBe(Number(profile.id));
});

test('an ordinary account cannot read or mutate discovery configuration', async ({ page }) => {
	const before = snapshot();
	await login(page, 'discovery-reader', 'discovery-reader-fixture');
	await page.goto('/automation_networks.php?action=edit');
	await expect(page.locator('#subnet_range')).toHaveCount(0);
	await expect(page.getByText(/Access Denied|not permitted|Permission Denied/i).first()).toBeVisible();
	// csrfMagicToken is emitted by the ordinary authenticated page layout.
	const csrf = await page.evaluate(() => (window as any).csrfMagicToken);
	expect(typeof csrf).toBe('string');
	expect(csrf.length).toBeGreaterThan(20);
	const deniedSave = await page.request.post('/automation_networks.php', { form: {
		action: 'save', id: '0', name: 'Forbidden fixture network', subnet_range: '172.30.251.10',
		save_component_network: '1', __csrf_magic: csrf,
	} });
	expect(await deniedSave.text()).toMatch(/Access Denied|not permitted|Permission Denied/i);
	const deniedRun = await page.request.post('/automation_networks.php', { form: {
		action: 'actions', drp_action: '4', selected_items: `a:1:{i:0;s:1:"${before.networks[0].id}";}`,
		__csrf_magic: csrf,
	} });
	expect(await deniedRun.text()).toMatch(/Access Denied|not permitted|Permission Denied/i);
	expect(snapshot().networks).toEqual(before.networks);
	expect(snapshot().processes).toEqual(before.processes);
});
