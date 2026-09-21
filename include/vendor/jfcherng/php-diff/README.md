# php-diff

[![GitHub Workflow Status (branch)](https://img.shields.io/github/actions/workflow/status/jfcherng/php-diff/php.yml?branch=v6&style=flat-square)](https://github.com/jfcherng/php-diff/actions)
[![Codacy grade](https://img.shields.io/codacy/grade/5b7ab5ed613d48b99f12cd334f6489ff/v6?style=flat-square)](https://app.codacy.com/project/jfcherng/php-diff/dashboard)
[![Packagist](https://img.shields.io/packagist/dt/jfcherng/php-diff?style=flat-square)](https://packagist.org/packages/jfcherng/php-diff)
[![Packagist Version](https://img.shields.io/packagist/v/jfcherng/php-diff?style=flat-square)](https://packagist.org/packages/jfcherng/php-diff)
[![Project license](https://img.shields.io/github/license/jfcherng/php-diff?style=flat-square)](https://github.com/jfcherng/php-diff/blob/v6/LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/jfcherng/php-diff?style=flat-square&logo=github)](https://github.com/jfcherng/php-diff/stargazers)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-blue.svg?style=flat-square&logo=paypal)](https://www.paypal.me/jfcherng/5usd)

A comprehensive library for generating diff between two strings.

## Introduction

Generated diff can be rendered in all of the standard formats including:

**Text** renderers:

- Context
- Json (plain text)
- Unified

**HTML** renderers:

- Combined
- Inline
- Json (HTML)
- Side by Side

Note that for HTML rendered results, you have to add CSS for a better visualization.
You may modify one from `example/diff-table.css` or write your own from zero.

If you are okay with the default CSS, there is `\Jfcherng\Diff\DiffHelper::getStyleSheet()`
which can be used to get the content of the `example/diff-table.css`.

## Requirements

![php](https://img.shields.io/badge/php-%E2%89%A57.4.0-blue?style=flat-square)
![ext-iconv](https://img.shields.io/badge/ext-iconv-brightgreen?style=flat-square)

## Installation

This package is available on `Packagist` by the name of [jfcherng/php-diff](https://packagist.org/packages/jfcherng/php-diff).

```bash
composer require jfcherng/php-diff
```

## Example

See files and readme in the [example/](https://github.com/jfcherng/php-diff/blob/v6/example) directory.

```php
<?php

include __DIR__ . '/vendor/autoload.php';

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;

$oldFile = __DIR__ . '/example/old_file.txt';
$newFile = __DIR__ . '/example/new_file.txt';

$old = 'This is the old string.';
$new = 'And this is the new one.';

// renderer class name:
//     Text renderers: Context, JsonText, Unified
//     HTML renderers: Combined, Inline, JsonHtml, SideBySide
$rendererName = 'Unified';

// the Diff class options
$differOptions = [
    // show how many neighbor lines
    // Differ::CONTEXT_ALL can be used to show the whole file
    'context' => 3,
    // ignore case difference
    'ignoreCase' => false,
    // ignore line ending difference
    'ignoreLineEnding' => false,
    // ignore whitespace difference
    'ignoreWhitespace' => false,
    // if the input sequence is too long, it will just gives up (especially for char-level diff)
    'lengthLimit' => 2000,
    // if truthy, when inputs are identical, the whole inputs will be rendered in the output
    'fullContextIfIdentical' => false,
];

// the renderer class options
$rendererOptions = [
    // how detailed the rendered HTML in-line diff is? (none, line, word, char)
    'detailLevel' => 'line',
    // renderer language: eng, cht, chs, jpn, ...
    // or an array which has the same keys with a language file
    // check the "Custom Language" section in the readme for more advanced usage
    'language' => 'eng',
    // show line numbers in HTML renderers
    'lineNumbers' => true,
    // show a separator between different diff hunks in HTML renderers
    'separateBlock' => true,
    // show the (table) header
    'showHeader' => true,
    // the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
    // but if you want to visualize them in the backend with "&nbsp;", you can set this to true
    'spacesToNbsp' => false,
    // HTML renderer tab width (negative = do not convert into spaces)
    'tabSize' => 4,
    // this option is currently only for the Combined renderer.
    // it determines whether a replace-type block should be merged or not
    // depending on the content changed ratio, which values between 0 and 1.
    'mergeThreshold' => 0.8,
    // this option is currently only for the Unified and the Context renderers.
    // RendererConstant::CLI_COLOR_AUTO = colorize the output if possible (default)
    // RendererConstant::CLI_COLOR_ENABLE = force to colorize the output
    // RendererConstant::CLI_COLOR_DISABLE = force not to colorize the output
    'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
    // this option is currently only for the Json renderer.
    // internally, ops (tags) are all int type but this is not good for human reading.
    // set this to "true" to convert them into string form before outputting.
    'outputTagAsString' => false,
    // this option is currently only for the Json renderer.
    // it controls how the output JSON is formatted.
    // see available options on https://www.php.net/manual/en/function.json-encode.php
    'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
    // this option is currently effective when the "detailLevel" is "word"
    // characters listed in this array can be used to make diff segments into a whole
    // for example, making "<del>good</del>-<del>looking</del>" into "<del>good-looking</del>"
    // this should bring better readability but set this to empty array if you do not want it
    'wordGlues' => [' ', '-'],
    // change this value to a string as the returned diff if the two input strings are identical
    'resultForIdenticals' => null,
    // extra HTML classes added to the DOM of the diff container
    'wrapperClasses' => ['diff-wrapper'],
];

// one-line simply compare two files
$result = DiffHelper::calculateFiles($oldFile, $newFile, $rendererName, $differOptions, $rendererOptions);
// one-line simply compare two strings
$result = DiffHelper::calculate($old, $new, $rendererName, $differOptions, $rendererOptions);
// or even shorter if you are happy with default options
$result = DiffHelper::calculate($old, $new, $rendererName);

// custom usage
$differ = new Differ(explode("\n", $old), explode("\n", $new), $differOptions);
$renderer = RendererFactory::make($rendererName, $rendererOptions); // or your own renderer object
$result = $renderer->render($differ);

// use the JSON result to render in HTML
$jsonResult = DiffHelper::calculate($old, $new, 'Json'); // may store the JSON result in your database
$htmlRenderer = RendererFactory::make('Inline', $rendererOptions);
$result = $htmlRenderer->renderArray(json_decode($jsonResult, true));
```

## Rendered Results

### HTML Diff In-line Detailed Rendering

<table>
  <tr>
    <th>None-level</th>
    <th>Line-level (Default)</th>
  </tr>
  <tr>
    <td><img src="https://raw.githubusercontent.com/jfcherng/php-diff/v6/example/images/inline-none-level-diff.png"></td>
    <td><img src="https://raw.githubusercontent.com/jfcherng/php-diff/v6/example/images/inline-line-level-diff.png"></td>
  </tr>
  <tr>
    <th>Word-level</th>
    <th>Char-level</th>
  </tr>
  <tr>
    <td><img src="https://raw.githubusercontent.com/jfcherng/php-diff/v6/example/images/inline-word-level-diff.png"></td>
    <td><img src="https://raw.githubusercontent.com/jfcherng/php-diff/v6/example/images/inline-char-level-diff.png"></td>
  </tr>
</table>

### Renderer: Inline

```php
<?php $rendererOptions = ['detailLevel' => 'line'];
```

![Inline](https://raw.githubusercontent.com/jfcherng/php-diff/v6/example/images/inline-renderer.png)

### Renderer: Side By Side

```php
<?php $rendererOptions = ['detailLevel' => 'line'];
```

![Side By Side](https://raw.githubusercontent.com/jfcherng/php-diff/v6/example/images/side-by-side-renderer.png)

### Renderer: Combined

```php
<?php $rendererOptions = ['detailLevel' => 'word'];
```

This renderer is suitable for articles and always has no line number information.

![Combined](https://raw.githubusercontent.com/jfcherng/php-diff/v6/example/images/combined-renderer-word-level.png)

### Renderer: Unified

About the `Unified` diff format: https://en.wikipedia.org/wiki/Diff#Unified_format

```diff
@@ -1,3 +1,4 @@
-<p>Hello World!</p>
+<div>Hello World!</div>
 ~~~~~~~~~~~~~~~~~~~
+Let's add a new line here.
 X
@@ -7,6 +8,5 @@
 N
-Do you know in Chinese, "金槍魚罐頭" means tuna can.
+Do you know in Japanese, "魚の缶詰" means fish can.
 This is just a useless line.
 G
-// remember to delete this line
 Say hello to my neighbors.
```

### Renderer: Context

About the `Context` diff format: https://en.wikipedia.org/wiki/Diff#Context_format

<details><summary>Click to expand</summary>

```diff
***************
*** 1,3 ****
! <p>Hello World!</p>
  ~~~~~~~~~~~~~~~~~~~
  X
--- 1,4 ----
! <div>Hello World!</div>
  ~~~~~~~~~~~~~~~~~~~
+ Let's add a new line here.
  X
***************
*** 7,12 ****
  N
! Do you know in Chinese, "金槍魚罐頭" means tuna can.
  This is just a useless line.
  G
- // remember to delete this line
  Say hello to my neighbors.
--- 8,12 ----
  N
! Do you know in Japanese, "魚の缶詰" means fish can.
  This is just a useless line.
  G
  Say hello to my neighbors.
```

</details>

### Renderer: Text JSON

This renderer has no detailed diff.

<details><summary>Click to expand</summary>

```json
[
  [
    {
      "tag": "rep",
      "old": {
        "offset": 0,
        "lines": ["<p>Hello World! Good-looking.</p>"]
      },
      "new": {
        "offset": 0,
        "lines": ["<div>Hello World! Bad-tempered.</div>"]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 1,
        "lines": ["~~~~~~~~~~~~~~~~~~~"]
      },
      "new": {
        "offset": 1,
        "lines": ["~~~~~~~~~~~~~~~~~~~"]
      }
    },
    {
      "tag": "ins",
      "old": {
        "offset": 2,
        "lines": []
      },
      "new": {
        "offset": 2,
        "lines": ["Let's add a new line here."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 2,
        "lines": ["X"]
      },
      "new": {
        "offset": 3,
        "lines": ["X"]
      }
    }
  ],
  [
    {
      "tag": "eq",
      "old": {
        "offset": 6,
        "lines": ["N"]
      },
      "new": {
        "offset": 7,
        "lines": ["N"]
      }
    },
    {
      "tag": "rep",
      "old": {
        "offset": 7,
        "lines": ["Do you know in Chinese, \"金槍魚罐頭\" means tuna can."]
      },
      "new": {
        "offset": 8,
        "lines": ["Do you know in Japanese, \"魚の缶詰\" means fish can."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 8,
        "lines": ["\t  \tTab visualization test.", "G"]
      },
      "new": {
        "offset": 9,
        "lines": ["\t  \tTab visualization test.", "G"]
      }
    },
    {
      "tag": "del",
      "old": {
        "offset": 10,
        "lines": ["// remember to delete this line"]
      },
      "new": {
        "offset": 11,
        "lines": []
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 11,
        "lines": ["Say hello to my neighbors."]
      },
      "new": {
        "offset": 11,
        "lines": ["Say hello to my neighbors."]
      }
    }
  ],
  [
    {
      "tag": "eq",
      "old": {
        "offset": 14,
        "lines": ["B"]
      },
      "new": {
        "offset": 14,
        "lines": ["B"]
      }
    },
    {
      "tag": "rep",
      "old": {
        "offset": 15,
        "lines": ["Donec rutrum."]
      },
      "new": {
        "offset": 15,
        "lines": ["Donec rutrum test.", "There is a new inserted line."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 16,
        "lines": ["C"]
      },
      "new": {
        "offset": 17,
        "lines": ["C"]
      }
    },
    {
      "tag": "rep",
      "old": {
        "offset": 17,
        "lines": ["Sed dictum lorem ipsum."]
      },
      "new": {
        "offset": 18,
        "lines": ["Sed dolor lorem ipsum hendrerit."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 18,
        "lines": [""]
      },
      "new": {
        "offset": 19,
        "lines": [""]
      }
    }
  ]
]
```

</details>

### Renderer: HTML JSON

For a `"tag": "rep" (8)` block, this renderer has HTML-style detailed diff.
If you don't need those detailed diff, consider using the `JsonText` renderer.

<details><summary>Click to expand</summary>

```json
[
  [
    {
      "tag": "rep",
      "old": {
        "offset": 0,
        "lines": ["&lt;<del>p&gt;Hello World! Good-looking.&lt;/p</del>&gt;"]
      },
      "new": {
        "offset": 0,
        "lines": ["&lt;<ins>div&gt;Hello World! Bad-tempered.&lt;/div</ins>&gt;"]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 1,
        "lines": ["~~~~~~~~~~~~~~~~~~~"]
      },
      "new": {
        "offset": 1,
        "lines": ["~~~~~~~~~~~~~~~~~~~"]
      }
    },
    {
      "tag": "ins",
      "old": {
        "offset": 2,
        "lines": [""]
      },
      "new": {
        "offset": 2,
        "lines": ["Let's add a new line here."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 2,
        "lines": ["X"]
      },
      "new": {
        "offset": 3,
        "lines": ["X"]
      }
    }
  ],
  [
    {
      "tag": "eq",
      "old": {
        "offset": 6,
        "lines": ["N"]
      },
      "new": {
        "offset": 7,
        "lines": ["N"]
      }
    },
    {
      "tag": "rep",
      "old": {
        "offset": 7,
        "lines": ["Do you know in <del>Chinese, \"金槍魚罐頭\" means tuna</del> can."]
      },
      "new": {
        "offset": 8,
        "lines": ["Do you know in <ins>Japanese, \"魚の缶詰\" means fish</ins> can."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 8,
        "lines": ["\t  \tTab visualization test.", "G"]
      },
      "new": {
        "offset": 9,
        "lines": ["\t  \tTab visualization test.", "G"]
      }
    },
    {
      "tag": "del",
      "old": {
        "offset": 10,
        "lines": ["// remember to delete this line"]
      },
      "new": {
        "offset": 11,
        "lines": [""]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 11,
        "lines": ["Say hello to my neighbors."]
      },
      "new": {
        "offset": 11,
        "lines": ["Say hello to my neighbors."]
      }
    }
  ],
  [
    {
      "tag": "eq",
      "old": {
        "offset": 14,
        "lines": ["B"]
      },
      "new": {
        "offset": 14,
        "lines": ["B"]
      }
    },
    {
      "tag": "rep",
      "old": {
        "offset": 15,
        "lines": ["Donec rutrum."]
      },
      "new": {
        "offset": 15,
        "lines": ["Donec rutrum test.", "There is a new inserted line."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 16,
        "lines": ["C"]
      },
      "new": {
        "offset": 17,
        "lines": ["C"]
      }
    },
    {
      "tag": "rep",
      "old": {
        "offset": 17,
        "lines": ["Sed d<del>ictum lorem ipsum</del>."]
      },
      "new": {
        "offset": 18,
        "lines": ["Sed d<ins>olor lorem ipsum hendrerit</ins>."]
      }
    },
    {
      "tag": "eq",
      "old": {
        "offset": 18,
        "lines": [""]
      },
      "new": {
        "offset": 19,
        "lines": [""]
      }
    }
  ]
]
```

</details>

## Custom Language

### Override an Existing Language

If you just want to override some translations of an existing language...

```php
$rendererOptions = [
  'language' => [
    // use English as the base language
    'eng',
    // your custom overrides
    [
      // use "Diff" as the new value of the "differences" key
      'differences' => 'Diff',
    ],
    // maybe more overrides if you somehow need them...
  ],
]
```

## Acknowledgment

This package is built on the top of [chrisboulton/php-diff](https://github.com/chrisboulton/php-diff) initially.
But the original repository looks like no longer maintained.
Here have been quite lots of rewrites and new features since then, hence I re-started this as a new package for better visibility.
