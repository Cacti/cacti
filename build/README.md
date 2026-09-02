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

All third-party JavaScript libraries are npm-sourced (generated, git-ignored):
jquery, jquery-ui, jstree, billboard.js, dompurify, pace-js, the tablesorter files
(core, widgets, pager) and the jquery-validation files.

`pace-js` carries a one-line local fix (a stray regex alternation removed) applied
through `patch-package` from `patches/pace-js+1.2.4.patch`. The fix should also be
sent upstream so the patch can eventually be dropped.

Only Cacti's own themed CSS for jquery-ui and jstree remains committed; those are
Cacti theme assets, not upstream library files.

## Release and source-tree checkouts

Because the generated files are no longer committed, `npm ci` must run before a
release tarball is assembled and after a fresh `git clone`. The release pipeline
must invoke `npm ci` (which runs the build via `postinstall`).
