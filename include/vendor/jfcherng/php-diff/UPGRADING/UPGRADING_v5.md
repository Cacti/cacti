## Upgrading to v5

- Names involving `a, b`, `from, to`, `base, changed` have been renamed to `old, new` for consistency.
  Here's some examples:

  - `Diff::setAB()` becomes `Diff::setOldNew()`.
  - `Diff::setA()` becomes `Diff::setOld()`.
  - `Diff::setB()` becomes `Diff::setNew()`.
  - `Diff::getA()` becomes `Diff::getOld()`.
  - `Diff::getB()` becomes `Diff::getNew()`.
  - `base`, `changed` keys in the result of the `Json` renderer have become `old`, `new`.

- In the result of HTML renderers, classes of rows of line numbers has been changed.
  You may have to change your CSS if you have some customized things depend on these.

  - `<th class="f-num">` (from-number) becomes `<th class="n-new">` (number-new).
  - `<th class="t-num">` (to-number) becomes `<th class="n-old">` (number-old).

- The `tag` (sometimes called `op`) in `Json` template is now in `int` form by default.
  To get previous behavior, set the renderer option `outputTagAsString` to `true`.

- The `tag` (sometimes called `op`) in `Diff::getGroupedOpcodes()`'s results are now in `int` form.
  The corresponding meaning could be found in
  [jfcherng/php-sequence-matcher](https://github.com/jfcherng/php-sequence-matcher/blob/3.0.0/src/SequenceMatcher.php#L16-L26).
