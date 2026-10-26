/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { existsSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { textContrast } from './text-contrast';
const core = resolve(__dirname, '../..');
const baseline = '883b334debc61c436e6ba140cf91863c7d6686b7';
export interface PluginFixture {
	title: string;
	status: string;
	metric: string;
	caption: string;
	row: string;
	link: string;
	add?: boolean;
	selectable?: boolean;
	wrapper?: string;
	names?: string[];
	statusCellClass?: string;
	plainName?: boolean;
}

export const themeVariants = [
	'cacti',
	'carrot',
	'dark',
	'deepness',
	'hollyberry',
	'modern',
	'paper-plane',
	'paw',
	'raspberry',
	'sunrise',
].map((theme) => ({ theme, palette: '' }));
themeVariants.push({ theme: 'midwinter', palette: 'dark' }, { theme: 'midwinter', palette: 'light' });

export function registerPluginThemeTests(
	metadata: Record<string, PluginFixture>,
	revisions: Record<string, string>,
	plugins: Record<string, string>,
	stylesheetPaths?: (plugin: string, theme: string) => string[],
) {
	for (const [plugin, revision] of Object.entries(revisions))
		for (const { theme, palette } of themeVariants) {
			test(`${plugin} ${theme} ${palette} compatibility`, async ({ page }, info) => {
				expect(
					existsSync(plugins[plugin]),
					`Missing pinned ${plugin} checkout: see docs/validation/plugin-theme-compatibility.md`,
				).toBeTruthy();
				expect(
					execFileSync('git', ['rev-parse', 'HEAD'], { cwd: plugins[plugin], encoding: 'utf8' }).trim(),
				).toBe(revision);
				const measurements: { version: string; state: string; kind: string; ratio: number }[] = [];
				await page.route('http://plugin-theme.test/**', async (route) => {
					const path = new URL(route.request().url()).pathname.slice(1);
					if (path === 'blank') return route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html>' });
					const [kind, ...parts] = path.split('/');
					const file = parts.join('/');
					if (kind === 'baseline') {
						try {
							return route.fulfill({
								contentType: file.endsWith('.css') ? 'text/css' : undefined,
								body: execFileSync('git', ['show', `${baseline}:${file}`], { cwd: core }),
							});
						} catch {
							return route.abort();
						}
					}
					const base = kind === 'core' ? core : plugins[plugin];
					const target = resolve(base, file);
					return target.startsWith(base + '/') && existsSync(target)
						? route.fulfill({ path: target })
						: route.abort();
				});
				for (const version of ['baseline', 'core']) {
					await page.goto('http://plugin-theme.test/blank');
					await page.setViewportSize({ width: 1280, height: 700 });
					const meta = metadata[plugin],
						title = meta.title;
					const rows = [1, 2]
						.map(
							(id) =>
								`<tr class="${meta.row}" id="line${id}"><td>${meta.plainName ? '<span>' : `<a class="${meta.link}" href="#device-${id}">`}${meta.names?.[id - 1] || (plugin === 'alarmmanager' ? String(500 + id) : id === 1 ? 'edge-rtr-01' : 'branch-fw-02')}${meta.plainName ? '</span>' : '</a>'}</td><td class="${meta.statusCellClass || ''}">${meta.status}</td><td>${meta.metric}</td>${meta.selectable ? `<td class="checkbox" style="width:1%"><input type="checkbox" class="checkbox" id="chk_${id}" name="chk_${id}" aria-label="Select device ${id}"><label class="formCheckboxLabel" for="chk_${id}"></label></td>` : ''}</tr>`,
						)
						.join('');
					await page.setContent(
						`<!DOCTYPE html><main class="cactiConsoleContentArea" style="padding:20px"><h1>${plugin} · Theme compatibility</h1><p>Browser fixture · Synthetic monitoring data</p><div id="${plugin === 'syslog' ? 'syslog_workspace' : 'plugin_workspace'}"><div class="${meta.wrapper || (plugin === 'syslog' ? 'syslogResultsMain' : '')}"><div class="cactiTable" style="width:calc(100vw - 55px)"><div class="cactiTableTitleRow"><div class="cactiTableTitle"><span>${title}</span></div><div class="cactiTableButton">${meta.add ? '<span class="cactiFilterAdd"><a class="linkOverDark" href="#add" aria-label="Add item"><i class="ti ti-plus plusAdd"></i></a></span>' : ''}</div></div><table class="cactiTable"><thead><tr class="tableHeader"><th>Device</th><th>Status</th><th>${meta.caption}</th>${meta.selectable ? '<th>Select</th>' : ''}</tr></thead><tbody>${rows}</tbody></table></div></div></div><button id="details-button">Open details</button><div id="details" style="display:none"><p>edge-rtr-01: ${meta.metric} · North campus monitoring fixture</p></div></main>`,
					);
					await page.addStyleTag({
						content: '*, *::before, *::after { transition: none !important; animation: none !important; }',
					});
					await page.evaluate((palette) => {
						if (palette) document.documentElement.setAttribute('data-theme-color', palette);
						document.documentElement.setAttribute('data-theme-state', 'ready');
						for (const box of document.querySelectorAll('input'))
							box.addEventListener('change', () =>
								box.closest('tr')!.classList.toggle('selected', (box as HTMLInputElement).checked),
							);
					}, palette);
					await page.addStyleTag({
						url: 'http://plugin-theme.test/core/include/tabler/dist/tabler-icons.min.css',
					});
					await page.addStyleTag({
						url: `http://plugin-theme.test/${version}/include/themes/${theme}/jquery-ui.css`,
					});
					await page.addStyleTag({
						url: `http://plugin-theme.test/${version}/include/themes/${theme}/main.css`,
					});
					const themed = existsSync(resolve(plugins[plugin], `css/${theme}.css`));
					const pluginStyles: Record<string, string[]> = {
						thold: [`css/${theme}.css`, 'css/pills.css'],
						mactrack: ['css/cards.css', themed ? `css/${theme}.css` : 'css/mactrack.css'],
						syslog: ['css/search.css', 'css/dashboard.css', themed ? `css/${theme}.css` : 'css/legend.css'],
						alarmmanager: ['css/dashboard.css'],
						hmib: ['css/hmib.css', `css/${theme}.css`],
						flowview: [themed ? `css/${theme}.css` : 'css/default.css'],
						monitor: ['css/monitor.css', `css/${theme}.css`],
					};
					const files = stylesheetPaths ? stylesheetPaths(plugin, theme) : pluginStyles[plugin];
					for (const file of files)
						if (existsSync(resolve(plugins[plugin], file)))
							await page.addStyleTag({ url: `http://plugin-theme.test/plugin/${file}` });
					if (plugin === 'syslog') {
						await page.addScriptTag({ url: 'http://plugin-theme.test/core/include/js/jquery.js' });
						await page.addScriptTag({ url: 'http://plugin-theme.test/plugin/js/functions.js' });
						await page.evaluate(() =>
							(window as any).applySyslogTheme(document.getElementById('syslog_workspace')),
						);
					}
					await page.locator('body').evaluate((e) => {
						e.style.opacity = '1';
						e.style.visibility = 'visible';
					});
					for (const state of meta.selectable
						? ['rest', 'hover', 'selected', 'selected-hover']
						: ['rest', 'hover']) {
						await page.mouse.move(0, 0);
						if (state === 'selected')
							await page.locator('#line1 input').evaluate((input: HTMLInputElement) => {
								input.checked = true;
								input.dispatchEvent(new Event('change', { bubbles: true }));
							});
						if (state.includes('hover'))
							await page
								.locator(meta.plainName ? '#line1 td:first-child' : '#line1 td:first-child a')
								.hover();
						const ratio = await page
							.locator(meta.plainName ? '#line1 td:first-child' : '#line1 td:first-child a')
							.evaluate(textContrast);
						measurements.push({ version, state, kind: 'link', ratio });
						const status = await page.locator('#line1 td:nth-child(2) > :first-child').evaluate(textContrast);
						measurements.push({ version, state, kind: 'status', ratio: status });

						if (meta.add)
							measurements.push({
								version,
								state,
								kind: 'add',
								ratio: await page.locator('.cactiTableButton i').evaluate(textContrast),
							});
					}
					writeFileSync(info.outputPath('contrast-comparison.json'), JSON.stringify(measurements, null, 2));
					if (version === 'core') {
						for (const width of [1280, 320]) {
							await page.setViewportSize({ width, height: 700 });
							const boxes = await page.locator('.cactiTableTitleRow').evaluate((e) => {
								const r = e.getBoundingClientRect(),
									t = e.querySelector('.cactiTableTitle')!.getBoundingClientRect(),
									b = e.querySelector('.cactiTableButton')!.getBoundingClientRect();
								return {
									left: r.left,
									right: r.right,
									bottom: r.bottom,
									titleRight: t.right,
									titleBottom: t.bottom,
									toolsLeft: b.left,
									toolsRight: b.right,
									toolsBottom: b.bottom,
								};
							});
							expect(boxes.titleRight, JSON.stringify(boxes)).toBeLessThanOrEqual(boxes.toolsLeft + 1);
							expect(boxes.titleBottom, JSON.stringify(boxes)).toBeLessThanOrEqual(boxes.bottom + 1);
							expect(boxes.toolsRight, JSON.stringify(boxes)).toBeLessThanOrEqual(boxes.right + 1);
							expect(boxes.toolsBottom, JSON.stringify(boxes)).toBeLessThanOrEqual(boxes.bottom + 1);
							await page.screenshot({
								path: info.outputPath(`${plugin}-${theme}-${palette || 'default'}-${width}.png`),
								fullPage: true,
							});
						}
						await page.addScriptTag({ url: 'http://plugin-theme.test/core/include/js/jquery.js' });
						await page.addScriptTag({ url: 'http://plugin-theme.test/core/include/js/jquery-ui.js' });
						await page.evaluate(() => {
							document.getElementById('details-button')!.addEventListener('click', () => {
								(window as any).jQuery('#details').dialog({
									modal: true,
									width: 270,
									buttons: [
										{
											text: 'Close',
											click() {
												(window as any).jQuery(this).dialog('destroy');
											},
										},
									],
								});
							});
						});
						await page.getByRole('button', { name: 'Open details' }).click();
						await expect(page.getByRole('dialog')).toBeVisible();
						await expect(page.getByRole('dialog')).toContainText('edge-rtr-01');
						await page.getByRole('button', { name: 'Close', exact: true }).last().click();
						await expect(page.getByRole('dialog')).toHaveCount(0);
					}
				}
				writeFileSync(info.outputPath('contrast-comparison.json'), JSON.stringify(measurements, null, 2));
				for (const row of measurements.filter((r) => r.version === 'core')) {
					const before = measurements.find(
						(r) => r.version === 'baseline' && r.state === row.state && r.kind === row.kind,
					);
					expect(
						row.ratio,
						`${row.kind} ${row.state}: before ${before!.ratio}, after ${row.ratio}`,
					).toBeGreaterThanOrEqual(
						(plugin === 'syslog' ? 4.5 : Math.min(row.kind === 'add' ? 3 : 4.5, before!.ratio)) - 0.02,
					);
				}
			});
		}
}
