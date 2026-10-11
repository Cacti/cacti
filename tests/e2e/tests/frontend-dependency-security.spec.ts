import { test, expect } from '@playwright/test';

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

test('Cacti rejects tampered history HTML and reloads from the server even when snapshots are reenabled', async ({ page }) => {
	let documents = 0;
	page.on('request', request => { if (request.isNavigationRequest()) documents++; });
	await page.goto('/');
	const original = page.url();
	await page.route('**/__codeql-history-next', route => route.fulfill({ contentType: 'text/html', body: '<p id="marker">second</p>' }));
	await page.evaluate(() => {
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
	await expect.poll(() => documents).toBeGreaterThan(1);
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
