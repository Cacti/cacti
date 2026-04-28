import { test, expect, type Page, type Request } from '@playwright/test';

/*
 * Plugin CSP harness. The base csp.spec.ts proves the header shape and
 * verifies the pilot pages (logout, permission_denied) carry matching
 * nonces. This spec extends coverage to two third-party plugins shipped
 * by the Cacti project — thold and monitor — to catch the case where a
 * plugin emits inline <script> tags directly without calling
 * CactiSecureHeaders::getNonceAttribute().
 *
 * Strategy:
 *   1. Log in as admin.
 *   2. Hook the page's network layer to capture every POST to
 *      /csp_report.php (the report endpoint configured in nonce-report
 *      mode). Any non-empty capture set means the plugin emitted at
 *      least one inline tag the browser refused to nonce-validate.
 *   3. Walk the plugin's admin index page. Status=1 (installed, not
 *      active) is what the entrypoint seeds, which is enough for
 *      plugins.php?plugin=<dir> to render the plugin's UI hooks.
 *   4. On any captured violation, attach the report bodies to the
 *      Playwright trace and fail with a structured message naming
 *      the violating directive and source so the maintainer can fix
 *      the offending plugin file directly.
 *
 * Scope decision: this is a smoke test, not a feature walkthrough.
 * Activating thold or monitor runs install hooks that touch rrdtool,
 * poller cache, and per-plugin schema migrations, none of which the
 * harness seeds. Walking the plugin index page exercises the plugin's
 * menu hooks and any inline JS the plugin emits at navigation chrome
 * load, which is where most CSP violations surface.
 */

interface CapturedReport {
    url: string;
    body: string;
    parsed?: Record<string, unknown>;
}

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.locator('form#login input[type="submit"]').click(),
    ]);
}

function attachReportListener(page: Page, sink: CapturedReport[]): void {
    /* Capture both directions:
     *   - request: gives us the JSON body the browser sent
     *   - response: confirms the endpoint accepted it (defensive; the
     *     spec doesn't require a 204, but a 4xx here would mask a real
     *     violation behind a transport error)
     * Using page.on('request') instead of route() means we don't
     * intercept and short-circuit the report — the endpoint still gets
     * to log it, which matches production behavior under nonce-report. */
    page.on('request', (req: Request) => {
        const url = req.url();
        if (!/\/csp_report\.php(\?|$)/.test(url)) {
            return;
        }
        const body = req.postData() ?? '';
        const captured: CapturedReport = { url, body };
        try {
            captured.parsed = JSON.parse(body) as Record<string, unknown>;
        } catch {
            /* Browsers emit application/csp-report which is JSON-shaped
             * but with a different content-type. JSON.parse handles both
             * the legacy and the newer Reporting API formats. If parsing
             * fails we keep the raw body for the failure message. */
        }
        sink.push(captured);
    });
}

function formatReports(reports: CapturedReport[]): string {
    if (reports.length === 0) {
        return '(no reports)';
    }
    return reports
        .map((r, i) => {
            const cspReport = (r.parsed?.['csp-report'] ?? r.parsed) as
                | Record<string, unknown>
                | undefined;
            const directive = cspReport?.['violated-directive']
                ?? cspReport?.['effective-directive']
                ?? '<unknown>';
            const blocked = cspReport?.['blocked-uri'] ?? '<inline>';
            const source = cspReport?.['source-file'] ?? '<unknown source>';
            const line = cspReport?.['line-number'] ?? '?';
            return `  [${i + 1}] directive=${String(directive)} blocked=${String(blocked)} source=${String(source)}:${String(line)}`;
        })
        .join('\n');
}

interface PluginWalk {
    /* Plugin directory under plugins/. Matches plugin_config.directory. */
    directory: string;
    /* Display name for test titles. */
    label: string;
    /* Paths to walk. The first entry should be the plugin index — the
     * page Cacti renders when a user clicks the plugin's menu entry. The
     * URL pattern follows lib/plugins.php's plugins.php?plugin=<dir>
     * convention; for plugins that register their own top-level page
     * (thold's threshold list, monitor's device dashboard) we hit those
     * directly so the spec exercises the plugin's own templates rather
     * than the generic plugins.php landing.
     *
     * If the harness can't load these URLs (plugin not active, missing
     * schema), the test logs a skip rather than failing — the goal is
     * to catch CSP regressions, not to gate on plugin install state. */
    pages: string[];
}

const PLUGINS: PluginWalk[] = [
    {
        directory: 'thold',
        label: 'thold',
        pages: [
            '/plugins.php?plugin=thold',
            /* TODO: add a thold-specific URL once the harness can run
             * the plugin's install hook. Examples observed in
             * develop-1.2.x: thold.php?action=list,
             * thold_graph.php?tab=thold. Both require thold's tables to
             * exist; the harness seeds plugin_config status=1 only. */
        ],
    },
    {
        directory: 'monitor',
        label: 'monitor',
        pages: [
            '/plugins.php?plugin=monitor',
            /* TODO: add monitor.php once the plugin's host_view table is
             * seeded. The monitor index expects rows in the monitor
             * settings table that the install hook creates. */
        ],
    },
];

test.describe('Plugin CSP under nonce-report', () => {
    for (const plugin of PLUGINS) {
        test(`${plugin.label}: walking admin pages emits no CSP violations`, async ({ page }) => {
            const reports: CapturedReport[] = [];
            attachReportListener(page, reports);

            await loginAsAdmin(page);

            /* The login flow hits core pages (auth_login.php, the
             * post-login redirect) that may legitimately emit CSP
             * reports in nonce-report mode while inline-tag migration
             * is still in progress. Clear anything captured during
             * setup so the assertion below covers only the plugin
             * pages walked by this test. */
            reports.splice(0, reports.length);

            for (const path of plugin.pages) {
                const resp = await page.goto(path, { waitUntil: 'networkidle' });
                /* A 404 here means the plugin route isn't registered —
                 * either the plugin isn't installed in plugin_config or
                 * the URL pattern moved. Don't fail the spec on that;
                 * the point is to catch CSP violations on pages that
                 * DO render. Log it and move on. */
                if (!resp || resp.status() === 404) {
                    test.info().annotations.push({
                        type: 'skip-path',
                        description: `${plugin.label}: ${path} returned ${resp?.status() ?? 'no response'}`,
                    });
                    continue;
                }
                /* 5xx means the plugin's PHP threw — fail loudly,
                 * because that's a harness bug we want surfaced. */
                expect(
                    resp.status(),
                    `${plugin.label}: ${path} returned 5xx; check php logs`,
                ).toBeLessThan(500);

                /* Give the page a beat to fire any deferred inline
                 * <script> blocks. networkidle covers most of it but
                 * jQuery DOMContentReady handlers can still fire after.
                 * 250ms is empirically enough on the CI runner. */
                await page.waitForTimeout(250);
            }

            /* Attach the captured reports as an artifact regardless of
             * pass/fail so post-mortem on a flaky run can see what the
             * browser sent. */
            if (reports.length > 0) {
                await test.info().attach(`${plugin.label}-csp-reports.json`, {
                    body: JSON.stringify(reports, null, 2),
                    contentType: 'application/json',
                });
            }

            expect(
                reports,
                `Plugin ${plugin.label} emitted ${reports.length} CSP violation report(s):\n${formatReports(reports)}\n` +
                    `Each report names the offending directive and source file. Fix by adding the attribute ` +
                    `returned by CactiSecureHeaders::getNonceAttribute() to the inline <script> tag, or move ` +
                    `the JavaScript to an external file under the plugin's include/ tree. Inline <style> tags ` +
                    `and inline style attributes are still permitted because the style-src directive keeps ` +
                    `unsafe-inline.`,
            ).toHaveLength(0);
        });
    }
});
