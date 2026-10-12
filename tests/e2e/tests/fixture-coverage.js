/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
const fs = require('node:fs');

function collectCoverage(test) {
	test.beforeEach(async ({ page }) => {
		await page.coverage.startJSCoverage({ resetOnNavigation: false });
		await page.coverage.startCSSCoverage({ resetOnNavigation: false });
	});
	test.afterEach(async ({ page }, testInfo) => {
		const data = { js: await page.coverage.stopJSCoverage(), css: await page.coverage.stopCSSCoverage() };
		const file = testInfo.outputPath('changed-coverage.json');
		fs.writeFileSync(file, JSON.stringify(data));
		await testInfo.attach('changed-coverage', { path: file, contentType: 'application/json' });
	});
}
module.exports = { collectCoverage };
