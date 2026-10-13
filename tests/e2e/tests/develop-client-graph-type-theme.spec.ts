/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');
const layout = readFileSync(resolve(root, 'include/layout.js'), 'utf8');

test('spike redraw requests graph JSON with the selected options', async ({ page }) => {
	await page.setContent('<!DOCTYPE html><select id="columns"><option>1</option></select><input id="thumbnails" type="checkbox" checked>' +
		'<input id="business_hours" type="checkbox" checked><select id="graph_source"><option>0</option></select>' +
		'<select id="graph_order"><option>1</option></select><select id="cf"><option>AVERAGE</option></select><select id="measure"><option>1</option></select>' +
		'<div class="tableRowGraph" style="width:500px"></div><div class="graphDrillDown"></div><div id="wrapper_42" graph_width="500" graph_height="200"></div>');
	await page.addScriptTag({ path: resolve(root, 'include/js/jquery.js') });
	const start = layout.indexOf('function redrawGraph(');
	const end = layout.indexOf('\nfunction refreshGraphs()', start);
	expect(start).toBeGreaterThan(0);
	expect(end).toBeGreaterThan(start);
	await page.addScriptTag({ content: layout.slice(start, end) });
	const url = await page.evaluate(() => {
		const browser = window as any;
		browser.getMainWidth = () => 800;
		browser.closeDateFilters = () => {};
		browser.graph_start = 100;
		browser.graph_end = 200;
		browser.$.getJSON = (url: string) => { browser.redrawUrl = url; return browser.$.Deferred().promise(); };
		browser.redrawGraph(42);
		return browser.redrawUrl;
	});
	const requested = new URL(url, 'http://cacti.test');
	expect(requested.pathname).toBe('/graph_json.php');
	expect(Object.fromEntries(requested.searchParams)).toEqual({ rra_id: '0', local_graph_id: '42', graph_start: '100', graph_end: '200', graph_height: '200', graph_width: '500', graph_nolegend: 'true', graph_source: '0', graph_order: '1', cf: 'AVERAGE', measure: '1', business_hours: 'true' });
});

test('realtime retries HTTP failures and ignores callbacks from older cycles', async ({ page }) => {
	await page.setContent('<!DOCTYPE html><select id="graph_start"><option>60</option></select><select id="ds_step"><option>1</option></select>' +
		'<div id="wrapper_1"><img id="graph_1"></div><div id="wrapper_2"><img id="graph_2"></div>');
	await page.addScriptTag({ path: resolve(root, 'include/js/jquery.js') });
	await page.addScriptTag({ path: resolve(root, 'include/realtime.js') });
	await page.clock.install();
	await page.evaluate(() => {
		const browser = window as any;
		browser.myRefresh = 0;
		browser.refreshMSeconds = 60000;
		browser.urlPath = '/';
		browser.Pace = { ignore: (callback: Function) => callback() };
		browser.httpErrors = 0;
		browser.getPresentHTTPError = () => browser.httpErrors++;
		browser.pending = [];
		browser.$.getJSON = (url: string) => {
			const request = browser.$.Deferred();
			browser.pending.push({ url, request });
			return request.promise();
		};
		browser.realtimeArray[1] = true;
		browser.realtimeGrapher();
		browser.pending[0].request.reject({ status: 503 });
	});
	await page.clock.fastForward(1001);
	expect(await page.evaluate(() => (window as any).pending.length)).toBe(2);
	await page.evaluate(() => {
		const browser = window as any;
		browser.realtimeArray[2] = true;
		browser.realtimeGrapher();
		// An older one-graph cycle is still pending when the two-graph cycle starts.
		browser.pending[1].request.resolve({ local_graph_id: 1, image_format: 'png', data: 'stale' });
	});
	expect(await page.evaluate(() => (window as any).graphsRendered)).toBe(0);
	await expect(page.locator('#graph_1')).not.toHaveAttribute('src', /stale/);
	await page.evaluate(() => {
		const browser = window as any;
		browser.pending[2].request.resolve({ local_graph_id: 1, image_format: 'png', data: 'current' });
		browser.pending[3].request.reject({ status: 503 });
	});
	await expect(page.locator('#graph_1')).toHaveAttribute('src', 'data:image/png;base64,current');
	expect(await page.evaluate(() => (window as any).graphsRendered)).toBe(2);
	await page.clock.fastForward(1001);
	expect(await page.evaluate(() => (window as any).pending.length)).toBe(6);
	expect(await page.evaluate(() => (window as any).httpErrors)).toBe(2);
});

for (const theme of ['modern', 'dark']) {
	test(`${theme} enables graph opacity after Select2 replaces selectmenu`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><select id="graph_type_id"><option value="1">Comment</option>' +
			[4, 5, 6, 7, 8].map(value => `<option value="${value}">Type ${value}</option>`).join('') +
			'</select><select id="alpha" disabled><option value="FF">100%</option><option value="80">50%</option></select>');
		for (const file of ['jquery.js', 'jquery-ui.js', 'select2.js']) {
			await page.addScriptTag({ path: resolve(root, 'include/js', file) });
		}
		const source = readFileSync(resolve(root, `include/themes/${theme}/main.js`), 'utf8');
		const start = source.indexOf("$('#graph_type_id').on('change', function()");
		const end = source.indexOf('\n\t});', start);
		expect(start).toBeGreaterThan(0);
		expect(end).toBeGreaterThan(start);
		await page.addScriptTag({ content: source.slice(start, end + '\n\t});'.length) });
		await page.evaluate(() => {
			const browser = window as any;
			browser.$('#graph_type_id, #alpha').select2();
		});
		for (const type of ['4', '5', '6', '7', '8']) {
			await page.evaluate(type => {
				const browser = window as any;
				browser.$('#alpha').prop('disabled', true);
				browser.$('#graph_type_id').val(type).trigger('change');
			}, type);
			await expect(page.locator('#alpha')).toBeEnabled();
			expect(await page.locator('#alpha').evaluate(element => (window as any).$(element).selectmenu('instance'))).toBeUndefined();
		}
		await page.evaluate(() => {
			const browser = window as any;
			browser.$('#alpha').prop('disabled', true);
			browser.$('#graph_type_id').val('1').trigger('change');
		});
		await expect(page.locator('#alpha')).toBeDisabled();
	});
}
