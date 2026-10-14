/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { chromium } from '@playwright/test';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { cpus, platform, arch } from 'node:os';

const output = resolve(process.env.CACTI_PROFILE_OUTPUT ?? '');
const base = new URL(process.env.CACTI_BASE_URL ?? 'http://127.0.0.1:18093/');
if (process.env.CACTI_PROFILE_DISPOSABLE !== '1' || !['127.0.0.1', 'localhost'].includes(base.hostname)) {
	throw new Error('Use the disposable profiling runner on loopback.');
}
const fixture = JSON.parse(await readFile(resolve(output, 'fixture.json'), 'utf8'));
const transitions = Number(process.env.CACTI_PROFILE_TRANSITIONS ?? 100);
if (!Number.isInteger(transitions) || transitions < 1 || transitions > 1000) throw new Error('Use 1–1000 transitions.');
await mkdir(resolve(output, 'browser'), { recursive: true });
console.log('Starting Chromium profiling browser.');
const browser = await chromium.launch();
console.log('Connecting to the disposable Cacti fixture.');
const report = {
	schema: 1, complete: false, kind: 'disposable application with controlled realtime responses',
	environment: { node: process.version, browser: browser.version(), platform: platform(), arch: arch(), cpu: cpus()[0]?.model, timezone: 'UTC' },
	fixture, navigation: [], realtime: [], themes: [],
};

async function openTheme(theme) {
	const debug = stage => { if (process.env.CACTI_PROFILE_DEBUG === '1') console.log(`${theme}: ${stage}`); };
	debug('opening login');
	const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, timezoneId: 'UTC' });
	// Keep the workload local and repeatable, including themes that load fonts
	// from an external CDN. The system fallback font is used in those themes.
	await context.route('**/*', route => new URL(route.request().url()).origin === base.origin ? route.continue() : route.abort());
	// Observe timers without retaining callback functions or DOM objects.
	await context.addInitScript(() => {
		const timeouts = new Set();
		const intervals = new Set();
		const nativeTimeout = window.setTimeout;
		const nativeInterval = window.setInterval;
		const nativeClearTimeout = window.clearTimeout;
		const nativeClearInterval = window.clearInterval;
		window.setTimeout = function(callback, delay, ...args) {
			let id;
			if (typeof callback === 'function') {
				id = nativeTimeout.call(window, (...values) => { timeouts.delete(id); Reflect.apply(callback, window, values); }, delay, ...args);
			} else {
				id = nativeTimeout.call(window, callback, delay, ...args);
			}
			timeouts.add(id);
			return id;
		};
		window.setInterval = function(callback, delay, ...args) {
			const id = nativeInterval.call(window, callback, delay, ...args);
			intervals.add(id);
			return id;
		};
		window.clearTimeout = function(id) { timeouts.delete(id); intervals.delete(id); nativeClearTimeout.call(window, id); };
		window.clearInterval = function(id) { timeouts.delete(id); intervals.delete(id); nativeClearInterval.call(window, id); };
		window.__cactiProfile = { timers: () => ({ timeouts: timeouts.size, intervals: intervals.size }), skin: [] };
	});
	const page = await context.newPage();
	let errors = 0;
	page.on('pageerror', () => errors++);
	await page.goto(base.href);
	debug('submitting login');
	await page.locator('#login_username').fill(`profile-${theme}`);
	await page.locator('#login_password').fill('profile-fixture-pass');
	if (await page.locator('#realm').count()) await page.locator('#realm').selectOption('0');
	await Promise.all([
		page.waitForNavigation(),
		page.locator('form#auth').evaluate(form => form.submit()),
	]);
	if (await page.locator('#login_username').count()) throw new Error(`Fixture login failed for ${theme}.`);
	debug('opening device table');
	await page.goto(new URL('host.php?rows=500', base).href);
	await page.waitForFunction(theme => window.theme === theme && typeof window.applySkin === 'function', theme);
	const devices = await page.locator('tr[id^="line"]').count();
	if (devices !== fixture.devices) throw new Error(`${theme} rendered ${devices} device rows, expected ${fixture.devices}.`);
	await page.evaluate(() => {
		const original = window.applySkin;
		window.applySkin = function(...args) {
			const start = performance.now();
			try { return Reflect.apply(original, this, args); }
			finally { window.__cactiProfile.skin.push(performance.now() - start); }
		};
	});
	const cdp = await context.newCDPSession(page);
	await cdp.send('Performance.enable');
	debug('device table ready');
	return { context, page, cdp, errors: () => errors };
}

async function snapshot(page, cdp) {
	await cdp.send('HeapProfiler.collectGarbage');
	const { metrics } = await cdp.send('Performance.getMetrics');
	const counters = Object.fromEntries(metrics.map(value => [value.name, value.value]));
	// Experimental CDP support differs between Chromium versions. Missing is
	// explicit, never reported as zero detached nodes.
	let detachedNodes = null;
	try { detachedNodes = (await cdp.send('DOM.getDetachedDomNodes')).detachedNodes.length; } catch {}
	const timers = await page.evaluate(() => window.__cactiProfile.timers());
	return {
		heapBytes: counters.JSHeapUsedSize, domNodes: counters.Nodes, listeners: counters.JSEventListeners,
		detachedTrees: detachedNodes, layoutCount: counters.LayoutCount, layoutSeconds: counters.LayoutDuration,
		styleCount: counters.RecalcStyleCount, styleSeconds: counters.RecalcStyleDuration,
		scriptSeconds: counters.ScriptDuration, taskSeconds: counters.TaskDuration, timers,
	};
}

async function ajax(page, path) {
	return page.evaluate(async path => {
		const start = performance.now();
		const url = new URL(path, location.href);
		url.searchParams.set('header', 'false');
		const response = await fetch(url, { signal: AbortSignal.timeout(30000) });
		if (!response.ok) throw new Error(`AJAX profiling returned HTTP ${response.status}.`);
		const html = await response.text();
		if (!html.includes('id="main"') && !html.includes("id='main'")) throw new Error('AJAX response has no application content.');
		window.handleAjaxResponse(html, { url: path, noState: true, force: true, elementId: 'main' });
		return performance.now() - start;
	}, path);
}

async function navigation(theme) {
	const { page, context, cdp, errors } = await openTheme(theme);
	try {
		await ajax(page, 'host.php?rows=500'); // Warm up before timing.
		const checkpoints = [{ transition: 0, ...await snapshot(page, cdp) }];
		const elapsed = [];
		await browser.startTracing(page, { path: resolve(output, 'browser', `navigation-${theme}.trace.json`), screenshots: false });
		for (let index = 1; index <= transitions; index++) {
			elapsed.push(await ajax(page, `host.php?rows=500&filter=${index % 2 ? 'Profile' : ''}`));
			if (index % 25 === 0 || index === transitions) {
				checkpoints.push({ transition: index, ...await snapshot(page, cdp) });
			}
			if (index % 10 === 0 || index === transitions) console.log(`${theme}: ${index}/${transitions} AJAX transitions.`);
		}
		await browser.stopTracing();
		const rows = await page.locator('tr[id^="line"]').count();
		if (rows !== fixture.devices) throw new Error(`Navigation rendered ${rows} devices, expected ${fixture.devices}.`);
		report.navigation.push({ theme, transitions, elapsedMs: elapsed, applySkinMs: await page.evaluate(() => window.__cactiProfile.skin), checkpoints, pageErrors: errors() });
		console.log(`Profiled ${transitions} AJAX transitions in ${theme}.`);
	} finally { await context.close(); }
}

async function rendering(theme) {
	const { page, context, cdp, errors } = await openTheme(theme);
	try {
		const samples = [];
		const graphFailures = [];
		const graphResponses = [];
		page.on('pageerror', error => graphFailures.push(error.message));
		page.on('response', async response => {
			if (new URL(response.url()).pathname.endsWith('/graph_json.php')) {
				try {
					const data = await response.json();
					graphResponses.push({ status: response.status(), id: data.local_graph_id, image: Boolean(data.image), keys: Object.keys(data) });
				} catch { graphFailures.push(`Graph response: HTTP ${response.status()}, invalid JSON`); }
			}
		});
		await browser.startTracing(page, { path: resolve(output, 'browser', `rendering-${theme}.trace.json`), screenshots: false });
		for (const view of ['devices', 'graphs']) {
			if (view === 'graphs') {
				const url = new URL('graph_view.php?action=preview&graphs=50', base);
				for (const [key, timestamp] of [['date1', fixture.start], ['date2', fixture.end]]) {
					url.searchParams.set(key, new Date(timestamp * 1000).toISOString().slice(0, 19).replace('T', ' '));
				}
				await page.goto(url.href);
				try {
					await page.waitForFunction(expected => document.querySelectorAll('.graphimage').length === expected, fixture.graphs);
				} catch {
					const state = await page.evaluate(() => ({ date1: document.querySelector('#date1')?.value, date2: document.querySelector('#date2')?.value, init: typeof initPage }));
					throw new Error(`${theme}: ${await page.locator('.graphWrapper').count()} wrappers, ${await page.locator('.graphimage').count()} images, ${JSON.stringify(state)}, ${graphResponses.length} responses: ${JSON.stringify(graphResponses.slice(0, 2))}. ${graphFailures.slice(0, 3).join('; ')}`);
				}
			}
			await page.evaluate(async () => { await Promise.all([...document.images].map(image => image.decode().catch(() => {}))); });
			const before = await snapshot(page, cdp);
			for (const width of [1440, 900, 375, 1440]) {
				await page.setViewportSize({ width, height: 900 });
				await page.evaluate(async () => {
					const areas = [document.scrollingElement, ...document.querySelectorAll('.cactiConsoleContentArea, .cactiGraphContentArea, #main, #mdw-Main')].filter(Boolean);
					for (const area of areas) {
						area.scrollTop = area.scrollHeight;
						await new Promise(resolve => requestAnimationFrame(resolve));
						area.scrollTop = 0;
					}
					await new Promise(resolve => requestAnimationFrame(resolve));
				});
			}
			const after = await snapshot(page, cdp);
			const rows = await page.locator('tr[id^="line"]').count();
			const images = await page.locator('.graphimage').count();
			if (view === 'devices' && rows !== fixture.devices) throw new Error(`${theme} rendered ${rows} device rows.`);
			if (view === 'graphs' && images !== fixture.graphs) throw new Error(`${theme} rendered ${images} graph images.`);
			if (view === 'graphs' && !await page.locator('.graphimage').evaluateAll(images => images.every(image => image.naturalWidth > 0))) {
				throw new Error(`${theme} contains graph images that failed to decode.`);
			}
			samples.push({ view, rows, images, before, after });
		}
		await browser.stopTracing();
		report.themes.push({ theme, samples, pageErrors: errors() });
		console.log(`Profiled device tables and graph grids in ${theme}.`);
	} finally { await context.close(); }
}

async function realtime() {
	const { page, context, cdp, errors } = await openTheme('modern');
	try {
		// Fetch actual RRD-rendered images, then control only transport delay/failure.
		const images = [];
		for (let id = 1; id <= fixture.graphs; id++) {
			const response = await context.request.get(new URL(`graph_image.php?local_graph_id=${id}&rra_id=0&graph_start=${fixture.start}&graph_end=${fixture.end}`, base).href);
			const body = await response.body();
			if (!response.ok() || body.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a') {
				throw new Error(`Fixture graph ${id}: HTTP ${response.status()}, ${response.headers()['content-type']}, prefix ${body.subarray(0, 128).toString('utf8')}`);
			}
			images.push(body.toString('base64'));
		}
		await page.addScriptTag({ url: new URL('include/realtime.js', base).href });
		await page.evaluate(() => {
			const root = document.getElementById('main');
			root.replaceChildren();
			for (const [id, value] of [['graph_start', '60'], ['ds_step', '60']]) {
				const input = document.createElement('input'); input.id = id; input.value = value; root.append(input);
			}
		});
		for (const count of [1, 10, 50]) {
			for (const mode of ['normal', 'slow', 'failure']) {
				let active = 0, peak = 0, requests = 0, failures = 0, responseBytes = 0;
				const routeHandler = async route => {
					const id = Number(new URL(route.request().url()).searchParams.get('local_graph_id'));
					requests++; active++; peak = Math.max(peak, active);
					try {
						if (mode === 'slow') await new Promise(resolve => setTimeout(resolve, 250));
						if (mode === 'failure' && id % 10 === 1) { failures++; await route.fulfill({ status: 503, body: 'Profile fixture failure' }); }
						else {
							const body = JSON.stringify({ local_graph_id: id, image_format: 'png', data: images[id - 1] });
							responseBytes += Buffer.byteLength(body);
							await route.fulfill({ contentType: 'application/json', body });
						}
					} finally { active--; }
				};
				await page.route('**/graph_realtime.php?**', routeHandler);
				const before = await snapshot(page, cdp);
				await browser.startTracing(page, { path: resolve(output, 'browser', `realtime-${count}-${mode}.trace.json`), screenshots: false });
				const cycles = [];
				for (let cycle = 0; cycle < 3; cycle++) {
					const start = performance.now();
					const expected = requests + count;
					await page.evaluate(({ count, initial }) => {
						clearTimeout(window.realtimeTimer);
						if (initial) {
							window.realtimeArray = []; window.graphsRendered = null; window.prevTotalGraphs = null;
							for (let id = 1; id <= count; id++) {
								window.realtimeArray[id] = true;
								let wrapper = document.getElementById(`wrapper_${id}`);
								if (!wrapper) {
									wrapper = document.createElement('div'); wrapper.id = `wrapper_${id}`;
									const image = document.createElement('img'); image.id = `graph_${id}`; wrapper.append(image); document.getElementById('main').append(wrapper);
								}
							}
						}
						window.realtimeGrapher();
					}, { count, initial: cycle === 0 });
					// Measure stalls as a result. Do not reset graphsRendered to hide them.
					const deadline = performance.now() + 10000;
					while ((requests < expected || active) && performance.now() < deadline) await new Promise(resolve => setTimeout(resolve, 10));
					await page.evaluate(async () => {
						await Promise.all([...document.querySelectorAll('img[id^="graph_"][src]')].map(image => image.decode().catch(() => {})));
						await new Promise(resolve => requestAnimationFrame(resolve));
					});
					const settled = await page.evaluate(() => window.graphsRendered);
					cycles.push({ cycle, elapsedMs: performance.now() - start, settled, stalled: requests < expected || settled < count });
					if (cycles.at(-1).stalled) break;
				}
				await page.evaluate(() => clearTimeout(window.realtimeTimer));
				await browser.stopTracing();
				report.realtime.push({ graphs: count, mode, simulatedDelayMs: mode === 'slow' ? 250 : 0, cycles, requests, failures, peakConcurrentRequests: peak, responseBytes, before, after: await snapshot(page, cdp), pageErrors: errors() });
				await page.unroute('**/graph_realtime.php?**', routeHandler);
				console.log(`Profiled ${count} realtime graphs with ${mode} responses.`);
			}
		}
	} finally { await context.close(); }
}

try {
	for (const theme of fixture.themes.filter(theme => ['modern', 'midwinter'].includes(theme))) await navigation(theme);
	await realtime();
	for (const theme of fixture.themes) await rendering(theme);
	report.complete = true;
} finally {
	await writeFile(resolve(output, 'browser.json'), JSON.stringify(report, null, 2) + '\n');
	await browser.close();
}
