# Front-end JavaScript build

The third-party JavaScript libraries under `include/js/` are pinned in the
repository-root `package.json` and sourced from npm so that Dependabot can track
them and raise security alerts.

Unlike the `develop` branch, the 1.2.x branch keeps the generated files under
`include/js/` committed to the tree, so a source checkout works without running
npm. When a pinned version changes (for example from a Dependabot pull request),
regenerate and commit the affected asset:

```
npm ci             # installs the pinned versions and runs the build (postinstall)
npm run build:js   # or run the sync explicitly
```

`build/sync-js.mjs` copies the upstream dist files into `include/js/`.

## What is managed here

jstree, billboard.js, d3, DOMPurify, pace-js, screenfull, select2 and the
tablesorter files (core, widgets, pager) are copied from npm by `sync-js.mjs`.

`pace-js` and `tablesorter` each carry a one-line local fix applied through
`patch-package` from `patches/pace-js+1.2.4.patch` and
`patches/tablesorter+2.32.0.patch`.

`screenfull` is pinned to 5.2.0, the last non-ESM (UMD) release, so it loads as a
classic `<script>` without transpilation.

`jquery` is pinned in `package.json` only so npm can resolve the select2/jstree
peer dependency; its `include/js/jquery.js` is hand-maintained on the committed
3.7.1 build and is not regenerated here. `jquery-ui` is intentionally not npm
managed because the committed `include/js/jquery-ui.js` keeps a
`$.uiBackCompat = true;` build that the upstream dist omits.

Only Cacti's own themed CSS for jquery-ui and jstree remains hand-maintained;
those are Cacti theme assets, not upstream library files.
