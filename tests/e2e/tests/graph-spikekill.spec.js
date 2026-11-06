/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);
const htmlSource = fs.readFileSync(path.join(root, 'lib/html.php'), 'utf8');
const methods = ['stddev', 'fill', 'float'];
if (htmlSource.includes("$('.rabsolute')")) methods.push('absolute');
const preferences = [['skmethod', 'method_avg', 'ravgnan', 'avg'], ['skills', 'kills_5', 'rkills', '5'], ['skstddev', 'stddev_2', 'rstddev', '2']];
if (htmlSource.includes("$('.skabsmax')")) preferences.push(['skabsmax', 'absmax_3', 'rabsmax', '3']);
const markup = execFileSync(process.env.CACTI_TEST_PHP || 'php', [path.join(root, 'tests/fixtures/Core/GraphDrilldownMarkup.php'), 'browser'], { encoding: 'utf8' });

function sourceFunction(source, name, next) {
	const start = source.indexOf('function ' + name + '(');
	const end = source.indexOf('\nfunction ' + next, start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	return '\n'.repeat(source.slice(0, start).split('\n').length - 1) + source.slice(start, end);
}

async function setup(page, delay = 0) {
	const requests = [];
	await page.route('http://cacti-fixture/**', async route => {
		const request = route.request();
		const url = new URL(request.url());
		if (url.searchParams.get('action') === 'spikemenu') {
			const graph = url.searchParams.get('local_graph_id');
			requests.push({ graph });
			await new Promise(resolve => setTimeout(resolve, delay));
			await route.fulfill({ contentType: 'text/html', body: `<div class="spikekillParent" style="display:none;position:absolute"><ul class="spikekillMenu">${methods.map(method => ['r', 'd'].map(mode => `<li class="${mode}${method}" data-graph="${graph}"><div>${mode}${method}</div></li>`).join('')).join('')}${preferences.map(([name, id]) => `<li class="${name}" id="${id}"><div>${name}</div></li>`).join('')}</ul></div>` });
		} else if (url.pathname.endsWith('/spikekill.php')) {
			requests.push({ method: request.method(), data: request.postDataJSON() });
			await route.fulfill({ contentType: 'application/json', body: '{"results":"Fixture result"}' });
		} else if (url.searchParams.get('action') === 'spikesave') {
			requests.push({ method: request.method(), data: request.postDataJSON() });
			await route.fulfill({ body: '' });
		} else {
			await route.fulfill({ contentType: 'text/html', body: markup });
		}
	});
	await page.goto('http://cacti-fixture/graph_view.php');
	await page.addStyleTag({ content: '.graphWrapperOuter {margin:20px;width:400px}.graphimage {width:400px;height:80px;background:gray}.drillDown {display:inline-block;width:16px;height:16px}' });
	await page.addStyleTag({ path: path.join(root, 'include/css/graph_drilldown.css') });
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery-ui.js') });
	const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	await page.addScriptTag({ content: sourceFunction(layout, 'initGraphDrilldown', 'initializeGraphs') + '\n//# sourceURL=http://cacti-fixture/include/layout.js' });
	await page.addScriptTag({ content: sourceFunction(layout, 'removeSpikes', 'redrawGraph') + '\n//# sourceURL=http://cacti-fixture/include/layout.js' });
	const php = fs.readFileSync(path.join(root, 'lib/html.php'), 'utf8');
	const start = php.indexOf('\tvar spikeKillOpen = false;', php.indexOf('function html_spikekill_js()'));
	const end = php.indexOf('\t</script>', start);
	expect(start).toBeGreaterThan(-1);
	await page.addScriptTag({ content: '\n'.repeat(php.slice(0, start).split('\n').length - 1) + php.slice(start, end) + '\n//# sourceURL=http://cacti-fixture/lib/html.php' });
	await page.evaluate(() => {
		window.urlPath = '/';
		window.csrfMagicToken = 'fixture-token';
		window.spikeKillResults = 'Results';
		window.closeDateFilters = window.checkForRedirects = window.redrawGraph = () => {};
		window.httpErrors = [];
		window.getPresentHTTPError = data => window.httpErrors.push(data.status);
		initGraphDrilldown();
	});
	await page.waitForTimeout(30);
	return requests;
}

async function open(page, graph) {
	await page.locator(`#g${graph} .graphimage`).hover();
	await page.locator(`#g${graph} .graphDrillDownMenu`).click();
	await page.locator(`#g${graph} .iconLink.spikekill`).click();
}

test('the real SpikeKill action supports keyboard activation and Escape cleanup', async ({ page }) => {
	const requests = await setup(page);
	await page.locator('#g1 .graphDrillDownMenu').focus();
	await page.keyboard.press('Enter');
	for (let index = 0; index < 4; index++) await page.keyboard.press('Tab');
	await expect(page.locator('#g1 .iconLink.spikekill')).toBeFocused();
	await page.keyboard.press('Space');
	await expect(page.locator('#g1 .spikekillParent')).toBeVisible();
	await page.keyboard.press('Escape');
	await expect(page.locator('.spikekillParent')).toHaveCount(0);
	expect(await page.evaluate(() => spikeKillOpen)).toBe(false);
	await expect(page.locator('#g1 .graphDrillDownMenu')).toBeFocused();
	await open(page, 1);
	await expect(page.locator('#g1 .spikekillParent')).toBeVisible();
	expect(requests.filter(request => request.graph === '1')).toHaveLength(2);
});

test('switching graphs cancels pending SpikeKill responses', async ({ page }) => {
	const requests = await setup(page, 300);
	await open(page, 1);
	await open(page, 2);
	await expect(page.locator('#g2 .spikekillParent')).toBeVisible();
	await page.waitForTimeout(350);
	await expect(page.locator('#g1 .spikekillParent')).toHaveCount(0);
	await expect(page.locator('#g2 .rstddev')).toHaveAttribute('data-graph', '2');
	expect(requests.filter(request => request.graph).map(request => request.graph)).toEqual(['1', '2']);
	expect(await page.evaluate(() => httpErrors)).toEqual([]);
});

test('closing while a request is pending does not resurrect the submenu', async ({ page }) => {
	await setup(page, 300);
	await open(page, 1);
	await page.locator('#g1 .graphDrillDownMenu').focus();
	await page.keyboard.press('Escape');
	await page.waitForTimeout(400);
	await expect(page.locator('.spikekillParent')).toHaveCount(0);
	expect(await page.evaluate(() => spikeKillOpen)).toBe(false);
});

for (const method of methods) for (const dryrun of [false, true]) {
	test(`SpikeKill ${method} ${dryrun ? 'dry run' : 'mutation'} sends a CSRF-protected POST for the selected graph`, async ({ page }) => {
		const requests = await setup(page);
		await open(page, 2);
		await page.locator(`#g2 .${dryrun ? 'd' : 'r'}${method}`).click();
		await expect.poll(() => requests.find(request => request.method)).toBeTruthy();
		const request = requests.find(request => request.method);
		expect(request.method).toBe('POST');
		expect(request.data).toMatchObject({ method, local_graph_id: '2', 'outlier-start': '100', 'outlier-end': '200', __csrf_magic: 'fixture-token' });
		expect(request.data.dryrun).toBe(dryrun ? 'true' : undefined);
		await expect(page.locator('.spikekillParent')).toHaveCount(0);
	});
}

for (const [name, , setting, value] of preferences) {
	test(`SpikeKill ${setting} preference changes use CSRF-protected POST`, async ({ page }) => {
		const requests = await setup(page);
		await open(page, 1);
		await page.locator('#g1 .' + name).click();
		await expect.poll(() => requests.find(request => request.method)).toBeTruthy();
		expect(requests.find(request => request.method)).toEqual({ method: 'POST', data: { setting, id: value, __csrf_magic: 'fixture-token' } });
	});
}

test('late responses are ignored even when cancellation does not stop completion', async ({ page }) => {
	await setup(page, 300);
	await open(page, 1);
	await page.evaluate(() => { spikeKillRequest.abort = () => {}; });
	await open(page, 2);
	await expect(page.locator('#g2 .spikekillParent')).toBeVisible();
	await page.waitForTimeout(350);
	await expect(page.locator('#g1 .spikekillParent')).toHaveCount(0);
	await expect(page.locator('#g2 .rstddev')).toHaveAttribute('data-graph', '2');
});

for (const detached of [false, true]) {
	test(`a response for a ${detached ? 'removed' : 'closed'} graph is discarded`, async ({ page }) => {
		await setup(page, 300);
		await open(page, 1);
		await page.locator('#g1').evaluate((element, remove) => {
			if (remove) element.remove();
			else element.querySelector('.graphDrillDownToggle').classList.remove('open');
		}, detached);
		await page.waitForTimeout(400);
		await expect(page.locator('.spikekillParent')).toHaveCount(0);
		expect(await page.evaluate(() => spikeKillAnchor)).toBeNull();
	});
}

test('request failures clear pending state and report the HTTP error', async ({ page }) => {
	await setup(page);
	await page.route('**/*action=spikemenu*', route => route.fulfill({ status: 500, body: 'Fixture failure' }));
	await open(page, 1);
	await expect.poll(() => page.evaluate(() => httpErrors)).toEqual([500]);
	expect(await page.evaluate(() => spikeKillAnchor)).toBeNull();
});


test('the active action toggles the submenu and an outside click closes it', async ({ page }) => {
	await setup(page);
	await open(page, 1);
	await expect(page.locator('#g1 .spikekillParent')).toBeVisible();
	await page.locator('#g1 .iconLink.spikekill').click();
	await expect(page.locator('.spikekillParent')).toHaveCount(0);
	await page.locator('#g1 .iconLink.spikekill').click();
	await expect(page.locator('#g1 .spikekillParent')).toBeVisible();
	await page.locator('#outside').click();
	await expect(page.locator('.spikekillParent')).toHaveCount(0);
});

test('a submenu near the right edge remains on screen and accepts keyboard selection', async ({ page }) => {
	await setup(page);
	await page.locator('#g1').evaluate(element => { element.style.marginLeft = (document.documentElement.clientWidth - 150) + 'px'; });
	await open(page, 1);
	const menu = page.locator('#g1 .spikekillMenu');
	await expect(menu).toBeVisible();
	await expect(menu).toHaveCSS('left', '-180px');
	await menu.focus();
	await page.keyboard.press('Home');
	await page.keyboard.press('Enter');
	await expect(menu.locator('.ui-state-active')).toHaveCount(1);
});
