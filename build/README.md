# Front-end JavaScript build

The JavaScript libraries listed in the repository-root `package.json` are managed
through npm so that Dependabot can track them and raise security alerts. Their
built files under `include/js/` are generated, not committed.

## Building

```
npm ci        # installs the pinned versions and runs the build (postinstall)
npm run build:js   # or run the sync explicitly
```

`build/sync-js.mjs` copies the upstream dist files into `include/js/`.

## What is managed here

`assetMap` in `build/sync-js.mjs` lists the npm-sourced, generated files. It
includes the main UI libraries, Chart.js, Touch Punch, Moment, daterangepicker,
mark.js, and hotkeys-js. The latter libraries retain their existing public
paths under `include/js/vendor/`. Moment is pinned to a patched release;
mark.js uses the published 8.11.1 release rather than the old copy marked 9.0.0.

The copy transforms preserve Pace's prototype-key rejection and body-class
fix, rewrite screenfull's ESM export, and replace removed jQuery APIs in
tablesorter's core, widgets, and minified pager. No npm package source is
modified. Unexpected Pace or screenfull source formats fail the build so an
update cannot silently discard the fixes.

`build/legacy-js.json` lists the remaining Cacti-maintained adapters and their
compatibility requirements. They are not interchangeable with upstream
distributions. The inventory test covers every root browser script and every
static JavaScript dependency in the shared header.

Only Cacti's own themed CSS for jquery-ui and jstree remains committed; those are
Cacti theme assets, not upstream library files.

`include/themes/<theme>/select2.css` is the unmodified upstream Select2 CSS,
committed (not npm-synced) per-theme so it loads alongside each theme's other
vendor CSS files; it should be refreshed by hand whenever the `select2` version
in `package.json` changes. Cacti's own Select2-to-theme adapter rules live in
each theme's `main.css` instead.

## Release and source-tree checkouts

Because generated files may be absent or stale in a source checkout, `npm ci` must run before a
release tarball is assembled and after a fresh `git clone`. The release pipeline
must invoke `npm ci` (which runs the build via `postinstall`).
