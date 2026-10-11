import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const layoutSource = readFileSync(resolve(__dirname, '../../../include/layout.js'), 'utf8');
const validationSource = layoutSource.slice(layoutSource.indexOf('function formValidate('), layoutSource.indexOf('function toggleFields('));
const serializationSource = layoutSource.slice(layoutSource.indexOf('$.fn.serializeForm ='), layoutSource.indexOf('$.fn.serializeObject ='));
const treeSource = readFileSync(resolve(__dirname, '../../../tree.php'), 'utf8');
const editorSource = treeSource.slice(treeSource.indexOf('$("#ctree").jstree({'), treeSource.indexOf(".on('ready.jstree'"));
const editorForceText = editorSource.match(/'force_text'\s*:\s*(true|false)/)?.[1] === 'true';

async function validationFixture(page: import('@playwright/test').Page) {
	await page.goto('/');
	const asset = await page.locator('script[src*="htmx.js"]').getAttribute('src');
	expect(asset).toBeTruthy();
	const jquery = new URL('jquery.js', new URL(asset!, page.url())).href;
	expect(jquery).toBeTruthy();
	const validation = new URL('jquery.validate/jquery.validate.js', jquery).href;
	await page.route('**/__codeql-validation', route => route.fulfill({
		contentType: 'text/html',
		body: '<!doctype html><html><body><form id="probe"><input id="email" name="email" type="email"></form></body></html>',
	}));
	await page.goto('/__codeql-validation');
	await page.addScriptTag({ url: jquery! });
	await page.addScriptTag({ url: validation });
	// Use the production functions; only their page-level state is isolated.
	await page.addScriptTag({ content: 'var changed=false; var formArray={}; var formRules={"#probe":{}};' + serializationSource + validationSource });
	return jquery;
}

test('table drag initializes existing rows without interpreting their text as HTML (CodeQL 64)', async ({ page }) => {
	const jquery = await validationFixture(page);
	await page.addScriptTag({ url: new URL('jquery.tablednd.js', jquery).href });
	const payload = '<img src=x onerror="window.dependencyExecuted=1">';
	const result = await page.evaluate(payload => {
		const table = document.createElement('table');
		table.id = 'drag-probe';
		const first = table.insertRow();
		first.id = 'first';
		first.insertCell().textContent = payload;
		const second = table.insertRow();
		second.id = 'second';
		second.className = 'nodrag';
		second.insertCell().textContent = 'fixed row';
		document.body.append(table);
		Reflect.get(window, '$')(table).tableDnD();
		return { first: table.rows[0] === first, second: table.rows[1] === second, cursor: first.style.cursor, fixedCursor: second.style.cursor };
	}, payload);
	expect(result).toEqual({ first: true, second: true, cursor: 'move', fixedCursor: '' });
	await expect(page.locator('#first td')).toHaveText(payload);
	await expect(page.locator('#drag-probe img')).toHaveCount(0);
	expect(await page.evaluate(() => Reflect.get(window, 'dependencyExecuted'))).toBeUndefined();
});

test('core colorpicker input is parsed as a color rather than template HTML', async ({ page }) => {
	const jquery = await validationFixture(page);
	await page.addScriptTag({ url: new URL('jquery-ui.js', jquery).href });
	await page.addScriptTag({ url: new URL('jquery.colorpicker.js', jquery).href });
	const payload = '<img src=x onerror="window.dependencyExecuted=1">';
	const colors = await page.evaluate(payload => {
		const input = document.createElement('input');
		input.id = 'hex';
		input.value = payload;
		document.body.append(input);
		const $ = Reflect.get(window, '$');
		// Match color.php: no caller-supplied HTML, size or regional options.
		$(input).colorpicker();
		$(input).colorpicker('open');
		const invalid = $(input).colorpicker('getColor');
		$(input).colorpicker('setColor', '12ab34');
		const valid = $(input).colorpicker('getColor');
		return { invalid, valid };
	}, payload);
	expect(colors.invalid).not.toContain('<');
	expect(colors.valid.toLowerCase()).toBe('12ab34');
	await expect(page.locator('img')).toHaveCount(0);
	expect(await page.evaluate(() => Reflect.get(window, 'dependencyExecuted'))).toBeUndefined();
});

async function treeFixture(page: import('@playwright/test').Page) {
	const jquery = await validationFixture(page);
	await page.addScriptTag({ url: new URL('jstree.js', jquery).href });
	await page.evaluate(forceText => new Promise<void>(resolve => {
		const container = document.createElement('div');
		container.id = 'tree-probe';
		const list = document.createElement('ul');
		for (const id of ['first', 'second', 'third']) {
			const item = document.createElement('li');
			item.id = id;
			item.textContent = '<img src=x onerror="window.dependencyExecuted=1"> ' + id;
			list.append(item);
		}
		container.append(list);
		document.body.append(container);
		Reflect.get(window, '$')(container).one('ready.jstree', () => resolve()).jstree({
			core: { animation: 0, check_callback: true, force_text: forceText },
		});
	}), editorForceText);
}

test('jsTree next/previous navigation wraps DOM siblings rather than label HTML (CodeQL 101-104)', async ({ page }) => {
	await treeFixture(page);
	const result = await page.evaluate(() => {
		const tree = Reflect.get(window, '$')('#tree-probe').jstree(true);
		return {
			next: tree.get_next_dom('first')[0] === document.getElementById('second'),
			nextStrict: tree.get_next_dom('first', true)[0] === document.getElementById('second'),
			previous: tree.get_prev_dom('third')[0] === document.getElementById('second'),
			previousStrict: tree.get_prev_dom('third', true)[0] === document.getElementById('second'),
		};
	});
	expect(result).toEqual({ next: true, nextStrict: true, previous: true, previousStrict: true });
	await expect(page.locator('#tree-probe img')).toHaveCount(0);
	expect(await page.evaluate(() => Reflect.get(window, 'dependencyExecuted'))).toBeUndefined();
});

test('Cacti tree editor keeps markup-like labels as text through rename and outside-click blur (CodeQL 105)', async ({ page }) => {
	expect(editorForceText).toBe(true);
	await treeFixture(page);
	await page.evaluate(() => Reflect.get(window, '$')('#tree-probe').jstree(true).edit('first'));
	const input = page.locator('.jstree-rename-input');
	const payload = '<img src=x onerror="window.dependencyExecuted=1"> renamed';
	await input.fill(payload);
	await page.locator('#email').click();
	await expect(input).toHaveCount(0);
	await expect(page.locator('#first > .jstree-anchor')).toHaveText(payload);
	await expect(page.locator('#tree-probe img')).toHaveCount(0);
	expect(await page.evaluate(() => Reflect.get(window, 'dependencyExecuted'))).toBeUndefined();
});

test('core datetimepicker parses field text separately from grid template options', async ({ page }) => {
	const jquery = await validationFixture(page);
	await page.addScriptTag({ url: new URL('jquery-ui.js', jquery).href });
	await page.addScriptTag({ url: new URL('jquery.timepicker.js', jquery).href });
	const result = await page.evaluate(() => {
		const input = document.createElement('input');
		input.id = 'date-probe';
		input.value = '<img src=x onerror="window.dependencyExecuted=1">';
		document.body.append(input);
		const $ = Reflect.get(window, '$');
		// Match the graph/tree date-filter configuration.
		$(input).datetimepicker({ minuteGrid: 10, stepMinute: 1, showAnim: 'slideDown', numberOfMonths: 1, timeFormat: 'HH:mm', dateFormat: 'yy-mm-dd', showButtonPanel: false });
		$(input).datetimepicker('show');
		const grid = document.querySelector('.ui-timepicker-div') !== null;
		$(input).datetimepicker('setDate', new Date(2026, 9, 10, 12, 30));
		return { grid, value: input.value };
	});
	expect(result).toEqual({ grid: true, value: '2026-10-10 12:30' });
	await expect(page.locator('img')).toHaveCount(0);
	expect(await page.evaluate(() => Reflect.get(window, 'dependencyExecuted'))).toBeUndefined();
});

test('Cacti validation renders title and custom error messages as text on creation and update', async ({ page }) => {
	await validationFixture(page);
	const payload = '<img src=x onerror="window.validationExecuted=1">';
	await page.evaluate(payload => {
		const input = document.getElementById('email') as HTMLInputElement;
		input.title = payload;
		input.value = 'invalid';
		Reflect.get(window, 'formValidate')('#probe', '/unused');
		Reflect.get(window, '$')('#probe').data('validator').element(input);
	}, payload);
	await expect(page.locator('#email-error')).toHaveText(payload);
	await expect(page.locator('#email-error *')).toHaveCount(0);
	await page.evaluate(payload => Reflect.get(window, '$')('#probe').data('validator').showErrors({ email: payload }), payload + ' updated');
	await expect(page.locator('#email-error')).toHaveText(payload + ' updated');
	await expect(page.locator('#email-error *')).toHaveCount(0);
	expect(await page.evaluate(() => Reflect.get(window, 'validationExecuted'))).toBeUndefined();
	await page.locator('#email').fill('valid@example.org');
	await page.evaluate(() => Reflect.get(window, '$')('#probe').data('validator').element(document.getElementById('email')));
	await expect(page.locator('#email-error')).toBeHidden();
});

test('validation lookup and ARIA paths retain DOM elements rather than parsing selector text (CodeQL 91/92/619/620)', async ({ page }) => {
	await validationFixture(page);
	const result = await page.evaluate(() => {
		const $ = Reflect.get(window, '$');
		Reflect.get(window, 'formValidate')('#probe', '/unused');
		const validator = $('#probe').data('validator');
		const input = document.getElementById('email') as HTMLInputElement;
		const name = '\"><img src=x onerror="window.validationExecuted=1">';
		input.name = name;
		validator.groups[name] = 'group';
		const error = document.createElement('label');
		error.id = 'validation-error';
		document.getElementById('probe')!.append(error);
		validator.addErrorAriaDescribedBy(input, $(error), true);
		return {
			found: validator.findByName(name)[0] === input,
			clean: validator.clean(validator.findByName(name)) === input,
			target: validator.validationTargetFor(input) === input,
			aria: input.getAttribute('aria-describedby'),
		};
	});
	expect(result).toEqual({ found: true, clean: true, target: true, aria: 'validation-error' });
	await expect(page.locator('img')).toHaveCount(0);
	expect(await page.evaluate(() => Reflect.get(window, 'validationExecuted'))).toBeUndefined();
});

// These cases exercise generated dependencies in a real browser. Fixtures
// isolate library behavior from CSP so blocked execution cannot hide a bug.
test('HTMX query text remains a history URL rather than restored HTML (CodeQL 622)', async ({ page }) => {
	await page.goto('/');
	const asset = await page.locator('script[src*="htmx.js"]').getAttribute('src');
	expect(asset).toBeTruthy();
	await page.route('**/__codeql-history-origin?*', route => route.fulfill({
		contentType: 'text/html',
		body: `<!doctype html><html><head><meta name="htmx-config" content='{"allowEval":false,"allowScriptTags":false}'>
		<script src="${asset}"></script></head><body>
		<button id="next" hx-get="/__codeql-history-next" hx-target="#target" hx-push-url="true">Next</button>
		<div id="target"><p id="marker">original</p></div></body></html>`,
	}));
	await page.route('**/__codeql-history-next', route => route.fulfill({ contentType: 'text/html', body: '<p id="marker">second</p>' }));
	const payload = '"><img src=x onerror="window.codeqlExecuted=1">';
	await page.goto('/__codeql-history-origin?probe=' + encodeURIComponent(payload));
	await page.locator('#next').click();
	await expect(page).toHaveURL(/__codeql-history-next$/);
	const entry = await page.evaluate(() => JSON.parse(sessionStorage.getItem('htmx-history-cache')!)[0]);
	expect(entry.url).toContain('probe=');
	expect(entry.content).not.toContain('codeqlExecuted');
	await page.goBack();
	await expect(page.locator('#marker')).toHaveText('original');
	expect(await page.evaluate(() => Reflect.get(window, 'codeqlExecuted'))).toBeUndefined();
});

test('Cacti disables HTML history snapshots and restricts HTMX requests to this origin', async ({ page }) => {
	await page.goto('/');
	const config = await page.evaluate(() => Reflect.get(window, 'htmx').config);
	expect(config.allowEval).toBe(false);
	expect(config.allowScriptTags).toBe(false);
	expect(config.selfRequestsOnly).toBe(true);
	expect(config.historyCacheSize).toBe(0);
	expect(config.refreshOnHistoryMiss).toBe(true);
});

test('HTMX expression prefixes are not URL schemes and cannot enable evaluation (CodeQL 167)', async ({ page }) => {
	await page.goto('/');
	await page.route('**/__codeql-values', route => route.fulfill({ contentType: 'text/html', body: 'safe' }));
	await page.evaluate(() => {
		const button = document.createElement('button');
		button.id = 'expression-probe';
		button.textContent = 'Probe';
		button.setAttribute('hx-get', '/__codeql-values');
		button.setAttribute('hx-vals', 'js:{probe:(window.codeqlExecuted=1)}');
		document.body.append(button);
		document.addEventListener('htmx:evalDisallowedError', () => button.dataset.blocked = 'true');
		Reflect.get(window, 'htmx').process(button);
	});
	await page.locator('#expression-probe').click();
	await expect(page.locator('#expression-probe')).toHaveAttribute('data-blocked', 'true');
	expect(await page.evaluate(() => Reflect.get(window, 'codeqlExecuted'))).toBeUndefined();
});

test('Cacti history protection leaves non-HTMX popstate handlers intact', async ({ page }) => {
	await page.goto('/');
	const reached = await page.evaluate(() => {
		let calls = 0;
		const previous = window.onpopstate;
		window.onpopstate = () => { calls++; };
		try {
			window.dispatchEvent(new PopStateEvent('popstate', { state: { cacti: true } }));
			window.dispatchEvent(new PopStateEvent('popstate', { state: null }));
			return calls;
		} finally {
			window.onpopstate = previous;
		}
	});
	expect(reached).toBe(2);
});

test('Cacti rejects tampered history HTML and reloads from the server even when snapshots are reenabled', async ({ page }) => {
	let documents = 0;
	page.on('request', request => { if (request.isNavigationRequest()) documents++; });
	await page.goto('/');
	await page.waitForLoadState('networkidle');
	documents = 0;
	const original = page.url();
	await page.route('**/__codeql-history-next', route => route.fulfill({ contentType: 'text/html', body: '<p id="marker">second</p>' }));
	await page.evaluate(() => {
		// Cacti's legacy navigation owns the initial state. Mark this fixture
		// entry as HTMX-owned so Back actually exercises restoreHistory().
		history.replaceState({ ...history.state, htmx: true }, document.title);
		// Exercise stale/plugin-enabled caches rather than relying on size=0.
		Reflect.get(window, 'htmx').config.historyCacheSize = 10;
		Reflect.get(window, 'htmx').config.refreshOnHistoryMiss = false;
		const button = document.createElement('button');
		button.id = 'history-next';
		button.textContent = 'Next';
		button.setAttribute('hx-get', '/__codeql-history-next');
		button.setAttribute('hx-target', '#history-target');
		button.setAttribute('hx-push-url', 'true');
		const target = document.createElement('div');
		target.id = 'history-target';
		document.body.append(button, target);
		Reflect.get(window, 'htmx').process(button);
	});
	await page.locator('#history-next').click();
	await expect(page).toHaveURL(/__codeql-history-next$/);
	await page.evaluate(() => {
		const cache = JSON.parse(sessionStorage.getItem('htmx-history-cache')!);
		cache[0].content = '<p id="tampered">tampered</p><img src=x onerror="window.codeqlExecuted=1">';
		sessionStorage.setItem('htmx-history-cache', JSON.stringify(cache));
		// Prevent saveCurrentPageToHistory from clearing the seeded cache.
		document.body.setAttribute('hx-history', 'false');
	});
	await page.goBack();
	await expect.poll(() => documents).toBeGreaterThan(0);
	// The navigation request can start before the new document's loader runs.
	// Wait for its cache cleanup rather than inspecting the departing page.
	await page.waitForFunction(() => sessionStorage.getItem('htmx-history-cache') === null);
	await expect(page).toHaveURL(original);
	await expect(page.locator('#tampered')).toHaveCount(0);
	expect(await page.evaluate(() => sessionStorage.getItem('htmx-history-cache'))).toBeNull();
});

for (const input of [
	'{"__proto__":{"codeqlPollution":true},"minTime":35,"startOnPageLoad":false}',
	'{"ajax":{"__proto__":{"codeqlPollution":true}},"minTime":35,"startOnPageLoad":false}',
	'{"constructor":{"prototype":{"codeqlPollution":true}},"minTime":35,"startOnPageLoad":false}',
]) {
	test(`Pace option merging rejects prototype keys: ${input}`, async ({ page }) => {
		await page.route('**/__codeql-pace', route => route.fulfill({
			contentType: 'text/html',
			body: '<!doctype html><html><head><script src="/include/js/pace.js"></script></head><body>safe</body></html>',
		}));
		await page.addInitScript(options => { Reflect.set(window, 'paceOptions', JSON.parse(options)); }, input);
		await page.goto('/__codeql-pace');
		const result = await page.evaluate(() => ({
			polluted: Reflect.get(Object.prototype, 'codeqlPollution'),
			minTime: Reflect.get(window, 'Pace').options.minTime,
			constructorOverride: Object.hasOwn(Reflect.get(window, 'Pace').options, 'constructor'),
		}));
		expect(result.polluted).toBeUndefined();
		expect(result.constructorOverride).toBe(false);
		expect(result.minTime).toBe(35);
	});
}

test('Billboard ignores prototype keys while preserving valid chart options (CodeQL 129)', async ({ page }) => {
	await page.route('**/__codeql-billboard', route => route.fulfill({
		contentType: 'text/html',
		body: '<!doctype html><html><head><script src="/include/js/d3.js"></script><script src="/include/js/billboard.js"></script></head><body><div id="chart"></div></body></html>',
	}));
	await page.goto('/__codeql-billboard');
	const result = await page.evaluate(() => {
		const options = JSON.parse('{"bindto":"#chart","data":{"columns":[["safe",1,2]],"__proto__":{"codeqlPollution":true}},"__proto__":{"codeqlPollution":true},"constructor":{"prototype":{"codeqlPollution":true}}}');
		const chart = Reflect.get(window, 'bb').generate(options);
		const data = chart.data();
		chart.destroy();
		return { polluted: Reflect.get(Object.prototype, 'codeqlPollution'), id: data[0].id };
	});
	expect(result.polluted).toBeUndefined();
	expect(result.id).toBe('safe');
});
