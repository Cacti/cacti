
## VERSION 4  AT NOON

 * Version **4.2** - helper methods
   * 2019-03-03 06:36  **4.2.3**  tiny fixes
      * c3d1512 Make Language::getTranslationsByLanguage() safer
      * df4488e Fix a typo
      * 8a41e9a Use native class name
      * fa48647 Fix a typo in readme
   * 2019-03-03 04:03  **4.2.2**  final release for v4
      * 86e32c0 Adapt some non-breaking changes from the master branch
      * 35c1303 nits
      * 637e07e Update deps
      * 80c1861 Move CHANGELOG and UPGRADING
   * 2019-02-27 01:46  **4.2.1**  fix typo
      * 37f7f75 Fix DiffHelp::getTemplatesInfo() typos
      * 3a3c276 Revise phpdoc
      * c221c25 Add (private) Diff::finalize() to help maintain cache vadility
   * 2019-02-26 14:49  **4.2.0**  initial release
      * 43566f1 php-cs-fixer
      * be70f5e Add DiffHelper::getProjectDirectory()
      * 4937417 Add INFO['type'] for renderers
      * 3ef51f6 Rewrite DiffHelper::getTemplatesInfo() and DiffHelper::getStyleSheet()
      * 5e0f0da Add DiffHelper::getStyleSheet()
      * c7e5312 require-dev at least phpunit/phpunit 7.5
      * 01d52fd Add DiffHelper::calculateFiles()
      * 4601400 No need to clear cached value if $old/$new does not change
      * d690144 Code tidy

 * Version **4.1** - Allow negative tabSize
   * 2019-02-22 02:51  **4.1.9**  fix Json renderer with empty changes
      * d842546 Fix Json renderer with empty changes
   * 2019-02-22 02:38  **4.1.8**  fix dep
      * c2fb459 squizlabs/php_codesniffer should be in require-dev
   * 2019-02-22 02:16  **4.1.7**  fix them
      * 88ff257 Update example/old_file.txt and the new one.
      * 32537b9 Fix AbstractHtml::expandTabs() $tabSize undeclared
      * 37bd345 Fix problem caused by in OP_EQ, it still may be $old !== $new
      * a821017 AbstractHtml::expandTabs() add argument: $onlyLeadingTabs
      * bd00670 Code tidy
      * 2031306 Update readme
      * 0637909 Move upgrading guide from readme to other files
      * 5e932e6 Separate CHANGELOG by major version
   * 2019-02-21 19:50  **4.1.5**  cs tweak
      * 1464d86 Update .rmt.yml
      * b3b0595 Code tidy
      * 7caae81 Update readme
      * 347935c Update deps
      * 6f8e9dd Uniform the term "$a, $b, from, to" into "old, new"
      * f7679ff Code tidy
      * 159d244 nits
      * 76675e4 Tiny regex performance increasement
      * 42eeff7 Fix 120 chars per line
      * 86f2325 Introduce squizlabs/php_codesniffer
   * 2019-02-20 18:49  **4.1.4**  Fix Diff::getText()
      * 540daf5 Fix potential boundary error in Diff::getText()
      * 2b723e7 Revert "Inline and remove Diff::getText()"
   * 2019-02-20 16:45  **4.1.3**  Fix Diff::getText()
      * a5171c8 Inline and remove Diff::getText()
      * be52d42 Fix Diff::getText() when $end is null
      * 3a6259a Code tidy
      * 1fd7935 Update option comments
   * 2019-02-20 15:04  **4.1.2**  update deps
      * df25c88 Update jfcherng/php-sequence-matcher ^2.0
      * 0c7b425 Update readme
   * 2019-02-19 19:36  **4.1.1**  fix some HTML templates
      * 0ed0e53 Fix HTML renderer should not emphasize inserted/deleted lines
      * 6e97b8a Update output example in readme
      * 80db0a0 Fix HTML special chars in JSON renderer should be escaped
      * 09a405e Code tidy
      * 6be0a0e Update deps
      * cf5bff2 AbstractHtml::expandTabs() has a default $tabSize = 4
   * 2019-02-16 18:36  **4.1.0**  initial release
      * 53e6b17 Allow renderer option tabSize < 0
      * e60fb5c Remove useless codes
      * bf85310 Add more punctuations for word-level line renderer
      * 847b795 Update readme
      * ba77110 nits
      * b13149a Update deps

 * Version **4.0** - Strip out the SequenceMatcher
   * 2019-02-08 20:17  **4.0.1**  nits
      * 8f11cb1 Fix a typo
      * 54351a6 Move factory classes to Jfcherng\Diff\Factory\
      * 6d68583 Merge ReservedConstantInterface into RendererConstant
   * 2019-02-08 10:04  **4.0.0**  initial release
      * fac4dae nits
      * b6fa08d Strip out SequenceMatcher as an external package
      * 2349bb2 Demo uses "font-family: monospace"
      * ed3f47c Temporary fix for CI
      * 538f270 Update tests
      * 11cf372 Fix typos
      * d56ad7e Use native exceptions
      * 67351d1 Update deps
      * 8b14dae Add "final" to classes non-inherited
      * ee6e5a3 Add UTF-8-ready demo
      * bf686a2 Prepare for being a new package
      * 04fb079 nits