/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Page } from '@playwright/test';
import { mkdirSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { installThemeCss, collectThemeCoverage } from '../theme-css';
import { textContrast } from '../text-contrast';

collectThemeCoverage();

for (const width of [320, 390, 1280]) {
	for (const title of ['Status', 'Analyze Cacti Log Details [ Warnings/Errors ]', 'A'.repeat(180)]) {
		test(`Cacti titlebar contains wrapped titles and controls at ${width}px: ${title.slice(0, 16)}`, async ({ page }) => {
			await page.setViewportSize({ width, height: 800 });
			await page.setContent('<!DOCTYPE html><div style="width:calc(100vw - 80px)">' +
				'<div class="cactiTableTitleRow"><div class="cactiTableTitle"></div>' +
				'<div class="cactiTableButton"><span>Status</span></div></div>' +
				'<table class="cactiTable"><tr><td>Panel content</td></tr></table></div>');
			await page.locator('.cactiTableTitle').evaluate((element, title) => element.textContent = title, title);
			await installThemeCss(page, 'include/themes/cacti/main.css');
			const boxes = await page.evaluate(() => {
				const box = (selector: string) => {
					const r = document.querySelector(selector)!.getBoundingClientRect();
					return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, height: r.height };
				};
				return { bar: box('.cactiTableTitleRow'), title: box('.cactiTableTitle'), tools: box('.cactiTableButton'), content: box('table') };
			});
			for (const child of [boxes.title, boxes.tools]) {
				expect(child.left).toBeGreaterThanOrEqual(boxes.bar.left);
				expect(child.right).toBeLessThanOrEqual(boxes.bar.right + 1);
				expect(child.top).toBeGreaterThanOrEqual(boxes.bar.top);
				expect(child.bottom).toBeLessThanOrEqual(boxes.bar.bottom + 1);
			}
			expect(boxes.title.right).toBeLessThanOrEqual(boxes.tools.left + 1);
			expect(boxes.content.top).toBeGreaterThanOrEqual(boxes.bar.bottom);
			if (title === 'Status') expect(boxes.bar.height).toBe(25);
		});
	}
}

for (const theme of ['cacti', 'carrot']) {
	for (const row of theme === 'carrot' ? ['', 'odd', 'even', 'selected', 'selectable odd', 'selectable even', 'selectable selected'] : ['', 'odd', 'even']) {
		for (const linkClass of ['', 'linkEditMain']) {
			test(`${theme} ${linkClass || 'ordinary'} links retain text contrast on ${row || 'white'} surfaces`, async ({ page }) => {
				await page.setContent(`<!DOCTYPE html><table><tr class="${row}"><td><a class="${linkClass}" href="#device">Device</a></td></tr></table>`);
				await installThemeCss(page, `include/themes/${theme}/main.css`);
				for (const state of ['rest', 'focus', 'hover', 'active']) {
					const link = page.locator('a');
					if (state === 'focus') await link.focus();
					if (state === 'hover' || state === 'active') await link.hover();
					if (state === 'active') await page.mouse.down();
					const ratio = await link.evaluate(textContrast);
					expect(ratio, state).toBeGreaterThanOrEqual(4.5);
					if (state === 'active') await page.mouse.up();
				}
			});
		}
	}
}

test('Carrot titlebars, navigation selections, rows, and active controls retain text contrast', async ({ page }) => {
	await page.setContent('<!DOCTYPE html>' +
		'<div class="cactiTableTitleRow"><div class="cactiTableTitle">Device inventory</div><div class="cactiTableButton"><span>2 alerts</span></div></div>' +
		'<table><tr class="selected"><td id="selected-text">branch-firewall-03</td></tr></table>' +
		'<ul id="nav"><li><a class="pic" href="#devices">Devices</a></li><li><a class="menu_parent" href="#management">Management</a></li>' +
		'<li><a class="selected" href="#graphs">Graphs</a></li><li><a class="active" href="#console">Console</a></li></ul>' +
		'<div class="ui-widget-content"><button class="ui-button ui-state-active">Active control</button></div>' +
		'<div class="select2-container--default"><ul><li class="select2-results__option--highlighted select2-results__option--selectable">Selected device</li></ul></div>');
	await installThemeCss(page, 'include/themes/carrot/jquery-ui.css');
	await installThemeCss(page, 'include/themes/carrot/main.css');
	for (const selector of ['.cactiTableTitle', '.cactiTableButton span', '#selected-text', '#nav a', 'button', '.select2-results__option--highlighted']) {
		for (const element of await page.locator(selector).all()) expect(await element.evaluate(textContrast), selector).toBeGreaterThanOrEqual(4.5);
	}
	for (const name of ['Devices', 'Management']) {
		const link = page.getByRole('link', { name, exact: true });
		await link.hover();
		expect(await link.evaluate(textContrast), `${name} hover`).toBeGreaterThanOrEqual(4.5);
	}
});

const root = resolve(__dirname, '../../..');

for (const theme of ['cacti', 'carrot']) {
	test(`${theme} seeded device inventory and details work with desktop and narrow dialogs`, async ({ page }, info) => {
		await page.setViewportSize({ width: 1100, height: 380 });
		await page.setContent(readFileSync(resolve(root, 'tests/fixtures/Ui/cacti-theme.html'), 'utf8').replaceAll('Cacti theme', `${theme === 'carrot' ? 'Carrot' : 'Cacti'} theme`));
		await installThemeCss(page, `include/themes/${theme}/jquery-ui.css`);
		await installThemeCss(page, `include/themes/${theme}/main.css`);
		await page.addScriptTag({ url: 'http://cacti-theme.test/include/js/jquery.js' });
		await page.addScriptTag({ url: 'http://cacti-theme.test/include/js/jquery-ui.js' });
		await page.evaluate(() => {
			document.getElementById('open-details')!.addEventListener('click', () => {
				(window as any).jQuery('#details').dialog({
					modal: true, width: Math.min(650, innerWidth - 50), title: 'Poller details',
					buttons: [{ text: 'Close', click() { (window as any).jQuery(this).dialog('destroy'); } }],
				});
			});
		});
		await expect(page.getByRole('table', { name: 'Device inventory' }).locator('tbody tr')).toHaveCount(4);
		await page.getByRole('link', { name: 'edge-router-01' }).click();
		await expect(page).toHaveURL(/#edge-router-01$/);
		const capture = async (name: string) => {
			const path = process.env.UPDATE_THEME_SCREENSHOTS === '1'
				? resolve(root, `docs/validation/images/${name}.png`) : info.outputPath(`${name}.png`);
			mkdirSync(dirname(path), { recursive: true });
			await page.screenshot({ path, fullPage: true });
			await info.attach(name, { path, contentType: 'image/png' });
		};
		await capture(`${theme}-theme-desktop`);
		await page.setViewportSize({ width: 320, height: 680 });
		await page.getByRole('button', { name: 'Open poller details' }).click();
		await expect(page.getByRole('dialog')).toBeVisible();
		await expect(page.getByRole('table', { name: 'Poller alerts' }).getByText(/branch-firewall-03/)).toBeVisible();
		const boxes = await page.locator('#details .cactiTableTitleRow').evaluate(element => {
			const row = element.getBoundingClientRect(), title = element.querySelector('.cactiTableTitle')!.getBoundingClientRect();
			return { rowBottom: row.bottom, titleBottom: title.bottom, rowRight: row.right };
		});
		expect(boxes.titleBottom).toBeLessThanOrEqual(boxes.rowBottom);
		expect(boxes.rowRight).toBeLessThanOrEqual(320);
		await capture(`${theme}-theme-mobile-details`);
		await page.locator('.ui-dialog-buttonpane').getByRole('button', { name: 'Close', exact: true }).click();
		await expect(page.getByRole('dialog')).toHaveCount(0);
	});
}

const files: string[] = JSON.parse(readFileSync(resolve(__dirname, '../../fixtures/Ui/theme-correctness-files.json'), 'utf8'));

for (const file of files) {
	test(`${file} parses repaired declarations in Chromium`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><html><body></body></html>');
		const text = readFileSync(resolve(root, file), 'utf8');
		await installThemeCss(page, file);
		const result = await page.evaluate(text => {
			const sheet = new CSSStyleSheet();
			sheet.replaceSync(text);
			const fonts: string[] = [];
			const selectors: string[] = [];
			const walk = (rules: CSSRuleList) => {
				for (const rule of Array.from(rules)) {
					if (rule instanceof CSSStyleRule) {
						selectors.push(rule.selectorText);
						if (rule.style.fontFamily && !rule.style.fontFamily.includes('var(')) fonts.push(rule.style.fontFamily);
					}
					if ('cssRules' in rule) walk((rule as CSSGroupingRule).cssRules);
				}
			};
			walk(sheet.cssRules);
			return { fonts, selectors };
		}, text);
		expect(result.selectors.length).toBeGreaterThan(0);
		for (const font of result.fonts) expect(font).toMatch(/(?:sans-serif|serif|monospace|system-ui|inherit|unset|initial|revert)$/);
		const declarations = text.replace(/\/\*[\s\S]*?\*\//g, '');
		expect(declarations).not.toContain('#white');
		expect(declarations).not.toContain('word-break-wrap');
		expect(result.selectors.filter(selector => /(?:^|,\s*)navBarNavigation\b/.test(selector))).toEqual([]);
	});
}

// One test per stylesheet: setContent discards the previous sheet and its rule usage.
for (const [file, selector, expected] of [
	['include/themes/deepness/main.css', '.graphSubHeaderColumn', 'rgb(255, 255, 255)'],
	['include/themes/midwinter/css/media/core.css', '.graphSubHeaderColumn', 'rgb(255, 255, 255)'],
	['include/themes/hollyberry/main.css', '.cactiPageHead', 'rgb(0, 161, 11)'],
]) {
	test(`${file} graph headings retain white text and gradients retain fallback colors`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><div class="graphSubHeaderColumn"></div><div class="cactiPageHead"></div>');
		await installThemeCss(page, file);
		expect(await page.locator(selector).evaluate(el => getComputedStyle(el)[el.classList.contains('graphSubHeaderColumn') ? 'color' : 'backgroundColor'])).toBe(expected);
		if (selector === '.cactiPageHead') expect(await page.locator(selector).evaluate(el => getComputedStyle(el).backgroundImage)).toContain('linear-gradient(');
	});
}

for (const [file, html, selector, pseudo, property, expected] of [
	['include/themes/midwinter/css/media/core.css', '<legend>Options</legend>', 'legend', '', 'fontFamily', /monospace$/],
	['include/themes/midwinter/css/media/core.css', '<div class="formFieldDescription">Help</div>', '.formFieldDescription', '', 'cssFloat', /^left$/],
	['include/themes/midwinter/css/pre/helper.css', '<span class="icon ti-icon-star"></span>', 'span', '', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/midwinter/css/media/compact.css', '<div class="mdw-GridContainer-PopOver"><div class="cactiInstallTable"></div></div>', '.cactiInstallTable', '', 'borderTopLeftRadius', /^2px$/],
	['include/themes/midwinter/css/media/compact.css', '<div class="elementsOnTop"><div class="tableNavBarOnTop"><div class="navBarNavigationPrevious"><a href="#prev">Previous</a></div></div></div>', '.navBarNavigationPrevious a', '::before', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/midwinter/css/media/compact.css', '<div class="elementsOnTop"><div class="tableNavBarOnTop"><div class="navBarNavigationNext"><a href="#next">Next</a></div></div></div>', '.navBarNavigationNext a', '::before', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/midwinter/css/media/compact-landscape.css', '<div class="navBreadCrumb"><div>Console</div><div>Devices</div></div>', '.navBreadCrumb div:first-child', '::after', 'fontFamily', /^"Font Awesome 5 Free", sans-serif$/],
	['include/themes/midwinter/css/media/compact-landscape.css', '<div class="navBox-header-dropdown-content"><a href="#item">Item</a></div>', 'a', '', 'textDecorationLine', /^none$/],
	['include/themes/midwinter/css/media/compact-landscape.css', '<div class="navBox-content"><div class="mdw-jstree-wrapper"><ul><li class="jstree-node jstree-closed"><i class="jstree-ocl"></i></li></ul></div></div>', '.jstree-ocl', '::before', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/cacti/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/carrot/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/dark/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/deepness/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/hollyberry/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/modern/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/paper-plane/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/paw/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/raspberry/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/sunrise/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/deepness/main.css', '<span class="filteredValue">match</span>', '.filteredValue', '', 'backgroundColor', /^rgb\(227, 179, 65\)$/],
	['include/themes/deepness/main.css', '<span class="filteredValue">match</span>', '.filteredValue', '', 'color', /^rgb\(11, 14, 19\)$/],
	['include/themes/cacti/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/carrot/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/dark/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/deepness/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/hollyberry/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/modern/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/paper-plane/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/paw/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/raspberry/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/sunrise/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
] as [string, string, string, string, string, RegExp][]) {
	test(`${file} keeps repaired ${selector}${pseudo} ${property}`, async ({ page }) => {
		await page.setContent(`<!DOCTYPE html>${html}`);
		await installThemeCss(page, file);
		expect(await page.locator(selector).evaluate((element, [pseudo, property]) => (getComputedStyle(element, pseudo || null) as any)[property], [pseudo, property])).toMatch(expected);
	});
}

for (const width of [700, 900, 1100]) {
	test(`Midwinter responsive table rules have an explicit root at width ${width}`, async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		await page.setContent('<!DOCTYPE html><html><body><table class="cactiTable"><tr>' + Array.from({ length: 12 }, () => '<th>Column</th>').join('') + '</tr></table></body></html>');
		await installThemeCss(page, 'include/themes/midwinter/css/media/compact-landscape.css');
		const before = await page.locator('th').evaluateAll(els => els.filter(el => getComputedStyle(el).display === 'none').length);
		await page.evaluate(() => document.documentElement.setAttribute('data-auto-table-layout', 'on'));
		const after = await page.locator('th').evaluateAll(els => els.filter(el => getComputedStyle(el).display === 'none').length);
		expect(after).toBeGreaterThan(before);
		await page.evaluate(() => document.documentElement.removeAttribute('data-auto-table-layout'));
		expect(await page.locator('th').evaluateAll(els => els.filter(el => getComputedStyle(el).display === 'none').length)).toBe(before);
	});
}

for (const theme of ['dark', 'deepness', 'modern', 'sunrise']) {
	test(`${theme} spinner retains both keyframe endpoints`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><div class="pace"><div class="pace-activity"></div></div>');
		await installThemeCss(page, `include/themes/${theme}/pace.css`);
		const frames = await page.evaluate(() => {
			const animations = Array.from(document.styleSheets[0].cssRules).filter(rule => rule instanceof CSSKeyframesRule) as CSSKeyframesRule[];
			const animation = animations.find(rule => rule.name === 'pace-spinner' && rule.cssText.startsWith('@keyframes'))!;
			return Array.from(animation.cssRules).map(rule => [(rule as CSSKeyframeRule).keyText, (rule as CSSKeyframeRule).style.transform]);
		});
		expect(frames).toEqual([['0%', 'rotate(0deg)'], ['100%', 'rotate(360deg)']]);
	});
}


test('Cacti context menu fallbacks override package fonts and preserve Midwinter icons', async ({ page }) => {
	for (const theme of ['modern', 'midwinter']) {
		await page.setContent('<!DOCTYPE html><span class="context-menu-icon"></span><span class="context-menu-icon context-menu-icon--fa"></span><span class="context-menu-icon context-menu-icon-ti"></span>');
		await installThemeCss(page, 'include/css/jquery.contextMenu.css');
		if (theme === 'midwinter') await installThemeCss(page, 'include/themes/midwinter/jquery.contextMenu.css');
		await installThemeCss(page, 'include/css/context_menu_overrides.css');
		if (theme === 'midwinter') await installThemeCss(page, 'include/themes/midwinter/context_menu_overrides.css');
		const fonts = await page.locator('span').evaluateAll(els => els.map(el => getComputedStyle(el, '::before').fontFamily));
		expect(fonts[0]).toContain(theme === 'midwinter' ? 'tabler-icons' : 'context-menu-icons');
		expect(fonts[1]).toBe('FontAwesome, sans-serif');
		expect(fonts[2]).toContain('tabler-icons');
		for (const font of fonts) expect(font).toMatch(/sans-serif$/);
	}
});

for (const theme of ['cacti', 'carrot', 'dark', 'hollyberry', 'midwinter', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise']) {
	test(`${theme} visible zoom markers retain icon font fallbacks`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><div class="zoom-marker" style="display:block"><span class="zoom-marker-tooltip" id="marker-1">Start</span></div>');
		await installThemeCss(page, `include/themes/${theme}/jquery.zoom.css`);
		for (const selector of ['.zoom-marker', '.zoom-marker-tooltip']) {
			for (const pseudo of ['::before', '::after']) {
				const font = await page.locator(selector).evaluate((element, pseudo) => getComputedStyle(element, pseudo).fontFamily, pseudo);
				expect(font).toContain('Font Awesome 5 Free');
				expect(font).toMatch(/sans-serif$/);
			}
		}
	});
}

for (const theme of ['carrot', 'dark', 'deepness', 'hollyberry', 'midwinter', 'modern', 'paw', 'raspberry']) {
	test(`${theme} console icons, authentication panel, and graph navigation retain their styles`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><span class="glyph_tab-console"></span><div class="cactiAuthArea">Login</div>' +
			'<div class="jstree-default"><ul><li class="jstree-node"><i class="jstree-ocl"></i></li></ul></div>' +
			'<div class="navBarNavigation"><a href="#next">Next</a></div><div class="graphWrapper">Graph</div><pre class="code">Command</pre>');
		await installThemeCss(page, `include/themes/${theme}/main.css`);
		const icon = await page.locator('.jstree-ocl').evaluate(element => getComputedStyle(element).fontFamily);
		expect(icon).toContain(theme === 'deepness' ? 'Font Awesome 5 Free' : 'tabler-icons');
		expect(icon).toMatch(/sans-serif$/);
		if (theme !== 'midwinter') expect(await page.locator('.cactiAuthArea').evaluate(element => getComputedStyle(element).paddingTop)).toBe('15px');
		if (theme === 'modern') {
			expect(await page.locator('.navBarNavigation a').evaluate(element => getComputedStyle(element).color)).toBe('rgb(255, 255, 255)');
			expect(await page.locator('.code').evaluate(element => getComputedStyle(element).fontFamily)).toMatch(/monospace$/);
		}
	});
}

test('Hollyberry menu and footer gradients retain solid fallback colors', async ({ page }) => {
	await page.setContent('<!DOCTYPE html><div class="submenuoptions">Menu</div><div class="cactiPageBottom">Footer</div>' +
		'<div class="beingGraphed">Visible graph</div><div class="notBeingGraphed">Hidden graph</div>');
	await installThemeCss(page, 'include/themes/hollyberry/main.css');
	for (const selector of ['.submenuoptions', '.cactiPageBottom']) {
		expect(await page.locator(selector).evaluate(element => getComputedStyle(element).backgroundImage)).toContain('linear-gradient(');
		expect(await page.locator(selector).evaluate(element => getComputedStyle(element).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)');
	}
	expect(await page.locator('.beingGraphed').evaluate(element => getComputedStyle(element).color)).toBe('rgba(148, 179, 107, 0.8)');
	expect(await page.locator('.notBeingGraphed').evaluate(element => getComputedStyle(element).color)).toBe('rgb(72, 72, 72)');
});

test('Hollyberry checked radio sliders retain their gradient and solid fallback', async ({ page }) => {
	await page.setContent('<!DOCTYPE html><input type="radio" checked><span class="radioSlider"></span>');
	await installThemeCss(page, 'include/themes/hollyberry/main.css');
	await expect(page.locator('.radioSlider')).toHaveCSS('background-color', 'rgba(0, 161, 11, 0.9)');
	const styles = await page.locator('.radioSlider').evaluate(element => {
		const style = getComputedStyle(element);
		return { color: style.backgroundColor, image: style.backgroundImage };
	});
	expect(styles.color).toBe('rgba(0, 161, 11, 0.9)');
	expect(styles.image).toContain('linear-gradient(');
});

// Mirrors the button and menu markup that include/js/jquery.multiselect.js 3.x builds.
const multiselectMarkup = '<!DOCTYPE html><div style="font-size:20px">' +
	'<button type="button" id="ms-button" class="ui-multiselect ui-widget ui-state-default ui-corner-all"><span class="ui-icon ui-icon-triangle-1-s"></span><span>2 Selected</span></button>' +
	'<button type="button" id="ms-hover" class="ui-multiselect ui-widget ui-state-default ui-corner-all ui-state-hover"><span>2 Selected</span></button>' +
	'<button type="button" id="ms-disabled" class="ui-multiselect ui-widget ui-state-default ui-corner-all ui-state-disabled" disabled><span>None</span></button>' +
	'<div id="ms-menu" class="ui-multiselect-menu ui-widget ui-widget-content ui-corner-all" style="display:block">' +
	'<div class="ui-multiselect-header ui-widget-header ui-corner-all ui-helper-clearfix"><ul class="ui-helper-reset"><li><a class="ui-multiselect-all"><span>Check all</span></a></li></ul></div>' +
	'<ul class="ui-multiselect-checkboxes ui-helper-reset"><li class="ui-multiselect-optgroup"><a class="ui-multiselect-grouplabel">Protocols</a><ul>' +
	'<li><label id="ms-option" class="ui-corner-all ui-state-hover"><input type="checkbox"><span>TCP</span></label></li>' +
	'<li><label id="ms-active" class="ui-corner-all ui-state-active"><input type="checkbox" checked><span>UDP</span></label></li>' +
	'<li><label id="ms-option-disabled" class="ui-corner-all ui-state-disabled"><input type="checkbox" disabled><span>ESP</span></label></li>' +
	'</ul></li></ul></div>' +
	'<div class="ui-multiselect-menu ui-widget ui-widget-content ui-corner-all ui-multiselect-single" style="display:block">' +
	'<ul class="ui-multiselect-checkboxes ui-helper-reset"><li><label id="ms-single" class="ui-corner-all"><input type="radio"><span>Last Hour</span></label></li></ul></div>' +
	'</div>';

async function computedStyles(page: Page, styles: [string, string][]) {
	const result: string[] = [];
	for (const [selector, property] of styles) {
		result.push(await page.locator(selector).first().evaluate((element, property) => (getComputedStyle(element) as any)[property], property));
	}
	return result;
}

test('deepness skins jQuery multiselect buttons and menus with its own palette', async ({ page }) => {
	await page.setContent(multiselectMarkup);
	await installThemeCss(page, 'include/themes/deepness/jquery.multiselect.css');
	await installThemeCss(page, 'include/themes/deepness/main.css');
	// Deepness zeroes the body margin, so #ms-button sits at the origin where
	// headless Chromium on Linux can leave the pointer and apply :hover.
	await page.mouse.move(1000, 600);
	expect(await computedStyles(page, [
		['#ms-button', 'backgroundColor'], ['#ms-button', 'backgroundImage'], ['#ms-button', 'textTransform'],
		['#ms-hover', 'backgroundColor'],
		['#ms-disabled', 'color'],
		['#ms-menu', 'backgroundColor'], ['#ms-menu', 'borderTopColor'],
		['#ms-menu .ui-multiselect-header', 'backgroundColor'], ['#ms-menu .ui-multiselect-header', 'backgroundImage'],
		['#ms-option', 'backgroundColor'], ['#ms-option', 'color'],
		['#ms-active', 'backgroundColor'],
		['#ms-option-disabled', 'color'],
		['.ui-multiselect-grouplabel', 'borderBottomColor'],
	])).toEqual([
		'rgb(17, 22, 29)', 'none', 'none',
		'rgb(22, 28, 37)',
		'rgb(151, 163, 176)',
		'rgb(17, 22, 29)', 'rgb(38, 48, 64)',
		'rgb(13, 18, 23)', 'none',
		'rgb(7, 10, 14)', 'rgb(230, 237, 243)',
		'rgb(22, 28, 37)',
		'rgb(151, 163, 176)',
		'rgb(38, 48, 64)',
	]);
});

for (const theme of ['cacti', 'carrot', 'hollyberry', 'raspberry']) {
	test(`${theme} lays out jQuery multiselect 3.x option groups`, async ({ page }) => {
		await page.setContent(multiselectMarkup);
		await installThemeCss(page, `include/themes/${theme}/jquery.multiselect.css`);
		await installThemeCss(page, `include/themes/${theme}/main.css`);
		expect(await computedStyles(page, [
			['#ms-menu .ui-multiselect-optgroup', 'listStyleType'], ['#ms-menu .ui-multiselect-optgroup', 'fontSize'],
			['#ms-menu .ui-multiselect-optgroup > ul', 'paddingLeft'],
			['#ms-option', 'fontSize'], ['#ms-option', 'paddingLeft'], ['#ms-option', 'textIndent'],
			['#ms-single', 'textIndent'],
			['.ui-multiselect-grouplabel', 'display'], ['.ui-multiselect-grouplabel', 'fontWeight'], ['.ui-multiselect-grouplabel', 'textAlign'], ['.ui-multiselect-grouplabel', 'borderBottomStyle'],
		])).toEqual([
			'none', '20px',
			'3px',
			'18px', '21px', '-20px',
			'0px',
			'block', '700', 'center', 'solid',
		]);
	});
}
