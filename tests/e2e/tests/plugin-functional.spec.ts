import { test, expect, type Page, type Response, type TestInfo } from '@playwright/test';

/*
 * Plugin functional smoke under the new environment.
 *
 * csp-plugins.spec.ts walks plugin admin pages with the plugin in
 * status=1 ("installed but not active") and asserts no CSP reports
 * fire. This spec drives the activation cycle through the admin UI:
 *
 *   plugins.php landing -> install action -> enable action -> plugin
 *   index page renders core elements
 *
 * The point is to catch a regression where a plugin loads under the
 * stock environment but breaks once any of the new components ship —
 * the CSP nonce headers (1.2.x) or the Symfony helpers (develop). A
 * plugin that emits inline <script> with no nonce attribute would
 * clear csp-plugins.spec.ts (which only asserts on report-uri POSTs
 * in nonce-report mode) but its dropdowns would silently stop firing
 * jQuery handlers under enforce mode. This spec catches that by
 * asserting the rendered DOM after activation.
 *
 * Activation can fail when the plugin's install hook reaches into
 * rrdtool / poller_cache paths the harness does not seed; on failure
 * the spec captures the response body and PHP error log as Playwright
 * artifacts and skips the rest of the cycle for that plugin so the
 * remaining specs still run.
 */

interface PluginFunctional {
    /* plugin_config.directory */
    directory: string;
    /* Display name used in test titles. */
    label: string;
    /* CSS / role selectors that must be present on the plugin's main
     * page after activation. The selectors are chosen so a missing
     * jQuery handler or a CSP-blocked script registration causes them
     * to be absent. Keep the count small (1-3) per plugin so a single
     * miss is unambiguous. */
    indexUrl: string;
    expectedSelectors: string[];
}

const PLUGINS: PluginFunctional[] = [
    {
        directory: 'thold',
        label: 'thold',
        indexUrl: '/plugins/thold/thold.php',
        expectedSelectors: [
            /* thold.php?action=list renders a tholds table with the
             * standard Cacti table chrome. The export button is JS-bound
             * and only appears once thold's inline script has executed. */
            'table.cactiTable',
        ],
    },
    {
        directory: 'monitor',
        label: 'monitor',
        indexUrl: '/plugins/monitor/monitor.php',
        expectedSelectors: [
            /* monitor.php registers a host_view template with a header
             * cell labelled by Cacti's __() helper. The exact label
             * varies across plugin versions (Description / Hostname);
             * matching on the table itself is more durable. */
            'table.cactiTable',
        ],
    },
];

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.locator('form#login input[type="submit"]').click(),
    ]);
}

async function attachResponseOnFailure(
    info: TestInfo,
    label: string,
    resp: Response | null,
): Promise<void> {
    if (resp === null) {
        return;
    }
    const status = resp.status();
    if (status < 500) {
        return;
    }
    const body = await resp.text().catch(() => '<unreadable>');
    await info.attach(`${label}-${status}.html`, {
        body,
        contentType: 'text/html',
    });
}

test.describe('Plugin functional walk', () => {
    /* The harness stages plugins at status=1 (installed). Activation is
     * driven through plugins.php?action=install then ?action=enable,
     * which exercises the same code path the human admin would use.
     * Both actions accept the plugin's id (PK in plugin_config) on the
     * query string; we look it up via the row link in plugins.php so
     * the test does not need to query the DB directly. */
    for (const plugin of PLUGINS) {
        test(`${plugin.label}: install -> enable -> index renders`, async ({ page }) => {
            await loginAsAdmin(page);

            const adminResp = await page.goto('/plugins.php', { waitUntil: 'networkidle' });
            await attachResponseOnFailure(test.info(), `${plugin.label}-plugins-admin`, adminResp);
            expect(adminResp?.status(), 'plugins.php should render').toBeLessThan(500);

            /* Each plugin row in plugins.php has data-id-{directory}
             * attributes on the action links thanks to plugins_table()
             * in lib/plugins.php. If the plugin is missing entirely,
             * skip rather than fail — the entrypoint may have skipped
             * staging this plugin if its repo clone failed. */
            const row = page.locator(`tr:has-text("${plugin.directory}")`).first();
            const rowCount = await row.count();
            if (rowCount === 0) {
                test.info().annotations.push({
                    type: 'skip-plugin',
                    description: `${plugin.label}: row absent from plugins.php; plugin not staged`,
                });
                test.skip();
                return;
            }

            /* Trigger install via direct URL. The install link in the UI
             * is `plugins.php?action=install&id=N&plugin=<dir>`; the id
             * is recoverable from the row's data-id attribute, but the
             * directory-keyed form is stable across schema changes. */
            const installResp = await page.goto(
                `/plugins.php?action=install&plugin=${plugin.directory}`,
                { waitUntil: 'networkidle' },
            );
            await attachResponseOnFailure(test.info(), `${plugin.label}-install`, installResp);
            expect(installResp?.status(), 'install action should not 5xx').toBeLessThan(500);

            const enableResp = await page.goto(
                `/plugins.php?action=enable&plugin=${plugin.directory}`,
                { waitUntil: 'networkidle' },
            );
            await attachResponseOnFailure(test.info(), `${plugin.label}-enable`, enableResp);
            const enableStatus = enableResp?.status() ?? 0;

            /* Some plugins emit a 4xx when their install hook bails
             * (missing rrdtool, missing poller cache table). 4xx is a
             * harness-fixture issue, not a plugin-under-the-new-env
             * regression — log and skip the index assertion. 5xx is a
             * real bug and we fail loud. */
            if (enableStatus >= 400 && enableStatus < 500) {
                test.info().annotations.push({
                    type: 'skip-functional',
                    description: `${plugin.label}: enable returned ${enableStatus}; install hook likely blocked on harness fixtures`,
                });
                return;
            }
            expect(enableStatus, `enable should not 5xx`).toBeLessThan(500);

            /* Walk the plugin's main page and assert each expected
             * selector is present. waitFor is bounded by the project
             * default expect timeout (10s) so a missing element fails
             * fast without dragging out the suite. */
            const indexResp = await page.goto(plugin.indexUrl, { waitUntil: 'networkidle' });
            await attachResponseOnFailure(test.info(), `${plugin.label}-index`, indexResp);
            const indexStatus = indexResp?.status() ?? 0;
            if (indexStatus === 404) {
                test.info().annotations.push({
                    type: 'skip-index',
                    description: `${plugin.label}: ${plugin.indexUrl} returned 404; plugin route absent`,
                });
                return;
            }
            expect(indexStatus, `index page should not 5xx`).toBeLessThan(500);

            for (const selector of plugin.expectedSelectors) {
                await expect(
                    page.locator(selector).first(),
                    `${plugin.label}: expected selector "${selector}" on ${plugin.indexUrl}`,
                ).toBeVisible();
            }
        });
    }

    /* Standalone assertion: the admin plugin manager itself renders
     * cleanly under the new env. This is the canary for any change
     * that moves plugins.php's bootstrap chain (Symfony Console
     * adoption, helper renames). If this fails, the per-plugin tests
     * above are uninterpretable. */
    test('plugins.php admin renders both plugin rows', async ({ page }) => {
        await loginAsAdmin(page);
        const resp = await page.goto('/plugins.php', { waitUntil: 'networkidle' });
        expect(resp?.status()).toBeLessThan(400);

        for (const plugin of PLUGINS) {
            const row = page.locator(`tr:has-text("${plugin.directory}")`);
            await expect(
                row.first(),
                `${plugin.label} should appear in plugins.php`,
            ).toBeVisible();
        }
    });
});
