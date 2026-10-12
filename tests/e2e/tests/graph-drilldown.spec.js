/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

test.beforeEach(async ({ page }) => {
	await page.setContent('<style>.graphWrapperOuter {margin:20px;width:200px}.graphimage {width:200px;height:60px;background:gray}.iconLink {display:block;height:25px} </style>' +
		[1, 2].map(id => `<div class="graphWrapperOuter" id="g${id}"><div><div class="graphimage"></div><div class="graphDrillDownToggle"><span class="graphDrillDownMenu" role="button" tabindex="0" aria-label="Graph Actions" aria-expanded="false">Menu</span><div class="iconWrapper"><a class="iconLink" href="#" title="Action tooltip">Action</a><a class="iconLink" href="#">Export</a><a class="iconLink" href="#">SpikeKill</a></div></div></div></div>`).join('') + '<button id="outside">Outside</button>');
	await page.addStyleTag({ path: path.join(root, 'include/css/graph_drilldown.css') });
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	const source = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	const start = source.indexOf('function initGraphDrilldown()');
	const end = source.indexOf('\nfunction initializeGraphs', start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	await page.addScriptTag({ content: '\n'.repeat(source.slice(0, start).split('\n').length - 1) + source.slice(start, end) + '\n//# sourceURL=http://cacti-fixture/include/layout.js' });
	await page.evaluate(() => { window.closedSubmenus = 0; window.spikeKillClose = () => window.closedSubmenus++; initGraphDrilldown(); initGraphDrilldown(); });
});

test('pointer interaction preserves geometry, icon reachability and grace period', async ({ page }) => {
	const menu = page.locator('#g1 .graphDrillDownMenu');
	const toggle = page.locator('#g1 .graphDrillDownToggle');
	await page.locator('#g1 .graphimage').hover();
	await expect(menu).toHaveCSS('opacity', '1');
	const before = await menu.boundingBox();
	await menu.click();
	await expect(menu).toHaveAttribute('aria-expanded', 'true');
	expect(await menu.boundingBox()).toEqual(before);
	await page.locator('#g1 .iconLink').last().hover();
	await page.mouse.move((await menu.boundingBox()).x + 10, (await menu.boundingBox()).y + 10);
	await page.waitForTimeout(600);
	await expect(toggle).toHaveClass(/open/);
	// A tooltip can send a synthetic leave while the pointer remains on an icon.
	// Simulate the native tooltip's contradictory leave/hover state. Chromium's
	// automation hover state is cleared by synthetic leave events, so control
	// only that query while retaining the real event handler and timer.
	await toggle.evaluate(el => {
		window.originalIs = $.fn.is;
		$.fn.is = function (selector) { return selector === ':hover' && this[0] === el ? true : originalIs.call(this, selector); };
	});
	await toggle.evaluate(el => $(el).trigger('mouseleave'));
	await page.waitForTimeout(600);
	await expect(toggle).toHaveClass(/open/);
	await page.evaluate(() => { $.fn.is = originalIs; });
	await page.mouse.move(500, 400);
	await page.waitForTimeout(100);
	await page.locator('#g1 .graphimage').hover();
	await page.waitForTimeout(600);
	await expect(toggle).toHaveClass(/open/);
	await page.mouse.move(500, 400);
	await expect(toggle).not.toHaveClass(/open/);
	await expect(menu).toHaveAttribute('aria-expanded', 'false');
	await expect(page.locator('#g1')).not.toHaveClass(/graphDrillActive/);
	expect(await page.evaluate(() => window.closedSubmenus)).toBeGreaterThan(0);
});

test('activation, switching graphs, outside click and reinitialization', async ({ page }) => {
	const first = page.locator('#g1 .graphDrillDownMenu');
	await first.focus();
	await page.keyboard.press('a');
	await expect(first).toHaveAttribute('aria-expanded', 'false');
	await page.keyboard.press('Space');
	await expect(first).toHaveAttribute('aria-expanded', 'true');
	await page.keyboard.press('Enter');
	await expect(first).toHaveAttribute('aria-expanded', 'false');
	await page.locator('#outside').click();
	await first.click();
	await page.locator('#g2 .graphimage').hover();
	await page.locator('#g2 .graphDrillDownMenu').click();
	await expect(first).toHaveAttribute('aria-expanded', 'false');
	await expect(page.locator('#g2 .graphDrillDownMenu')).toHaveAttribute('aria-expanded', 'true');
	await page.locator('#g2 .iconLink').first().click();
	await expect(page.locator('#g2 .graphDrillDownMenu')).toHaveAttribute('aria-expanded', 'true');
	await page.locator('#outside').click();
	await expect(page.locator('.graphDrillDownToggle.open')).toHaveCount(0);
	await page.evaluate(() => { delete window.spikeKillClose; });
	await first.click();
	await first.click();
	await expect(first).toHaveAttribute('aria-expanded', 'false');
});

test('mixed pointer and keyboard input keeps a focused action visible', async ({ page }) => {
	const menu = page.locator('#g1 .graphDrillDownMenu');
	const action = page.locator('#g1 .iconLink').first();
	await menu.focus();
	await page.keyboard.press('Enter');
	await action.focus();
	await page.locator('#g1 .graphimage').hover();
	await page.mouse.move(500, 400);
	await page.waitForTimeout(650);
	await expect(action).toBeVisible();
	await expect(action).toBeFocused();
	await page.locator('#g2 .graphimage').hover();
	await expect(action).toBeVisible();
	await expect(action).toBeFocused();
	await page.keyboard.press('Escape');
	await expect(menu).toBeFocused();
	await expect(menu).toHaveAttribute('aria-expanded', 'false');
});

test('touch users can see and activate the hamburger without hover', async ({ browser }) => {
	const context = await browser.newContext({ hasTouch: true, isMobile: true });
	const page = await context.newPage();
	await page.setContent('<div class="graphDrillDownToggle"><span class="graphDrillDownMenu" tabindex="0">Menu</span></div>');
	await page.addStyleTag({ path: path.join(root, 'include/css/graph_drilldown.css') });
	await expect(page.locator('.graphDrillDownMenu')).toHaveCSS('opacity', '1');
	await context.close();
});
