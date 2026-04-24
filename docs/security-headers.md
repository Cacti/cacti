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
~180 inline `<script>` / `<style>` tags across the UI. Each has to be
migrated to either an external file or a `nonce=` attribute before
`'unsafe-inline'` can come out of the policy. The nonce primitives are in
place (`CactiSecureHeaders::getNonce()`, `::getNonceAttribute()`); new code
should use them and existing inline tags get converted as their pages are
touched.

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

## Adding a new inline script

Don't. Put the JavaScript in an external file under
`include/themes/<theme>/` or `include/js/` and load it via
`get_md5_include_js($path)` (which now emits `integrity="sha384-..."` for
SRI automatically).

If an inline tag is genuinely unavoidable, attach a nonce:

```php
echo '<script ' . CactiSecureHeaders::getNonceAttribute() . '>';
// ...
echo '</script>';
```

The nonce rotates per request; do not cache its value.
