## Upgrading to v4

- `Jfcherng\Diff\Utility\SequenceMatcher` becomes [a new package](https://packagist.org/packages/jfcherng/php-sequence-matcher) by the namespace of `Jfcherng\Diff\SequenceMatcher`.
- Factories under `Jfcherng\Diff\Utility\` are moved to `Jfcherng\Diff\Factory\`. For example, `Jfcherng\Diff\Utility\RendererFactory` is now `Jfcherng\Diff\Factory\RendererFactory`.
- Non-abstract classes are no longer inheritable as they are added with `final` keywords. (This allows me to do more internal changes without causing possible BC breaks.)
