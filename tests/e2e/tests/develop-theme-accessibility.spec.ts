/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Browser, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { existsSync, mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

/*
 * WCAG 2.2 AA audit of every theme against a running Cacti (CACTI_BASE_URL).
 *
 * Themes are discovered from include/themes at collection time, so a new theme
 * is audited without editing this file. Each case writes its findings to
 * test-results/a11y/cases/; a11y-summary.mjs folds them into summary.json and
 * summary.md. Cases only fail when CACTI_A11Y_ENFORCE=1, because the current
 * themes do not pass yet and the baseline is the point of the first run.
 *
 * Text contrast is owned by develop-theme-correctness.spec.ts, so axe's
 * color-contrast rule is off unless CACTI_A11Y_TEXT_CONTRAST=1 asks for it
 * (useful when comparing a whole branch against develop). This spec covers what
 * that suite does not: the remaining axe WCAG 2.2 AA rules on real pages, the
 * 3:1 focus indicator check for every theme, and right-to-left layout.
 */

const root = resolve(__dirname, '../../..');
const outDir = resolve(__dirname, '../test-results/a11y/cases');
const baseURL = process.env.CACTI_BASE_URL ?? 'http://127.0.0.1:8080';
const enforce = process.env.CACTI_A11Y_ENFORCE === '1';
const wcagTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const skipRules = process.env.CACTI_A11Y_TEXT_CONTRAST === '1' ? [] : ['color-contrast'];

// A theme only offers light and dark when its CSS switches on data-theme-color.
function cssFiles(dir: string): string[] {
	return readdirSync(dir).flatMap((entry) => {
		const path = join(dir, entry);
		if (statSync(path).isDirectory()) return cssFiles(path);
		return entry.endsWith('.css') ? [path] : [];
	});
}

const themes = readdirSync(resolve(root, 'include/themes'))
	.filter((name) => existsSync(resolve(root, 'include/themes', name, 'main.css')))
	.sort()
	.map((name) => {
		const switchable = cssFiles(resolve(root, 'include/themes', name))
			.some((file) => readFileSync(file, 'utf8').includes('[data-theme-color'));
		return { name, modes: switchable ? ['light', 'dark'] : ['default'] };
	});

const directions = [
	{ dir: 'ltr', language: 'en-US' },
	{ dir: 'rtl', language: 'he-IL' },
];

const pages = [
	{ name: 'console', path: '/index.php' },
	{ name: 'devices', path: '/host.php' },
	{ name: 'device-form', path: '/host.php?action=edit' },
	{ name: 'graph-tree', path: '/graph_view.php?action=tree' },
	{ name: 'settings', path: '/settings.php' },
	{ name: 'users', path: '/user_admin.php' },
	{ name: 'log', path: '/utilities.php?action=view_logfile' },
];

test.describe.configure({ mode: 'parallel' });

// Each case logs in on its own context: a saved storage state is rejected by
// Cacti's session binding, so cookies cannot be shared between contexts.
async function openCase(browser: Browser, login: boolean, mode: string, url: string): Promise<Page | string> {
	const context = await browser.newContext({
		baseURL,
		colorScheme: mode === 'dark' ? 'dark' : 'light',
		viewport: { width: 1366, height: 900 },
	});
	const page = await context.newPage();

	if (login) {
		await page.goto('/');
		await page.locator('#login_username').fill('ldapuser');
		await page.locator('#login_password').fill('ldap-e2e-pass');
		await page.locator('#realm').selectOption('1001');
		await Promise.all([
			page.waitForNavigation(),
			page.locator('form#auth input[type="submit"], form#auth button[type="submit"]').click(),
		]);
		await expect(page.locator('form#auth')).toHaveCount(0);
	}

	try {
		await page.goto(url, { timeout: 20000 });
	} catch (error) {
		// A page that never finishes loading is itself a finding, not a harness failure.
		await context.close();
		return `page did not load within 20s: ${(error as Error).message.split('\n')[0]}`;
	}

	// Some pages keep a request open (midwinter's device form), so idle is best effort.
	await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

	if (mode !== 'default') {
		// The theme script restores its own mode from storage; pin the one under test.
		await page.evaluate((mode) => document.documentElement.setAttribute('data-theme-color', mode), mode);
		await page.waitForTimeout(250);
	}

	return page;
}

// Tab through the first focusable elements. The indicator is whatever changes
// between the focused and unfocused rendering (outline, box-shadow, border or
// background); it must exist and reach 3:1 against the color it replaces or the
// background behind it (WCAG 2.4.7 and 1.4.11).
async function focusAudit(page: Page) {
	const stops = [];
	await page.locator('body').click({ position: { x: 1, y: 1 } }).catch(() => {});

	for (let i = 0; i < 15; i++) {
		await page.keyboard.press('Tab');
		const stop = await page.evaluate(() => {
			const el = document.activeElement as HTMLElement | null;
			if (!el || el === document.body) return null;

			// Parse through a canvas like the text contrast helper, so color-mix() and
			// color(srgb ...) values resolve the same way in both suites.
			const canvas = document.createElement('canvas');
			canvas.width = canvas.height = 1;
			const context = canvas.getContext('2d', { willReadFrequently: true })!;
			const rgb = (value: string) => {
				context.clearRect(0, 0, 1, 1);
				context.fillStyle = value;
				context.fillRect(0, 0, 1, 1);
				return Array.from(context.getImageData(0, 0, 1, 1).data.slice(0, 3));
			};
			const lum = (c: string) => rgb(c).map((v) => {
				v /= 255;
				return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
			}).reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
			const ratio = (a: string, b: string) => {
				const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
				return (hi + 0.05) / (lo + 0.05);
			};
			const opaque = (c: string) => c !== 'transparent' && !/rgba\([^)]*,\s*0\)$/.test(c);
			const snapshot = () => {
				const st = getComputedStyle(el);
				return {
					outlineStyle: st.outlineStyle,
					outlineWidth: parseFloat(st.outlineWidth),
					outlineColor: st.outlineColor,
					boxShadow: st.boxShadow,
					borderColor: st.borderTopColor,
					borderWidth: parseFloat(st.borderTopWidth),
					background: st.backgroundColor,
				};
			};

			let behind = 'rgb(255, 255, 255)';
			for (let node = el.parentElement; node; node = node.parentElement) {
				const c = getComputedStyle(node).backgroundColor;
				if (opaque(c)) { behind = c; break; }
			}

			const on = snapshot();
			el.blur();
			const off = snapshot();
			el.focus({ preventScroll: true });

			let kind = 'none';
			let color = '';
			let against = behind;

			if (on.outlineStyle === 'auto') {
				kind = 'browser-default';
			} else if (on.outlineStyle !== 'none' && on.outlineWidth > 0 && (on.outlineStyle !== off.outlineStyle || on.outlineColor !== off.outlineColor || on.outlineWidth !== off.outlineWidth)) {
				kind = 'outline';
				color = on.outlineColor;
			} else if (on.boxShadow !== off.boxShadow && on.boxShadow !== 'none') {
				kind = 'box-shadow';
				color = (on.boxShadow.match(/rgba?\([^)]*\)/) || [''])[0];
			} else if (on.borderColor !== off.borderColor && on.borderWidth > 0) {
				kind = 'border';
				color = on.borderColor;
				against = off.borderColor;
			} else if (on.background !== off.background) {
				kind = 'background';
				color = opaque(on.background) ? on.background : behind;
				against = opaque(off.background) ? off.background : behind;
			}

			const contrast = color ? Math.round(ratio(color, against) * 100) / 100 : null;
			const label = el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') +
				(typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');

			return {
				element: label,
				kind,
				color,
				against,
				contrast,
				pass: kind === 'browser-default' || (contrast !== null && contrast >= 3),
			};
		});

		if (stop) stops.push(stop);
	}

	return stops;
}

async function rtlAudit(page: Page) {
	return page.evaluate(() => {
		const doc = document.documentElement;
		const candidates = ['#mdw-ConsoleNavigation', '.cactiConsoleNavigationArea', '.cactiTreeNavigationArea', '#navigation'];
		let navigation: { selector: string; centerX: number } | null = null;

		for (const selector of candidates) {
			const el = document.querySelector(selector) as HTMLElement | null;
			if (!el) continue;
			const box = el.getBoundingClientRect();
			if (box.width > 0 && box.height > 0) {
				navigation = { selector, centerX: Math.round(box.left + box.width / 2) };
				break;
			}
		}

		return {
			dir: doc.getAttribute('dir'),
			lang: doc.getAttribute('lang'),
			scrollWidth: doc.scrollWidth,
			clientWidth: doc.clientWidth,
			horizontalOverflow: doc.scrollWidth > doc.clientWidth + 1,
			navigation,
			navigationOnRight: navigation ? navigation.centerX > window.innerWidth / 2 : null,
		};
	});
}

async function audit(page: Page, record: Record<string, unknown>, dir: string) {
	const axe = await new AxeBuilder({ page }).withTags(wcagTags).disableRules(skipRules).analyze();
	const violations = axe.violations.map((v) => ({
		id: v.id,
		impact: v.impact,
		help: v.help,
		nodes: v.nodes.length,
		targets: v.nodes.slice(0, 5).map((n) => n.target.join(' ')),
	}));
	const focus = await focusAudit(page);
	const layout = await rtlAudit(page);

	const result = {
		...record,
		dir,
		url: page.url(),
		violations,
		focus,
		focusFailures: focus.filter((s) => !s.pass).length,
		layout,
		layoutPass: dir === 'rtl'
			? layout.dir === 'rtl' && !layout.horizontalOverflow && layout.navigationOnRight !== false
			: layout.dir === 'ltr' && !layout.horizontalOverflow,
	};

	mkdirSync(outDir, { recursive: true });
	const id = [record.theme, record.mode, dir, record.page].join('__').replace(/[^a-z0-9_-]/gi, '-');
	writeFileSync(join(outDir, id + '.json'), JSON.stringify(result, null, '\t'));

	if (enforce) {
		expect(violations, 'axe violations').toEqual([]);
		expect(result.focusFailures, 'focus indicators below 3:1 or missing').toBe(0);
		expect(result.layoutPass, 'document direction or layout').toBe(true);
	}
}

function recordLoadFailure(record: Record<string, unknown>, dir: string, error: string) {
	mkdirSync(outDir, { recursive: true });
	const id = [record.theme, record.mode, dir, record.page].join('__').replace(/[^a-z0-9_-]/gi, '-');
	writeFileSync(join(outDir, id + '.json'), JSON.stringify({ ...record, dir, loadError: error, violations: [], focus: [], focusFailures: 0, layoutPass: false }, null, '\t'));

	if (enforce) {
		throw new Error(error);
	}
}

test.setTimeout(60_000);

for (const { dir, language } of directions) {
	// The login page renders with the system theme, so it is audited once per direction.
	test(`login page meets WCAG 2.2 AA (${dir})`, async ({ browser }) => {
		const record = { theme: 'system-default', mode: 'default', page: 'login' };
		const page = await openCase(browser, false, 'default', `/index.php?language=${language}`);
		if (typeof page === 'string') return recordLoadFailure(record, dir, page);
		await expect(page.locator('form#auth')).toBeVisible();
		await audit(page, record, dir);
		await page.context().close();
	});

	for (const { name: theme, modes } of themes) {
		for (const mode of modes) {
			for (const target of pages) {
				test(`${theme} ${mode} ${dir} ${target.name} meets WCAG 2.2 AA`, async ({ browser }) => {
					const sep = target.path.includes('?') ? '&' : '?';
					const record = { theme, mode, page: target.name };
					const page = await openCase(browser, true, mode, `${target.path}${sep}newtheme=${theme}&language=${language}`);
					if (typeof page === 'string') return recordLoadFailure(record, dir, page);
					await expect(page.locator('form#auth')).toHaveCount(0);
					await audit(page, record, dir);
					await page.context().close();
				});
			}
		}
	}
}
