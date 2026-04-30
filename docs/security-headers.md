# Web security headers

Cacti sets its full HTTP security-header set in one place: `lib/headers_secure.php`.
`include/global.php` calls `CactiSecureHeaders::emitHeaders()` once per request,
early in the pipeline, so every authenticated page gets the same policy.

## Header set

| Header | Value |
|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-inline' <alternates>; style-src 'self' 'unsafe-inline' <alternates>; img-src 'self' <alternates> data: blob:; font-src 'self' <alternates>; connect-src 'self' <alternates>; frame-src 'self'; frame-ancestors 'self'; worker-src 'self' <alternates>; object-src 'none'; base-uri 'self'; form-action 'self'; manifest-src 'self';` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), geolocation=(), interest-cohort=(), microphone=(), payment=(), usb=()` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (HTTPS only) |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `Cross-Origin-Resource-Policy` | `same-origin` |
| `P3P` | `CP="CAO PSA OUR"` (legacy IE cookie handling) |
| `Cache-Control` | `no-store, no-cache, must-revalidate` |

`<alternates>` expands from `content_security_alternate_sources` in the
settings table; leave it empty unless a plugin needs to load assets from a
whitelisted CDN. `content_security_policy_script=unsafe-eval` adds
`'unsafe-eval'` to `script-src`; leave it off unless a plugin needs it.

## `'unsafe-inline'` status

Both `script-src` and `style-src` still allow `'unsafe-inline'`. Cacti ships
~180 inline `<script>` tags across the UI that each need to be migrated to
either an external file or a `nonce=` attribute before `'unsafe-inline'` can
come out of `script-src`. Inline `<style>` tags are not part of this migration:
`style-src` retains `'unsafe-inline'` permanently (see Nonce mode section).
The nonce primitives are in place (`CactiSecureHeaders::getNonce()`,
`::getNonceAttribute()`); new code should use them and existing inline
`<script>` tags get converted as their pages are touched.

## Nonce mode

The config option `content_security_policy_script` controls how inline scripts
and styles are handled. It accepts four values:

- Empty string (default): legacy mode with `'unsafe-inline'` in both
  `script-src` and `style-src`. Existing inline tags work without change.
- `unsafe-eval`: same as empty, plus `'unsafe-eval'` in `script-src` for
  plugins that need `eval()` or similar dynamic code execution.
- `nonce-report`: enables nonce-based CSP but delivers it via the
  `Content-Security-Policy-Report-Only` header. The page renders normally but
  the browser reports violations to the configured report URI without blocking
  any content. This mode is useful for testing nonce deployment before
  enforcement.
- `nonce`: enforcing nonce-based CSP. Every inline `<script>` must include a
  `nonce` attribute matching the request nonce, or the browser blocks it.

In both nonce modes the `script-src` directive also carries `'strict-dynamic'`
and `'unsafe-eval'`. `'strict-dynamic'` lets a nonced page script transitively
trust scripts it inserts via DOM manipulation (jQuery `.html()`, `.append()`,
etc.), which would otherwise fail because injected `<script>` tags don't carry
a nonce. `'unsafe-eval'` covers jQuery's `globalEval` and `new Function()`
paths. Without these two keywords most jQuery-driven UIs and Cacti plugins
break under nonce mode. Browser support: Chrome 52+, Firefox 60+,
Safari 15.4+.

The `style-src` directive keeps `'unsafe-inline'` in nonce modes because
jQuery `.css()`, `setAttribute('style', ...)`, and the legacy inline `style=""`
attributes scattered across Cacti pages all rely on inline-style execution.
Style XSS is a much narrower attack surface than script XSS; the trade-off is
intentional and documented.

Plugin authors should check for nonce mode and emit the nonce attribute when
present:

```php
if (class_exists('CactiSecureHeaders') && CactiSecureHeaders::isNonceMode()) {
    echo '<script ' . CactiSecureHeaders::getNonceAttribute() . '>';
    // ... JavaScript code ...
    echo '</script>';
} else {
    echo '<script>';
    // ... JavaScript code ...
    echo '</script>';
}
```

The `class_exists` check keeps plugin code compatible with Cacti versions that
predate the `CactiSecureHeaders` class.

In `nonce-report` mode, configure CSP violation reporting via the
`content_security_report_uri` setting (which drives the `report-uri`
directive). `content_security_alternate_sources` controls source lists
like `script-src`, `style-src`, and `img-src`; it does not affect where
violation reports are sent. Reports are POSTed to `<url_path>/csp_report.php`
by default, where `<url_path>` is the value of `$url_path` in
`include/config.php` (root installs use `/csp_report.php`). The endpoint
accepts `application/csp-report` or `application/json` bodies up to 16 KB and logs each violation
via `cacti_log()` using the `CSP-REPORT` facility.

The public API for nonce handling:

- `CactiSecureHeaders::getNonce(): string`: returns a 24-character base64url
  token. The nonce is idempotent within a single request; calling it multiple
  times returns the same value.
- `CactiSecureHeaders::getNonceAttribute(): string`: returns the string
  `nonce="..."` ready for direct HTML emission.
- `CactiSecureHeaders::getCspMode(): string`: returns the normalized mode
  value (empty, `unsafe-eval`, `nonce-report`, or `nonce`).
- `CactiSecureHeaders::isNonceMode(): bool`: convenience method; returns true
  if the mode is `nonce` or `nonce-report`.

To rollback from nonce mode, switch `content_security_policy_script` back to
empty in the settings UI. The policy reverts to `'unsafe-inline'` immediately;
no code changes are needed.

The migration is incremental. The pilot phase has converted `logout.php` and
`permission_denied.php` to use nonces. The remaining ~180 inline tags across
the UI continue to work under the default (empty) mode. Each page adopts nonce
attributes as it gets touched for other changes. Plugins that emit inline
scripts will work under empty, `unsafe-eval`, and `nonce-report` modes, but
will break under `nonce` enforcement until the plugin calls
`getNonceAttribute()`.

## Static-file headers

PHP responses get the full header set. Static files (images, CSS, JS, and
`robots.txt`) are served by Apache/nginx without hitting PHP, so they only
carry whatever headers the web server adds.

For Apache deployments that don't manage config centrally, rename the
shipped `.htaccess.dist` to `.htaccess` at the project root. It applies
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and a
narrow `Content-Security-Policy` to static files.

For distros that install Cacti via `.deb` or `.rpm`, put the same
directives in `/etc/httpd/conf.d/cacti.conf` or `/etc/apache2/conf-available/`
rather than shipping a per-project `.htaccess`.

For nginx:

```
location /cacti/ {
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

## Testing CSP under plugin load

The Playwright harness in `tests/e2e/` brings up Cacti with two third-party
plugins from the Cacti project preinstalled — `plugin_thold` and
`plugin_monitor` — and walks each plugin's admin pages with
`content_security_policy_script=nonce-report`. Any inline `<script>` tag
a plugin emits without a request nonce produces a violation report to
`<url_path>/csp_report.php`; the harness captures those reports through a
Playwright network listener and fails the test with the violating directive
and source file named. Inline `<style>` tags and inline style attributes
are not covered by this nonce reporting because the policy keeps
`'unsafe-inline'` on `style-src` (style XSS is a much narrower attack
surface and jQuery `.css()` and the legacy inline `style=""` attributes
across Cacti pages depend on it).

Plugin sources are cloned into the PHP image at build time via the
`PLUGIN_THOLD_REPO`, `PLUGIN_THOLD_REF`, `PLUGIN_MONITOR_REPO`, and
`PLUGIN_MONITOR_REF` build args. Defaults track `develop-1.2.x` on both
plugins. The container entrypoint copies the cloned trees into
`plugins/<dir>` and seeds `plugin_config` rows with `status=1` (installed,
not active). Status 1 is enough for `plugins.php?plugin=<dir>` to render
the plugin's UI hooks, which is where most CSP-relevant inline tags
surface; full activation is intentionally not done because thold and
monitor's install hooks reach into rrdtool and poller paths the harness
does not seed.

To add another plugin to the matrix:

1. Extend `tests/e2e/Dockerfile` with a `git clone` for the plugin into
   `/opt/cacti-plugins/<dir>` plus matching `ARG` lines so CI can pin the
   ref.
2. Pass the build args from `tests/e2e/docker-compose.yml`.
3. Add a `plugin_config` insert and a stage-from-image copy in
   `tests/e2e/entrypoint.sh`.
4. Add a `PluginWalk` entry to `tests/e2e/tests/csp-plugins.spec.ts`
   listing the URLs the spec should walk. Start with
   `/plugins.php?plugin=<dir>`; add plugin-specific pages once you confirm
   they render with the harness's seeded state.

The harness scopes plugin coverage to "admin index loads with no CSP
violations" rather than full feature flows. A plugin that activates
cleanly under harness seeding can graduate to a deeper walk by adding more
URLs to its `PluginWalk.pages` array.

## Adding a new inline script

Don't. Put the JavaScript in an external file under
`include/themes/<theme>/` or `include/js/` and load it via
`get_md5_include_js($path)`.

If an inline tag is genuinely unavoidable, attach a nonce:

```php
echo '<script ' . CactiSecureHeaders::getNonceAttribute() . '>';
// ...
echo '</script>';
```

The nonce rotates per request; do not cache its value.
