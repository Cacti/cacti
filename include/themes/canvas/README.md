# Canvas

A midwinter child theme whose light and dark palettes follow the functional
colors of GitHub's Primer design system: a subtle gray canvas, blue links and
focus, and a green primary action.

Only the palette, fonts and graph colors live here. Layout, behavior and every
other file come from `midwinter` through the `parent` file.

The parent is also named by the first `@import` in `main.css` and the
`include` in `rrdtheme.php`, which is how the browser and the graph code
inherit it. `tests/Unit/Ui/Html/ThemeChildImportsTest.php` keeps those in step
with `parent` and fails when an import's md5 cache buster goes stale, for
example after the parent's `main.css` changes.

The color values are taken from Primer's published primitives. Primer is
licensed under the MIT License. No GitHub logos or marks are used.

The font stack is the operating system's UI font, so nothing is bundled.
