/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

// Folds the per-case JSON written by develop-theme-accessibility.spec.ts into
// test-results/a11y/summary.json and summary.md.

import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, resolve } from 'node:path';

const dir = resolve(import.meta.dirname, 'test-results/a11y');
const casesDir = join(dir, 'cases');

if (!existsSync(casesDir)) {
	console.error(`no audit cases in ${casesDir}; run the accessibility spec first`);
	process.exit(1);
}

const cases = readdirSync(casesDir)
	.filter((f) => f.endsWith('.json'))
	.sort()
	.map((f) => JSON.parse(readFileSync(join(casesDir, f), 'utf8')));

const groups = new Map();
const rules = new Map();

for (const c of cases) {
	const key = `${c.theme}|${c.mode}|${c.dir}`;
	const g = groups.get(key) ?? { theme: c.theme, mode: c.mode, dir: c.dir, pages: 0, rules: {}, focusFailures: 0, focusStops: 0, layoutFailures: [], loadFailures: [] };
	g.pages++;
	g.focusFailures += c.focusFailures;
	g.focusStops += c.focus.length;
	if (c.loadError) g.loadFailures.push(c.page);
	else if (!c.layoutPass) g.layoutFailures.push(c.page);

	for (const v of c.violations) {
		g.rules[v.id] = (g.rules[v.id] ?? 0) + v.nodes;
		const r = rules.get(v.id) ?? { id: v.id, impact: v.impact, help: v.help, nodes: 0, themes: new Set(), targets: new Map() };
		r.nodes += v.nodes;
		r.themes.add(c.theme);
		for (const t of v.targets) {
			const pattern = t.replace(/#[\w-]+/g, '#id').replace(/:nth-child\(\d+\)/g, '');
			r.targets.set(pattern, (r.targets.get(pattern) ?? 0) + 1);
		}
		rules.set(v.id, r);
	}
	groups.set(key, g);
}

const top = [...rules.values()]
	.sort((a, b) => b.nodes - a.nodes)
	.map((r) => ({
		id: r.id,
		impact: r.impact,
		help: r.help,
		nodes: r.nodes,
		themes: [...r.themes].sort((a, b) => Number(a > b) - Number(a < b)),
		selectors: [...r.targets.entries()].sort((a, b) => b[1] - a[1]).slice(0, 3).map(([s]) => s),
	}));

const table = [...groups.values()];
writeFileSync(join(dir, 'summary.json'), JSON.stringify({ cases: cases.length, groups: table, rules: top }, null, '\t'));

function selectorCode(selector) {
	const text = selector.replace(/[\\`|&<>*_~\[\]\r\n]/g, (character) => `&#${character.codePointAt(0)};`);
	return `<code>${text}</code>`;
}

const lines = [
	'# Theme accessibility baseline',
	'',
	`${cases.length} page audits. Axe tags: wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa.`,
	'',
	'| Theme | Mode | Dir | Pages | Axe violations (nodes by rule) | Focus stops failing | Layout failures | Pages that did not load |',
	'| --- | --- | --- | --- | --- | --- | --- | --- |',
	...table.map((g) => `| ${g.theme} | ${g.mode} | ${g.dir} | ${g.pages} | ${Object.entries(g.rules).sort((a, b) => b[1] - a[1]).map(([id, n]) => `${id}: ${n}`).join(', ') || 'none'} | ${g.focusFailures}/${g.focusStops} | ${g.layoutFailures.join(', ') || 'none'} | ${g.loadFailures.join(', ') || 'none'} |`),
	'',
	'## Recurring failures',
	'',
	'| Rule | Impact | Nodes | Themes | Common selectors |',
	'| --- | --- | --- | --- | --- |',
	...top.map((r) => `| ${r.id} | ${r.impact} | ${r.nodes} | ${r.themes.join(', ')} | ${r.selectors.map(selectorCode).join('<br>')} |`),
	'',
];
writeFileSync(join(dir, 'summary.md'), lines.join('\n'));
console.log(`wrote ${join(dir, 'summary.md')}`);
