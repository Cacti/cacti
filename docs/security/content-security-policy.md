# Content Security Policy Nonce Contract

This document defines the CSP nonce contract for Cacti core, themes, and plugins.

## Core behavior

- Cacti now generates one request-scoped CSP nonce.
- The nonce is added to the `script-src` directive together with existing compatibility allowances.
- Core helpers that emit parser-inserted script tags now attach that nonce automatically:
	- `get_md5_include_js()`
	- `htmx_script_tag()`

## Plugin and theme contract

Plugins and themes that emit script tags must do one of the following:

- Prefer Cacti helper emitters such as `get_md5_include_js()` for external scripts.
- If emitting script tags directly, append `cacti_csp_nonce_attribute()` to each parser-inserted `<script>` tag.

Inline event handler attributes (for example `onclick=`, `onchange=`, `onsubmit=`) should be replaced with registered or delegated JavaScript event listeners.

## Migration guidance

- Use data attributes (for example `data-load-url`) as markup hooks.
- Bind behavior from shared JavaScript using `.on(...)` listeners.
- Keep plugin/theme workflows running under CSP report-only mode while migrating remaining handlers and inline script emitters.
