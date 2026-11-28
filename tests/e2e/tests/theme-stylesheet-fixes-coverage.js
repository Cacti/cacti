/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

// Renders the markup that the repaired theme rules target, so each rule is
// both checked for its computed result and recorded by the changed-CSS-line
// coverage gate. Registered from develop-theme-correctness.spec.ts, which the
// chromium and changed-coverage jobs run with CACTI_THEME_COVERAGE set.

const path = require('node:path');
const { readFileSync } = require('node:fs');

const root = path.resolve(__dirname, '../../..');

async function installCss(page, file) {
	const url = `http://cacti-theme.test/${file}`;
	await page.route(url, route => route.fulfill({ contentType: 'text/css', body: readFileSync(path.resolve(root, file), 'utf8') }));
	await page.addStyleTag({ url });
}

// Hidden subtrees and pseudo-elements are not styled until something asks
// for them, so resolve every element and its ::before and ::after.
async function resolveStyles(page) {
	await page.evaluate(() => {
		for (const element of document.querySelectorAll('body *')) {
			for (const pseudo of [null, '::before', '::after']) void getComputedStyle(element, pseudo).display;
		}
	});
}

async function render(page, html, files) {
	await page.setContent(`<!DOCTYPE html><html><body>${html}</body></html>`);
	for (const file of files) await installCss(page, file);
	await resolveStyles(page);
}

const style = (page, selector, property, pseudo = null) =>
	page.locator(selector).first().evaluate((element, [property, pseudo]) => getComputedStyle(element, pseudo)[property], [property, pseudo]);

// jquery.multiselect.js 3.x markup, including the optional listbox, column,
// collapsible group, no-wrap and width-measuring states.
const multiselectMarkup =
	'<button type="button" id="ms-nowrap" class="ui-multiselect ui-multiselect-nowrap ui-widget ui-state-default ui-corner-all">' +
	'<span class="ui-multiselect-open ui-icon ui-icon-triangle-1-s"></span><span>2 Selected</span></button>' +
	'<div id="ms-menu" class="ui-multiselect-menu ui-multiselect-listbox ui-widget ui-widget-content ui-corner-all" style="display:block">' +
	'<div class="ui-multiselect-header ui-widget-header ui-corner-all ui-helper-clearfix"><ul class="ui-helper-reset">' +
	'<li><a id="ms-all" class="ui-multiselect-all" href="#all"><span class="ui-icon ui-icon-check"></span><span>Check all</span></a></li>' +
	'<li class="ui-multiselect-close"><a href="#close"><span class="ui-icon ui-icon-circle-close"></span></a></li></ul></div>' +
	'<ul class="ui-multiselect-checkboxes ui-multiselect-nowrap ui-helper-reset">' +
	'<li><label class="ui-corner-all"><input type="checkbox"><img alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACw="><span>Plain</span></label></li>' +
	'<li class="ui-multiselect-optgroup ui-multiselect-columns"><a class="ui-multiselect-grouplabel ui-multiselect-selectable">Protocols</a><ul>' +
	'<li><label class="ui-corner-all"><input type="checkbox"><span>TCP</span></label></li></ul></li>' +
	'<li class="ui-multiselect-optgroup ui-multiselect-collapsed"><button type="button" class="ui-multiselect-collapser">+</button><ul>' +
	'<li><label class="ui-corner-all"><input type="checkbox"><span>ICMP</span></label></li></ul></li></ul></div>' +
	'<div class="ui-multiselect-menu ui-multiselect-single ui-multiselect-measure ui-multiselect-resize ui-widget ui-widget-content" style="display:block">' +
	'<div class="ui-multiselect-header"></div>' +
	'<ul class="ui-multiselect-checkboxes ui-helper-reset"><li><label id="ms-single" class="ui-corner-all"><input id="ms-radio" type="radio"><span>Last Hour</span></label></li></ul></div>';

function registerThemeStylesheetFixTests(test, expect) {
	for (const theme of ['cacti', 'midwinter', 'raspberry']) {
		test(`${theme} jstree stylesheet hides filtered nodes`, async ({ page }) => {
			await render(page, '<ul><li class="jstree-node jstree-hidden">Filtered</li><li class="jstree-node">Shown</li></ul><span class="jstree-hidden">Hidden</span>',
				[`include/themes/${theme}/default/style.css`]);
			expect(await style(page, 'li.jstree-hidden', 'display')).toBe('none');
			expect(await style(page, 'span.jstree-hidden', 'display')).toBe('none');
		});
	}

	for (const theme of ['cacti', 'raspberry']) {
		test(`${theme} multiselect stylesheet styles the 3.x widget states`, async ({ page }) => {
			await render(page, multiselectMarkup, [`include/themes/${theme}/jquery.multiselect.css`]);
			await page.locator('#ms-all').hover();
			expect(await style(page, '#ms-all', 'textDecorationLine')).toBe('underline');
			expect(await style(page, '.ui-multiselect-open', 'cssFloat')).toBe('right');
			expect(await style(page, '#ms-menu', 'position')).toBe('relative');
			expect(await style(page, '.ui-multiselect-close', 'cssFloat')).toBe('right');
			expect(await style(page, '.ui-multiselect-checkboxes img', 'verticalAlign')).toBe('middle');
			expect(await style(page, '.ui-multiselect-grouplabel', 'cursor')).toBe('pointer');
			expect(await style(page, '.ui-multiselect-columns', 'display')).toBe('inline-block');
			expect(await style(page, '.ui-multiselect-collapser', 'cssFloat')).toBe('left');
			expect(await style(page, '.ui-multiselect-collapsed > ul', 'display')).toBe('none');
			expect(await style(page, '#ms-nowrap', 'whiteSpace')).toBe('nowrap');
			// The first span floats, which makes it a block.
			expect(await style(page, '#ms-nowrap > span:last-child', 'display')).toBe('inline-block');
			expect(await style(page, '.ui-multiselect-nowrap li', 'whiteSpace')).toBe('nowrap');
			expect(await style(page, '#ms-radio', 'left')).toBe('-9999px');
			expect(await style(page, '#ms-single', 'textIndent')).toBe('0px');
			expect(await style(page, '.ui-multiselect-measure > .ui-multiselect-checkboxes', 'overflowY')).toBe('scroll');
			expect(await style(page, '.ui-multiselect-resize', 'borderTopStyle')).toBe('dotted');
		});
	}

	for (const theme of ['cacti', 'raspberry']) {
		test(`${theme} main stylesheet repairs layout helpers, menus and header colors`, async ({ page }) => {
			await render(page,
				'<div class="flexContainer"><div class="flexChild wrap">Graph</div></div>' +
				'<div class="spikeKillMenuItem">Remove spikes</div><div class="formHeader">Header</div>' +
				'<table><tr class="tableHeader"><th>Device</th></tr><tr class="templateHeader"><td>Template</td></tr></table>' +
				'<div class="tabs"><nav><a class="selected" href="#tab">Tab</a></nav></div>' +
				'<ul class="submenuoptions"><li><a id="submenu" href="#sub">Sub</a></li></ul>' +
				'<ul class="menuoptions"><li><a id="menu" href="#menu">Menu</a></li></ul>' +
				'<div class="cactiPageBottom"></div><div class="cactiAuthLogo"></div><div class="cactiInstallLoaderSpinnerTheme"></div>' +
				'<div class="cactiNavBarTop"></div><a class="deleteMarker" href="#delete">x</a><span class="deleteMarkerDisabled">x</span>' +
				'<label><input type="checkbox" checked><span class="radioSlider"></span></label>',
				[`include/themes/${theme}/main.css`]);
			for (const link of ['#submenu', '#menu']) {
				await page.locator(link).hover();
				expect(await style(page, link, 'display')).toBe('block');
			}
			expect(await style(page, '.flexContainer', 'display')).toBe('flex');
			expect(await style(page, '.flexChild', 'whiteSpace')).toBe('normal');
			expect(await style(page, '.spikeKillMenuItem', 'width')).not.toBe('auto');
			if (theme === 'raspberry') {
				expect(await style(page, '.cactiAuthLogo', 'display')).toBe('none');
				expect(await style(page, '.cactiPageBottom', 'backgroundColor')).toBe('rgba(119, 0, 119, 0.7)');
				await expect(page.locator('input:checked + .radioSlider')).toHaveCSS('background-color', 'rgba(119, 0, 119, 0.9)');
			} else {
				expect(await style(page, '.formHeader', 'backgroundColor')).toBe('rgb(0, 119, 0)');
				expect(await style(page, '.tableHeader', 'backgroundColor')).toBe('rgb(0, 119, 0)');
				expect(await style(page, '.tabs nav a.selected', 'backgroundColor')).toBe('rgb(0, 119, 0)');
			}
		});
	}

	test('deepness main stylesheet defines the shared layout and confirmation rules', async ({ page }) => {
		await render(page,
			'<div class="graphPage"><div>Graph</div></div>' +
			// Outside the flex container, which would blockify the table cells.
			'<div>' + [1, 2, 3, 4, 5, 6].map(columns => `<div class="cols${columns}">Graph</div>`).join('') + '</div>' +
			'<pre class="prewrap">Log line</pre>' +
			'<div class="settingsFilter"><div>Filter</div></div><div class="pingRow">Ping</div>' +
			'<form class="confirm_actions"><div class="formColumnLeft"><label>Action</label></div><div class="formColumnRight">Value</div></form>' +
			'<table><tr class="formConfirmRow"><td><input class="formConfirmRadio" type="radio"></td><td>Device</td></tr></table>' +
			'<div class="ui-selectmenu-search"><input></div>' +
			'<table><tr class="tableHeaderGraph"><th><span>Graph</span></th></tr></table>',
			['include/themes/deepness/main.css']);
		expect(await style(page, '.graphPage', 'display')).toBe('flex');
		for (const [columns, width] of [[1, '100%'], [2, '50%'], [3, '33%'], [4, '25%'], [5, '20%'], [6, '16.6%']]) {
			expect(await page.locator(`.cols${columns}`).evaluate(element => getComputedStyle(element).display)).toBe('table-cell');
			expect(await page.evaluate(([columns]) => {
				for (const sheet of Array.from(document.styleSheets)) {
					for (const rule of Array.from(sheet.cssRules)) if (rule.selectorText === `.cols${columns}`) return rule.style.width;
				}
				return '';
			}, [columns])).toBe(width);
		}
		expect(await style(page, '.prewrap', 'whiteSpace')).toBe('pre-wrap');
		expect(await style(page, '.settingsFilter', 'display')).toBe('table');
		expect(await style(page, '.settingsFilter div', 'display')).toBe('table-cell');
		expect(await style(page, '.pingRow', 'display')).toBe('table-cell');
		expect(await style(page, '.confirm_actions .formColumnLeft', 'display')).toBe('table-cell');
		expect(await style(page, '.confirm_actions .formColumnRight', 'display')).toBe('table-cell');
		expect(await style(page, '.formConfirmRadio', 'width')).toBe('10px');
		expect(await style(page, '.formConfirmRow td:last-child', 'paddingLeft')).toBe('8px');
		expect(await style(page, '.ui-selectmenu-search input', 'width')).not.toBe('auto');
		expect(await style(page, '.tableHeaderGraph span', 'paddingTop')).toBe('3px');
	});

	test('deepness zoom stylesheet styles the current zoom markup', async ({ page }) => {
		await render(page,
			'<div id="zoom-container" data-active-element="graph" style="width:400px;height:200px">' +
			'<div id="zoom-box" style="width:400px;height:200px"></div>' +
			'<div class="zoom-crosshair x-axis"></div><div class="zoom-crosshair y-axis"></div>' +
			'<div id="zoom-area"></div><div class="zoom-area-excluded"></div>' +
			'<div class="zoom-marker"><span class="zoom-marker-tooltip relative-right" id="marker-1"><span class="zoom-marker-tooltip-value">12:00</span></span></div>' +
			'<div class="zoom-marker"><span class="zoom-marker-tooltip" id="marker-2">12:05</span></div></div>' +
			'<div class="zoom-menu"><ul><li class="first_li zoom-menu-hover zoom-menu-highlight"><span><i class="ui-icon"></i><i class="ui-icon-right"></i><i class="ui-icon-empty"></i>Zoom</span>' +
			'<div class="inner_li"><span class="fill_title">Title</span></div></li><li class="sep_li"></li></ul></div>' +
			'<div class="zoom-hidden"></div>' +
			'<div class="zoom-livedata"><div class="zoom-livedata-header">Live</div><div class="zoom-livedata-item"><span class="zoom-livedata-color"></span>' +
			'<span class="zoom-livedata-title">Traffic</span><span class="zoom-livedata-value">42</span><span class="zoom-livedata-unit">b</span></div></div>',
			['include/themes/deepness/jquery.zoom.css']);
		await page.locator('#zoom-box').hover();
		await resolveStyles(page);
		expect(await style(page, '.zoom-crosshair', 'visibility')).toBe('visible');
		expect(await style(page, '.zoom-crosshair.x-axis', 'borderTopStyle')).toBe('dashed');
		expect(await style(page, '.zoom-crosshair.y-axis', 'borderLeftStyle')).toBe('dashed');
		expect(await style(page, '#zoom-container', 'pointerEvents')).toBe('none');
		expect(await style(page, '#zoom-box', 'pointerEvents')).toBe('all');
		expect(await style(page, '.zoom-marker', 'fontFamily', '::before')).toMatch(/sans-serif$/);
		expect(await style(page, '#marker-1', 'transform')).toBe('none');
		expect(await style(page, '#marker-1', 'display', '::after')).toBe('none');
		expect(await style(page, '#marker-2', 'bottom')).toBe('8px');
		expect(await style(page, '.zoom-livedata-item', 'display')).toBe('flex');
		expect(await style(page, '.zoom-livedata-value', 'fontFamily')).toMatch(/monospace$/);
	});

	test('midwinter date range picker uses the defined regular font size', async ({ page }) => {
		await page.setContent('<!DOCTYPE html><html><body></body></html>');
		await page.evaluate(() => document.documentElement.style.setProperty('--fs-regular', '17px'));
		await page.evaluate(() => {
			document.body.innerHTML = '<div class="daterangepicker" style="display:block"><div class="ranges"><ul><li>Today</li></ul></div>' +
				'<div class="drp-calendar"><table class="calendar-table"><tr><th>Mo</th><td>1</td></tr></table>' +
				'<select class="monthselect"></select><select class="yearselect"></select><select class="hourselect"></select></div>' +
				'<div class="drp-buttons"><span class="drp-selected">Range</span><button class="btn">Apply</button></div></div>';
		});
		await installCss(page, 'include/themes/midwinter/css/media/daterangepicker.css');
		await resolveStyles(page);
		for (const selector of ['.calendar-table td', '.monthselect', '.hourselect', '.drp-selected', '.btn', '.ranges li']) {
			expect(await style(page, `.daterangepicker ${selector}`, 'fontSize'), selector).toBe('17px');
		}
	});

	for (const theme of ['paper-plane', 'sunrise']) {
		test(`${theme} spike menu items span their menu`, async ({ page }) => {
			await render(page, '<div style="width:200px"><div class="spikeKillMenuItem">Remove spikes</div></div>', [`include/themes/${theme}/main.css`]);
			expect(await style(page, '.spikeKillMenuItem', 'width')).toBe('200px');
		});
	}

	for (const theme of ['cacti', 'paper-plane', 'sunrise']) {
		test(`${theme} tree toggles fall back to a generic font`, async ({ page }) => {
			await render(page, '<div class="jstree-default"><ul><li class="jstree-node"><i class="jstree-ocl"></i></li></ul></div>', [`include/themes/${theme}/main.css`]);
			expect(await style(page, '.jstree-ocl', 'fontFamily')).toBe('tabler-icons, sans-serif');
		});
	}

	test('sunrise gradients use the standard syntax', async ({ page }) => {
		await render(page, '<div class="cactiInstallSectionTitle">Install</div>' +
			'<label><input type="checkbox"><span class="checkboxSlider"></span></label>' +
			'<label><input type="radio" checked><span class="radioSlider"></span></label><label><input type="radio"><span class="radioSlider"></span></label>',
			['include/themes/sunrise/main.css']);
		for (const selector of ['body', '.cactiInstallSectionTitle', 'input:checked + .radioSlider', 'input:not(:checked) + .radioSlider']) {
			expect(await style(page, selector, 'backgroundImage'), selector).toMatch(/^radial-gradient\(/);
		}
		expect(await style(page, '.checkboxSlider', 'backgroundImage')).toMatch(/^linear-gradient\(to right,/);
	});

	test('paw hides the authentication logo', async ({ page }) => {
		await render(page, '<div class="cactiAuthLogo"></div>', ['include/themes/paw/main.css']);
		expect(await style(page, '.cactiAuthLogo', 'display')).toBe('none');
	});
}

module.exports = { registerThemeStylesheetFixTests };
