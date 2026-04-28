# MIME validation for upload sites

`lib/CactiMime.php` wraps `Symfony\Component\Mime\MimeTypes` with two
static methods, `detect()` and `validate()`, for use at every upload
boundary.

## When to use it

Apply `CactiMime::validate()` immediately after PHP has populated
`$_FILES['<field>']['tmp_name']` and before any of the following:

- `file_get_contents()` of the upload
- writing the upload to a temp file or session blob
- handing the path to an XML, ZIP, or signature parser
- any extension-only check on `$_FILES['<field>']['name']`

The first integrated site is `package_import.php :: form_save()`, which
gates `.xml.gz` package uploads.

## Defense in depth: necessary, not sufficient

Content-derived MIME inspection blocks the obvious mismatch case (a PHP
or shell payload renamed `import.zip`). It does not replace:

- path/traversal hardening on `$_FILES['<field>']['name']`
- bounds and structure checks on ZIP entries before extraction
  (zip-slip, oversized entries, name collisions)
- libxml entity loading controls when the payload is parsed as XML
  (XXE)
- signature verification, where present

Keep every existing check intact when adding `CactiMime::validate()`.

## libmagic dependency

Symfony's MIME guesser delegates to PHP's `finfo` extension, which
links libmagic. When `function_exists('finfo_open')` is false the
guesser falls back to extension-based matching, which defeats the
purpose of this gate.

`CactiMime::validate($path, $allowed, true)` (strict mode) returns
false in that environment so callers fail closed. The class also
emits one `cacti_log` warning per request when finfo is missing.

## Adding a new allowlisted upload site

1. Identify the upload handler (`$_FILES['<field>']['tmp_name']`).
2. Build an allowlist of canonical MIME strings the site genuinely
   accepts. Keep it short. Include both `application/x-gzip` and
   `application/gzip` if the site accepts gzip; libmagic versions
   disagree on which label to emit.
3. Call `CactiMime::validate($tmp, $allowed, true)` before any other
   handling. On false, surface an error via the same mechanism the
   surrounding code already uses (`raise_message` for web,
   `print_error` for CLI), then redirect or exit.
4. Add a regression test under `tests/Unit/` that exercises an
   extension/content mismatch against the new allowlist.

## Migration template

```php
require_once(CACTI_PATH_LIBRARY . '/CactiMime.php');

$allowed = ['application/zip']; // narrow to what this site really takes

if (!CactiMime::validate($_FILES['upload']['tmp_name'], $allowed, true)) {
    raise_message('upload_mime_reject', __('The uploaded file content does not match the accepted format.'), MESSAGE_LEVEL_ERROR);
    header('Location: ' . $self_url);
    exit;
}
```

The strict flag is the default for any site exposed to authenticated
users; the only reason to leave it off is a CLI or test path where
finfo absence is expected and the caller has its own fallback.
