/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const fixture = process.env.CACTI_ACCESSIBILITY_FIXTURE ? JSON.parse(readFileSync(process.env.CACTI_ACCESSIBILITY_FIXTURE, 'utf8')) : { graph_id: 1, aggregate_id: 1 };

async function login(page: Page) {
	await page.goto('/');
	await page.locator('[name="login_username"]').fill('admin');
	await page.locator('[name="login_password"]').fill('admin');
	await page.locator('form#auth [type="submit"]').click();
	await expect(page).toHaveURL(/index\.php/);
	await expect(page.locator('form#auth'), 'Authentication must complete before inspecting page controls').toHaveCount(0);
}

async function labels(page: Page, ids: string[]) {
	for (const id of ids) {
		const control = page.locator(`#${id}`);
		await expect(control).toHaveCount(1);
		expect(await control.evaluate(el => Array.from((el as HTMLInputElement).labels ?? []).map(label => label.textContent?.trim()).filter(Boolean))).not.toEqual([]);
	}
}

for (const [route, ids] of [
	['/plugins.php', ['filter', 'state', 'rows']],
	['/tree.php?action=edit&id=1', ['sfilter', 'hfilter', 'gfilter']],
	['/data_queries.php?action=item_edit&id=1&snmp_query_id=1', ['svg_field', 'svg_text']],
	['/package.php', ['package_export_type']],
	['/utilities.php?action=view_boost_status', ['refresh']],
	['/auth_changepassword.php', ['current', 'password', 'password_confirm']],
	['/color.php?action=import', ['allow_update']],
	['/graph_view.php?action=preview', ['host_id', 'site_id']],
] as [string, string[]][]) {
	test(`${route} connects native controls to their labels`, async ({ page }) => {
		await login(page);
		await page.goto(route);
		await labels(page, ids);
	});
}

test('data source suggested name inputs are labelled for every data template', async ({ page }) => {
	await login(page);
	await page.goto('/data_queries.php?action=item_edit&id=1&snmp_query_id=1');
	const ids = await page.locator('input.svds_field, input.svds_text').evaluateAll(inputs => inputs.map(input => input.id));
	await labels(page, ids);
});

test('password reset identity and reset controls have accessible labels', async ({ page }) => {
	await page.goto('/auth_resetpassword.php?action=formidentity');
	await labels(page, ['identity']);
	await page.goto('/auth_resetpassword.php?action=formreset&hash=sonarbrowserfixture20261010');
	await labels(page, ['password', 'password_confirm']);
});

test('autocomplete decoys are hidden from the user and accessibility tree', async ({ page }) => {
	await login(page);
	await page.goto('/auth_changepassword.php');
	const decoys = page.locator('tr[style="display:none"] input[type="text"], tr[style="display:none"] input[type="password"]');
	await expect(decoys).toHaveCount(2);
	for (const input of await decoys.all()) await expect(input).toBeHidden();
});

for (const route of ['/index.php', '/graph_view.php?action=preview', '/install/install.php', '/graph_realtime.php?local_graph_id=0']) {
	test(`${route} receives exactly one title from the shared PHP header`, async ({ page }) => {
		await login(page);
		await page.goto(route);
		await expect(page.locator('head > title')).toHaveCount(1);
		expect(await page.title()).not.toBe('');
		if (route.startsWith('/install/') || route.startsWith('/graph_realtime')) {
			await expect(page.locator('html')).toHaveAttribute('lang', /.+/);
			expect(await page.evaluate(() => document.doctype?.name.toLowerCase())).toBe('html');
		}
		if (route.startsWith('/graph_realtime')) await labels(page, ['graph_start', 'ds_step', 'size']);
	});
}

for (const [route, minimum] of [
	['/index.php', 1], ['/plugins.php', 1], ['/package.php', 2],
	['/host.php?action=edit&id=1', 2], ['/host_templates.php?action=edit&id=1', 2],
	['/data_queries.php?action=item_edit&id=1&snmp_query_id=1', 3], ['/data_templates.php?action=template_edit&id=1', 1],
	['/tree.php?action=edit&id=1', 3], ['/utilities.php?action=view_boost_status', 1],
] as [string, number][]) {
	test(`${route} marks control and layout tables as presentation`, async ({ page }) => {
		await login(page);
		await page.goto(route);
		expect(await page.locator('table[role="presentation"]').count()).toBeGreaterThanOrEqual(minimum);
	});
}

for (const pageName of ['user_admin', 'user_group_admin']) {
	for (const tab of ['permsg', 'permsd', 'permste', 'permstr']) {
		test(`${pageName} ${tab} exposes policy controls as a layout`, async ({ page }) => {
			await login(page);
			const action = pageName === 'user_admin' ? 'user_edit' : 'edit';
			await page.goto(`/${pageName}.php?action=${action}&id=1&tab=${tab}`);
			expect(await page.locator('table[role="presentation"]').count()).toBeGreaterThan(0);
		});
	}
}

for (const [file, action] of [
	['host_templates.php', 'item_remove_gt_confirm'], ['host_templates.php', 'item_remove_dq_confirm'],
	['automation_templates.php', 'item_remove_agr_confirm'], ['automation_templates.php', 'item_remove_atr_confirm'],
	['automation_templates.php', 'item_remove_ttr_confirm'],
]) {
	test(`${file} ${action} renders one set of confirmation IDs`, async ({ page }) => {
		await login(page);
		await page.goto(`/${file}?action=${action}&id=1&host_template_id=1&template_id=1&rule_id=1&header=false`);
		await expect(page.locator('#cancel')).toHaveCount(1);
		await expect(page.locator('#continue')).toHaveCount(1);
	});
}

test('graph properties fragment keeps the parent data ID unique', async ({ page }) => {
	await login(page);
	await page.goto(`/graph_view.php?action=zoom-preview&local_graph_id=${fixture.graph_id}&rra_id=0`);
	await expect(page.locator('#data')).toHaveCount(1);
	await page.evaluate(() => (window as any).graphProperties());
	await expect(page.getByText('RRDtool Command:', { exact: true })).toBeAttached();
	await expect(page.locator('#data')).toHaveCount(1);
});

test('version and license headings retain their text with current HTML elements', async ({ page }) => {
	await login(page);
	await page.goto('/about.php');
	await expect(page.locator('font, tt')).toHaveCount(0);
	await expect(page.locator('span.textSubHeaderDark')).toContainText('1.3.');
	const license = page.locator('p > span[style*="monospace"]');
	await expect(license).toHaveCount(2);
	expect(await license.first().evaluate(el => getComputedStyle(el).fontFamily)).toContain('monospace');
	await page.goto('/color.php?action=import');
	await expect(page.locator('font')).toHaveCount(0);
	await expect(page.locator('span.textEditTitle')).toHaveCount(2);
});

for (const route of ['/index.php', '/about.php', '/graph_view.php?action=preview']) {
	test(`${route} retains a native page banner and its tabs`, async ({ page }) => {
		await login(page);
		await page.goto(route);
		await expect(page.getByRole('banner')).toHaveCount(1);
		await expect(page.locator('header#cactiPageHead #tabs')).toHaveCount(1);
		await expect(page.locator('a[role="link"], button[role="button"]')).toHaveCount(0);
	});
}

test('about and color pages retain their presentation with CSS attributes', async ({ page }) => {
	await login(page);
	await page.goto('/about.php');
	await expect(page.locator('td.odd.textArea')).toHaveCSS('vertical-align', 'top');
	await expect(page.locator('[valign]')).toHaveCount(0);
	await page.goto('/color.php?action=import');
	expect(await page.locator('td[style="width:50%;"]').evaluateAll(cells => cells.map(cell => (cell as HTMLElement).style.width)))
		.toEqual(['50%', '50%']);
});

test('aggregate preview exposes its graph title as the image alternative', async ({ page }) => {
	await login(page);
	await page.goto(`/aggregate_graphs.php?action=edit&id=${fixture.aggregate_id}&tab=preview`);
	const image = page.locator('#imagewindow > img');
	await expect(image).toHaveCount(1);
	expect(await image.getAttribute('alt')).toBeTruthy();
});

test('installer logo retains a native image name', async ({ page }) => {
	const source = readFileSync(resolve(__dirname, '../../../install/install.php'), 'utf8');
	const logo = source.match(/<div class='cactiInstallLoaderLogo'>.*?<\/div>/)![0];
	await page.setContent('<!DOCTYPE html>' + logo);
	await expect(page.getByRole('img', { name: 'Cacti' })).toHaveCount(1);
});

test('example iframe keeps its name and border using CSS', async ({ page }) => {
	await page.route('https://www.cacti.net/**', route => route.fulfill({ body: '<!DOCTYPE html><title>Fixture</title>' }));
	const source = readFileSync(resolve(__dirname, '../../../include/content/iframe-example.html'), 'utf8');
	await page.setContent(source);
	await expect(page.locator('iframe')).toHaveAttribute('title', 'Cacti website');
	await expect(page.locator('iframe')).toHaveCSS('border-top-width', '0px');
	await expect(page.locator('[frameborder]')).toHaveCount(0);
});
