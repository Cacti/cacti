# Daybreak

A midwinter child theme with two original palettes. The light palette,
Daybreak, pairs a strong blue with a red selection color on near-white panels.
The dark palette, Nocturne, is near-black with one amber accent for links,
focus and highlights; filled buttons and the selected menu item use a deeper
amber so their white text stays readable.

Only the palette, fonts and graph colors live here. Layout, behavior and every
other file come from `midwinter` through the `parent` file.

The parent is also named by the first `@import` in `main.css` and the
`include` in `rrdtheme.php`, which is how the browser and the graph code
inherit it. `tests/Unit/Ui/Html/ThemeChildImportsTest.php` keeps those in step
with `parent` and fails when an import's md5 cache buster goes stale, for
example after the parent's `main.css` changes.

The sans-serif stack prefers Barlow when it is installed and falls back to the
midwinter stack. Barlow is not bundled.
