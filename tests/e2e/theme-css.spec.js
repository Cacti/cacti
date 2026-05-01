const { test, expect } = require('@playwright/test');

const allThemes = ['cacti', 'carrot', 'dark', 'hollyberry', 'midwinter', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise'];
const alignedThemes = ['cacti', 'carrot', 'hollyberry', 'midwinter', 'raspberry'];
const genericThemes = ['cacti', 'carrot', 'hollyberry', 'raspberry'];

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

  test('generic aligned themes match the paw reference bundle', async ({ request }) => {
    const reference = await (await request.get('/include/themes/paw/jquery-ui.css')).text();

    for (const theme of genericThemes) {
      const css = await (await request.get(`/include/themes/${theme}/jquery-ui.css`)).text();
      expect(css, `${theme} should match paw reference bundle`).toBe(reference);
    }
  });

  test('midwinter retains the custom selectmenu overrides as valid css', async ({ request }) => {
    const css = await (await request.get('/include/themes/midwinter/jquery-ui.css')).text();

    expect(css).toContain('button.ui-multiselect,');
    expect(css).toContain('.ui-selectmenu-button.ui-button:focus-visible');
    expect(css).toContain('.ui-button.ui-state-active:focus-within');
    expect(css).toContain('background: var(--background-progress);');
    expect(css).not.toContain('&:focus-within');
    expect(css).not.toContain('-webkit-tap-highlight-color: 1px solid');
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

      if (theme === 'midwinter') {
        const midwinterSelectmenu = await page.locator('#theme-select-button').evaluate((element) => {
          const style = getComputedStyle(element);
          return {
            display: style.display,
            maxWidth: style.maxWidth,
          };
        });

        expect(midwinterSelectmenu.display).toBe('inline-flex');
        expect(midwinterSelectmenu.maxWidth).toBe('400px');
      }
    });
  }
});
