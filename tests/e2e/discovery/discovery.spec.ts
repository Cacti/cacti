/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

// Cancellation and authorization check the configuration created by the first scenario.
test.describe.configure({ mode: 'serial' });

const root = path.resolve(__dirname, '../../..');
const project = process.env.DISCOVERY_COMPOSE_PROJECT;
if (!project || !/^cacti-discovery-[a-z0-9-]+$/.test(project)) {
	throw new Error('Use the task-owned discovery Docker runner');
}
const compose = ['compose', '-p', project, '-f', 'tests/e2e/docker-compose.yml', '-f', 'tests/e2e/discovery/compose.yml'];
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
	await page.locator('form#auth button[type=submit]').click();
	await expect(page.locator('#login_username')).toHaveCount(0);
}
async function check(page: Page, id: string) {
	if (!await page.locator(`#${id}`).isChecked()) {
		const toggle = page.locator('label.checkboxSwitch').filter({ has: page.locator(`#${id}`) });
		if (await toggle.count()) {
			await toggle.click();
		} else if (await page.locator(`#${id}`).isVisible()) {
			await page.locator(`#${id}`).check();
		} else {
			await page.locator(`label[for="${id}"]`).click();
		}
	}
	await expect(page.locator(`#${id}`)).toBeChecked();
}
async function password(page: Page, field: string, value: string) {
	for (const id of [field, `${field}_confirm`]) {
		await page.locator(`#${id}`).click();
		await page.locator(`#${id}`).fill(value);
	}
}
async function save(page: Page) {
	await page.locator('#submit').click();
	await page.waitForLoadState('networkidle');
	await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught (?:TypeError|Error|Exception)/);
}
async function scan(dryrun: boolean) {
	const configured = snapshot().networks;
	expect(configured).toHaveLength(1);
	expect(configured[0].name).toBe('Discovery isolated network');
	for (const target of configured[0].subnet_range.split(',')) {
		expect(target.trim()).toMatch(/^172\.30\.25[23]\.(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])$/);
	}
	const before = configured[0].last_started;
	cli(['poller_automation.php', '-M', '--force', ...(dryrun ? ['--dryrun'] : [])]);
	await expect.poll(() => {
		const state = snapshot();
		return state.processes.length === 0 && state.networks[0].last_started !== before && Number(state.networks[0].last_runtime) > 0;
	}, { timeout: 90_000, intervals: [1000] }).toBe(true);
	return snapshot();
}

test('real UI config drives master/child SNMP discovery, dryrun, selective graphs and repeat scans', async ({ page }) => {
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
	await page.locator('#snmp_version').selectOption('3');
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
	await page.locator('#description_pattern').fill('|sysName|');
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
	await page.locator('#operator').selectOption({ label: 'Matches (=)' });
	await page.locator('#pattern').fill(profile.name);
	await save(page);
	expect(snapshot().match_criteria).toHaveLength(1);
	await page.goto(`/automation_graph_rules.php?action=item_edit&id=${graphRule.id}&rule_type=2`);
	await page.locator('#field').selectOption('ifDescr');
	await page.locator('#operator').selectOption({ label: 'Matches (=)' });
	await page.locator('#pattern').fill('eth0');
	await save(page);
	expect(snapshot().graph_criteria).toHaveLength(1);
	await page.goto(`/automation_templates.php?action=edit&id=${rule.id}`);
	await page.locator('#graph_rule').selectOption(String(graphRule.id));
	await page.locator('#add_agr').click();
	await page.waitForLoadState('networkidle');

	await page.goto('/automation_networks.php?action=edit');
	await page.locator('#name').fill('Discovery isolated network');
	await page.locator('#subnet_range').fill('172.30.253.10,172.30.253.11,172.30.253.12,172.30.253.13,172.30.253.14,172.30.252.255');
	// Include comma-separated whitespace and overlapping included/excluded input.
	await page.locator('#ignore_ips').fill('172.30.253.13, 172.30.253.14');
	await page.locator('#snmp_id').selectOption(String(credentials.id));
	await page.locator('#ping_method').selectOption({ label: 'SNMP Get' });
	await page.locator('#threads').selectOption('2');
	await check(page, 'enabled');
	await check(page, 'add_to_cacti');
	await save(page);
	expect(Number(snapshot().networks[0].total_ips)).toBe(6);

	const baseline = snapshot();
	const dry = await scan(true);
	expect(dry.hosts).toEqual(baseline.hosts);
	expect(dry.graphs).toEqual(baseline.graphs);
	expect(dry.data_sources).toBe(baseline.data_sources);
	expect(dry.devices.map((row: any) => row.ip)).toEqual(['172.30.252.255', '172.30.253.10', '172.30.253.11', '172.30.253.12']);
	const live = await scan(false);
	const hosts = live.hosts.filter((row: any) => row.hostname.startsWith('172.30.25'));
	expect(hosts.map((row: any) => row.hostname).sort()).toEqual(['172.30.252.255', '172.30.253.10', '172.30.253.11']);
	expect(Number(hosts.find((row: any) => row.hostname === '172.30.252.255').snmp_version)).toBe(3);
	const full = hosts.find((row: any) => row.hostname === '172.30.253.10');
	const partial = hosts.find((row: any) => row.hostname === '172.30.253.11');
	expect(live.graphs.filter((row: any) => row.host_id === full.id)).toHaveLength(1);
	expect(live.graphs.filter((row: any) => row.host_id === partial.id)).toHaveLength(0);
	const v3 = hosts.find((row: any) => row.hostname === '172.30.252.255');
	expect(live.graphs.filter((row: any) => row.host_id === v3.id)).toHaveLength(1);
	expect(live.data_sources).toBeGreaterThan(baseline.data_sources);
	expect(Number(live.devices.find((row: any) => row.ip === '172.30.253.12').host_id)).toBe(0);
	const repeated = await scan(false);
	expect(repeated.hosts).toEqual(live.hosts);
	expect(repeated.graphs).toEqual(live.graphs);
	expect(repeated.data_sources).toBe(live.data_sources);
	await page.goto('/automation_devices.php');
	await expect(page.getByText('discovery-known', { exact: false }).first()).toBeVisible();
	// An unmatched device remains actionable through the normal manual fallback.
	const unknown = repeated.devices.find((row: any) => row.ip === '172.30.253.12');
	await check(page, `chk_${unknown.id}`);
	await page.locator('[name="drp_action"]').selectOption('1');
	await page.getByRole('button', { name: 'Go', exact: true }).last().click();
	await page.locator('#host_template').selectOption(String(profile.id));
	await page.locator('#availability_method').selectOption('2');
	await page.getByRole('button', { name: 'Continue', exact: true }).click();
	await page.waitForLoadState('networkidle');
	const manuallyAdded = snapshot().hosts.filter((row: any) => row.hostname === unknown.ip);
	expect(manuallyAdded).toHaveLength(1);
	expect(Number(manuallyAdded[0].host_template_id)).toBe(Number(profile.id));
});

test('UI cancellation stops a running SNMP discovery before later addresses are added', async ({ page }) => {
	await login(page);
	const baseline = snapshot();
	const network = baseline.networks[0];
	const credential = baseline.credential_items[0];
	await page.goto(`/automation_snmp.php?action=item_edit&id=${credential.snmp_id}&item_id=${credential.id}`);
	await page.locator('#snmp_timeout').fill('10000');
	await save(page);
	await page.goto(`/automation_networks.php?action=edit&id=${network.id}`);
	await page.locator('#subnet_range').fill('172.30.253.1,172.30.253.13');
	await page.locator('#ignore_ips').fill('');
	await page.locator('#threads').selectOption('1');
	await save(page);
	async function action(label: string) {
		await page.goto('/automation_networks.php');
		await check(page, `chk_${network.id}`);
		await page.locator('[name="drp_action"]').selectOption({ label });
		await page.getByRole('button', { name: 'Go', exact: true }).last().click();
		await page.getByRole('button', { name: 'Continue', exact: true }).click();
		await page.waitForLoadState('networkidle');
	}
	await action('Discover Now');
	await expect.poll(() => snapshot().processes.some((row: any) => row.task !== 'tmaster' && row.status === 'running'), { timeout: 15_000 }).toBe(true);
	await action('Cancel Discovery');
	await expect.poll(() => snapshot().processes.length, { timeout: 45_000, intervals: [500] }).toBe(0);
	// Wait beyond one unanswered request, proving no detached child adds the next agent.
	await page.waitForTimeout(12_000);
	expect(snapshot().hosts).toEqual(baseline.hosts);
	expect(snapshot().graphs).toEqual(baseline.graphs);
	expect(snapshot().data_sources).toBe(baseline.data_sources);
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
		action: 'save', id: '0', name: 'Forbidden fixture network', subnet_range: '172.30.253.10',
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
