# CSP Nonce Migration

Cacti's nonce Content Security Policy (CSP) has separate observation and
enforcement stages. Do not enable enforcement until the report-only violation
inventory is empty for the installed core, themes, and plugins.

## Enable report-only migration

1. Open **Console > Configuration > Settings > General**.
2. Expand **Site Security**.
3. Set **Inline JavaScript Protection** to
   **Nonce Migration (Report Only)**.
4. Optionally set **CSP Violation Report URI**. Leaving it empty uses Cacti's
   bundled `csp_report.php` endpoint.
5. Save, exercise the UI, and review CSP violation reports.

Report-only mode continues enforcing the compatible `unsafe-inline` policy. It
also sends a strict nonce policy through the
`Content-Security-Policy-Report-Only` header, so violations are observable
without breaking pages.

## Migrate violations

Every parser-inserted `<script>` element, including elements with `src`, must
carry the request nonce:

```php
<script type='text/javascript' <?php print CactiSecureHeaders::getNonceAttribute(); ?>>
```

Replace `onclick`, `onchange`, and other event attributes with event listeners
registered by a nonced script or a nonced external JavaScript file. Prefer
delegated listeners for elements replaced through AJAX.

Use this source inventory as a starting point:

```sh
rg -n -i --glob '*.php' --glob '*.html' \
	'<script\b|\bon[a-z]+\s*=|javascript\s*:'
```

An empty text scan is necessary but not sufficient. Exercise authentication,
console and graph pages, every installed theme, and every enabled plugin while
collecting report-only violations.

## Enable enforcement

Enforcement has two independent gates so it cannot be activated by a settings
change alone.

First add this explicit opt-in to `include/config.php`:

```php
define('CACTI_CSP_NONCE_ENFORCE', true);
```

Then select **Nonce Enforcement (Advanced)** under **Inline JavaScript
Protection**. Without the constant, that selection safely behaves as
report-only migration.

After enabling enforcement, verify that responses contain a nonce-bearing
`Content-Security-Policy` header and do not contain a
`Content-Security-Policy-Report-Only` header. Keep monitoring reports and be
prepared to remove the constant if an installed plugin still emits legacy
script markup.

The enforcement policy currently retains `unsafe-eval` for jQuery
compatibility. Removing it is a separate migration: inventory `eval`,
`new Function`, string-based timers, and jQuery global evaluation paths before
tightening the policy.
