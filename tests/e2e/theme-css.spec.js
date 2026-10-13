const { test, expect } = require('@playwright/test');
const { readdirSync, existsSync } = require('node:fs');
const path = require('node:path');

const themeRoot = path.resolve(__dirname, '../../include/themes');
const allThemes = readdirSync(themeRoot, { withFileTypes: true })
  .filter(entry => entry.isDirectory() && existsSync(path.join(themeRoot, entry.name, 'main.css')))
  .map(entry => entry.name).sort();

test.describe('theme jquery ui css alignment', () => {
  test('all themes serve 1.14.x jquery ui bundles', async ({ request }) => {
    for (const theme of allThemes) {
      const response = await request.get(`/include/themes/${theme}/jquery-ui.css`);
      expect(response.ok(), `${theme} css request should succeed`).toBeTruthy();

      const css = await response.text();
      expect(css, `${theme} should advertise jquery ui 1.14.x`).toContain('jQuery UI - v1.14.');
      expect(css, `${theme} should not advertise jquery ui 1.12.1`).not.toContain('jQuery UI - v1.12.1');
    }
  });

});

test.describe('theme jquery ui browser smoke', () => {
  for (const theme of allThemes) {
    test(`widgets initialize and render for ${theme}`, async ({ page }) => {
      await page.goto(`/tests/e2e/theme-smoke.html?theme=${theme}`);
      await page.waitForFunction(() => window.__themeSmokeReady === true || window.__themeSmokeError);

      const smokeError = await page.evaluate(() => window.__themeSmokeError || null);
      expect(smokeError).toBeNull();

      await expect(page.locator('#theme-select-button')).toBeVisible();
      await expect(page.locator('#open-dialog')).toHaveClass(/ui-button/);
      await expect(page.locator('#group')).toHaveClass(/ui-controlgroup|ui-buttonset/);

      await page.getByRole('button', { name: 'Open Dialog' }).click();
      await expect(page.locator('.ui-dialog')).toBeVisible();
      await expect(page.locator('.ui-widget-overlay')).toBeVisible();

      const screenshot = await page.locator('#sandbox').screenshot();
      expect(screenshot.byteLength, `${theme} sandbox screenshot should not be empty`).toBeGreaterThan(1000);

    });
  }
});
