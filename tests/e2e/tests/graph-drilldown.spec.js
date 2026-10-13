/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

test.beforeEach(async ({ page }) => {
	await page.setContent('<style>.graphWrapperOuter {margin:20px;width:400px}.graphimage {width:400px;height:60px;background:gray}.iconLink {display:block;height:25px} </style>' +
		[1, 2].map(id => `<div class="graphWrapperOuter" id="g${id}"><div><div class="graphimage"></div><div class="graphDrillDownToggle"><span class="graphDrillDownMenu" role="button" tabindex="0" aria-label="Graph Actions" aria-expanded="false">Menu</span><div class="iconWrapper"><a class="iconLink" href="#"><i class="drillDown" title="Action tooltip">A</i></a><br><a class="iconLink" href="#"><i class="drillDown" title="Export data">E</i></a><br><a class="iconLink" href="#"><i class="drillDown" title="SpikeKill spikes">S</i></a><br><a class="iconLink" href="#"><i class="fa fa-eye" title="Add to Dashboard">D</i></a></div></div></div></div>`).join('') + '<button id="outside">Outside</button>');
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

test('opening a strip labels each action glyph with its tooltip text once', async ({ page }) => {
	const menu = page.locator('#g1 .graphDrillDownMenu');
	await page.locator('#g1 .graphimage').hover();
	await menu.click();

	const labels = page.locator('#g1 .iconWrapper .iconLabel');
	await expect(labels).toHaveCount(4);
	await expect(labels.nth(0)).toHaveText('Action tooltip');
	await expect(labels.nth(1)).toHaveText('Export data');
	await expect(labels.nth(2)).toHaveText('SpikeKill spikes');
	// A plugin glyph without the drillDown class is still labelled from its title.
	await expect(labels.nth(3)).toHaveText('Add to Dashboard');

	// The row carries no title, so the visible label is not duplicated by a tooltip.
	const firstLink = page.locator('#g1 .iconWrapper .iconLink').first();
	expect(await firstLink.getAttribute('title')).toBeNull();
	await expect(firstLink.locator('.drillDown')).toHaveAttribute('aria-hidden', 'true');
	// The markup's <br> separators are removed in favour of the flex rows.
	await expect(page.locator('#g1 .iconWrapper br')).toHaveCount(0);

	// Re-opening the same strip must not relabel it (idempotent, single-pass).
	await menu.click();
	await page.locator('#g1 .graphimage').hover();
	await menu.click();
	await expect(page.locator('#g1 .iconWrapper .iconLabel')).toHaveCount(4);
});

test('the open strip adopts the theme palette from inline custom properties', async ({ page }) => {
	// graph_drilldown_icons() emits these custom properties per theme; a light
	// theme must yield a light panel with a dark foreground (the hamburger fix).
	await page.locator('#g1 .graphDrillDownMenu, #g1 .iconWrapper').evaluateAll(els => {
		els.forEach(el => el.setAttribute('style', '--cacti-dd-fg:rgb(36,36,36);--cacti-dd-menu-bg:rgb(248,248,248);--cacti-dd-panel-bg:rgb(255,255,255);--cacti-dd-border:rgba(0,0,0,0.22);--cacti-dd-accent:rgb(65,112,140)'));
	});
	await page.locator('#g1 .graphimage').hover();
	await page.locator('#g1 .graphDrillDownMenu').click();
	const wrapper = page.locator('#g1 .iconWrapper');
	await expect(wrapper).toHaveCSS('background-color', 'rgb(255, 255, 255)');
	await expect(wrapper).toHaveCSS('color', 'rgb(36, 36, 36)');
});

test('touch users can see and activate the hamburger without hover', async ({ browser }) => {
	const context = await browser.newContext({ hasTouch: true, isMobile: true });
	const page = await context.newPage();
	await page.setContent('<div class="graphDrillDownToggle"><span class="graphDrillDownMenu" tabindex="0">Menu</span></div>');
	await page.addStyleTag({ path: path.join(root, 'include/css/graph_drilldown.css') });
	await expect(page.locator('.graphDrillDownMenu')).toHaveCSS('opacity', '1');
	await context.close();
});
