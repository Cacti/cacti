'use strict';

const { test, expect } = require('@playwright/test');
const { readFileSync } = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const layout = readFileSync(path.join(root, 'include/layout.js'), 'utf8');
const helper = layout.match(/^function addFilterSearchIcons\(\) \{.*?^\}/ms)[0];

for (const theme of ['modern', 'dark', 'deepness', 'midwinter', 'paper-plane', 'paw', 'sunrise']) {
    test(`${theme} decorates revealed filters once and preserves input behavior`, async ({ page }) => {
        const themeScript = readFileSync(path.join(root, 'include/themes', theme, 'main.js'), 'utf8');
        expect(themeScript).toContain('setTimeout(addFilterSearchIcons, 0)');
        await page.goto('/tests/e2e/theme-style-regressions.html');
        await page.setContent('<table><tr><td><input id="filter" value="saved filter"></td>' +
            '<td><input id="filterd" disabled></td><td id="hidden" style="display:none"><input id="rfilter"></td></tr></table>');
        await page.addStyleTag({ url: `/include/themes/${theme}/main.css` });
        await page.addStyleTag({ url: '/include/fa/css/all.min.css' });
        await page.addScriptTag({ url: '/include/js/jquery.js' });
        await page.addScriptTag({ content: helper });
        await page.evaluate(() => {
            window.searchFilter = 'Search';
            window.searchRFilter = 'Regular expression';
            addFilterSearchIcons();
            addFilterSearchIcons();
        });
        await expect(page.locator('i.fa-search.filter')).toHaveCount(2);
        await expect(page.locator('#filter')).toHaveValue('saved filter');
        await expect(page.locator('#filterd')).toBeDisabled();
        await expect(page.locator('#rfilter + i')).toHaveCount(0);
        for (const width of [1440, 375]) {
            await page.setViewportSize({ width, height: 800 });
            await page.locator('#filter').focus();
            await expect(page.locator('#filter')).toBeFocused();
            await page.locator('#filter').hover();
            await expect(page.locator('#filter + i')).toBeVisible();
        }
        await page.evaluate(() => {
            document.querySelector('#hidden').style.display = '';
            addFilterSearchIcons();
            addFilterSearchIcons();
        });
        await expect(page.locator('i.fa-search.filter')).toHaveCount(3);
        await expect(page.locator('#rfilter')).toHaveAttribute('placeholder', 'Regular expression');
        await expect(page.locator('#filter')).toHaveAttribute('placeholder', 'Search');
        await expect(page.locator('#filter')).toHaveAttribute('autocomplete', 'off');
        await expect(page.locator('#hidden')).toHaveCSS('white-space', 'nowrap');
    });
}
