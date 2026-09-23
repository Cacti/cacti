## Upgrading to v3

- `$diffOptions` removes: `charLevelDiff` and `separateBlock`.
- `$templateOptions` adds: `detailLevel` (similar to `charLevelDiff`, read docs) and `separateBlock` (exact the same one in `$diffOptions`).
- `Jfcherng\Diff\Diff`'s `$a` (`$old`), `$b` (`$new`) are required in `__construct()`. (You may pass two empty arrays if you do not want to do anything at that moment.)
- The look of "skipped" block in HTML renderers (`SideBySide` and `Inline`) have been changed. (You may have to tweak your CSS.)
