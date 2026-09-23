
## VERSION 3  SUN RISE

 * Version **3.3** - JSON format language files
   * 2019-01-25 01:06  **3.3.0**  initial release
      * 395b802 Use JSON format language files
      * c299e39 Remove debug codes

 * Version **3.2** - add none-level diff
   * 2019-01-24 23:54  **3.2.6**  nits
      * 7d9ca9c Code tidy
      * ce5273a Code tidy
      * 42bdf83 Remove a unnecesary "else"
   * 2019-01-24 19:44  **3.2.5**  minify diff table block separator
      * 215f300 Change diff table block separator rendering
      * 08f3cf7 Remove extra empty lines when setting context to 0
   * 2019-01-24 15:32  **3.2.4**  nits
      * af51b33 Add more punctuations as word separators
      * 9e649d8 Fix typos
   * 2019-01-24 14:48  **3.2.3**  fix LineRendererFactory case-sensitive
      * 4825b96 Fix LineRendererFactory case-sensitive problem
   * 2019-01-24 14:21  **3.2.2**  nits
      * 4b74e4f PreservedConstantInterface -> ReservedConstantInterface
   * 2019-01-24 14:18  **3.2.1**  nits
      * d5206fb Refactor out AbstractHtml::renderChangedExtentByXXX()
   * 2019-01-24 12:49  **3.2.0**  initial release
      * 9e6e37a Add none-level diff
      * 5e05555 Add line-level diff to example.php
      * cdc19df Unroll if-conditions for ReverseIterator::fromArray()

 * Version **3.1** - word-level diff
   * 2019-01-22 15:32  **3.1.4**  fix renderer options
      * d7dbacf Fix render options
   * 2019-01-22 13:55  **3.1.3**  nist
      * 2d139a8 Code tidy
      * d54e4f1 Update deps
      * 9aeb234 Add ITERATOR_GET_KEY, ITERATOR_GET_BOTH for ReverseIterator
      * 11c7652 Code tidy
   * 2019-01-22 12:36  **3.1.2**  nits
      * 1bef1a0 Diff's $a, $b are now required in \__construct()
      * 22426a5 Add Diff::setAB()
   * 2019-01-22 12:05  **3.1.1**  fix ignoreCase/ignoreWhitespace
      * 300e4cf Fix ignoreCase/ignoreWhitespace for word/char-level diff
      * 9ea24dd Update readme
   * 2019-01-22 11:49  **3.1.0**  initial release
      * f6fbce8 Suppress phan errors
      * 6850cb5 Add word-level diff
      * aea5ff8 Fix demo.php

 * Version **3.0** - options changed
   * 2019-01-21 16:41  **3.0.0**  initial release
      * d7b6079 Clear up options
      * c9a43a1 Fix diff getInstance()
      * 1b99649 Code tidy
      * f5cd010 Update demo.php
