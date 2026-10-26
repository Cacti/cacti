/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readdirSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import fixture from '../rtm-theme-fixtures.json';
import { registerPluginThemeTests } from '../plugin-theme-matrix';
import { rtmRoot, rtmStyles, verifyRtmRevision } from '../rtm-theme';

const metadata = Object.fromEntries(Object.entries(fixture.tables).map(([id, data]) => [`rtm-${id}`, data]));
const revisions = Object.fromEntries(Object.keys(metadata).map((id) => [id, fixture.revision]));
const plugins = Object.fromEntries(Object.keys(metadata).map((id) => [id, rtmRoot]));
registerPluginThemeTests(metadata, revisions, plugins, rtmStyles);

test('RTM inventory accounts for every source plugin and available UI entry point', () => {
	verifyRtmRevision();
	const directories = readdirSync(resolve(rtmRoot, 'plugins'), { withFileTypes: true })
		.filter((entry) => entry.isDirectory())
		.map((entry) => entry.name)
		.sort();
	const classified = [
		...Object.keys(fixture.tables),
		...Object.keys(fixture.special),
		...Object.keys(fixture.demoOnly),
	].sort();
	expect(classified).toEqual(directories);
	for (const data of Object.values(fixture.tables))
		expect(existsSync(resolve(rtmRoot, 'plugins', data.source))).toBeTruthy();
	for (const source of [...Object.values(fixture.special), ...Object.values(fixture.demoOnly)])
		expect(existsSync(resolve(rtmRoot, 'plugins', source))).toBeTruthy();
	expect(existsSync(resolve(rtmRoot, 'plugins/projmap/setup.php'))).toBe(false);
});
