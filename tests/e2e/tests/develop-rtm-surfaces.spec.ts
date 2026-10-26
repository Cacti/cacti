/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { themeVariants } from '../plugin-theme-matrix';
import { installRtmTheme } from '../rtm-theme';
import { collectThemeCoverage } from '../theme-css';

collectThemeCoverage();

const surfaces = {
	RTM: `<table class="cactiTable"><tr class="rtmTitle tableRow"><td class="textAreaNotes top left">RTM research monitoring</td></tr><tr class="rtmSubTitle tableRow"><td>Monitoring 128 Hosts on 2 Clusters</td></tr></table>
 <table class="cactiTable"><tr><td><ul style="list-style:none;display:flex;flex-wrap:wrap;padding:5px">
 <li class="llist"><table><tr class="tableRow"><td class="landing_page_tile_large">Research cluster</td></tr><tr class="tableRow"><td class="landing_page_tile_small"><a href="#hosts">View 128 batch hosts</a></td></tr><tr class="tableRow"><td class="landing_page_tile_small">48 running jobs · 12 pending jobs</td></tr></table></li>
 <li class="llist"><table><tr class="tableRow"><td class="landing_page_tile_large">License usage</td></tr><tr class="tableRow"><td class="landing_page_tile_small"><a href="#licenses">View solver licenses</a></td></tr><tr class="tableRow"><td class="landing_page_tile_small">32 of 64 tokens in use</td></tr></table></li></ul></td></tr></table>`,
	gridpend: `<div class="cactiTable"><div class="cactiTableTitleRow"><div class="cactiTableTitle"><span>Pending Reason Filters · Research cluster · Batch queue history</span></div><div class="cactiTableButton"></div></div>
 <table class="cactiTable"><tr class="odd"><td><table class="filterTable"><tr><td><label for="clusterid">Cluster</label></td><td><select id="clusterid"><option value="1">Research cluster</option><option value="2">Batch cluster</option></select></td></tr><tr><td><label for="projqueue">Queue</label></td><td><select id="projqueue"><option value="-1">All</option><option value="research">research</option></select></td></tr></table></td></tr></table></div>
 <div class="cactiTable"><div class="cactiTableTitleRow"><div class="cactiTableTitle"><span>Pending Reason History</span></div><div class="cactiTableButton"></div></div><table class="cactiTable"><tr><td><div id="gridpendchartcontianer"></div></td></tr></table></div>`,
	lsfenh: `<div class="cactiTable"><div class="cactiTableTitleRow"><div class="cactiTableTitle"><span>Settings · RTM Plugins · Cluster event retention</span></div><div class="cactiTableButton"></div></div>
 <table class="cactiTable"><tr class="tableHeader"><td colspan="2">RTM Cluster Level Events</td></tr><tr class="even"><td><label for="lsfenh_retention">Retention Period for LSF Batch Restart/Reconfigure Events</label></td><td><select id="lsfenh_retention"><option value="180">180 Days</option><option value="365">1 Year</option><option value="730">2 Years</option></select></td></tr></table></div>`,
};

for (const [plugin, markup] of Object.entries(surfaces))
	for (const { theme, palette } of themeVariants) {
		test(`rtm-${plugin} ${theme} ${palette} native surface`, async ({ page }, info) => {
			await page.setContent(
				`<!DOCTYPE html><main class="cactiConsoleContentArea" style="padding:20px"><h1>RTM ${plugin}</h1><p>Synthetic monitoring fixture</p>${markup}</main>`,
			);
			await installRtmTheme(page, plugin, theme, palette);
			if (plugin === 'gridpend') {
				await page.locator('#clusterid').selectOption('2');
				await expect(page.locator('#clusterid')).toHaveValue('2');
				await page.locator('#projqueue').selectOption('research');
				await expect(page.locator('#projqueue')).toHaveValue('research');
				// The public source omits FusionCharts. Do not substitute a fake chart.
				await expect(page.locator('#gridpendchartcontianer')).toHaveCount(1);
			} else if (plugin === 'lsfenh') {
				await page.locator('#lsfenh_retention').selectOption('365');
				await expect(page.locator('#lsfenh_retention')).toHaveValue('365');
			} else {
				await expect(page.getByText('Monitoring 128 Hosts on 2 Clusters')).toBeVisible();
				await page.getByRole('link', { name: 'View solver licenses' }).click();
				await expect(page).toHaveURL(/#licenses$/);
			}
			for (const width of [1280, 320]) {
				await page.setViewportSize({ width, height: 700 });
				for (const bar of await page.locator('.cactiTableTitleRow').all()) {
					const boxes = await bar.evaluate((element) => {
						const outer = element.getBoundingClientRect();
						const title = element.querySelector('.cactiTableTitle')!.getBoundingClientRect();
						return {
							right: outer.right,
							bottom: outer.bottom,
							titleRight: title.right,
							titleBottom: title.bottom,
						};
					});
					expect(boxes.titleRight).toBeLessThanOrEqual(boxes.right + 1);
					expect(boxes.titleBottom).toBeLessThanOrEqual(boxes.bottom + 1);
				}
				await page.screenshot({
					path: info.outputPath(`rtm-${plugin}-${theme}-${palette || 'default'}-${width}.png`),
					fullPage: true,
				});
			}
		});
	}
