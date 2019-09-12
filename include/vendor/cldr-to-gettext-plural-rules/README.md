[![Build Status](https://api.travis-ci.org/mlocati/cldr-to-gettext-plural-rules.svg?branch=master)](https://travis-ci.org/mlocati/cldr-to-gettext-plural-rules)
# gettext language list automatically generated from CLDR data


## Static usage

To use the languages data generated from this tool you can use the `bin/export-plural-rules` command.

#### Export command line options
`export-plural-rules` supports the following options:
- `--us-ascii`
  If specified, the output will contain only US-ASCII characters.
  If not specified, the output charset is UTF-8.
- `--languages=<LanguageId>[,<LanguageId>,...]]`
  `--language=<LanguageId>[,<LanguageId>,...]]`
  Export only the specified language codes.
  Separate languages with commas; you can also use this argument more than once; it's case insensitive and accepts both '_' and '-' as locale chunks separator (eg we accept `it_IT` as well as `it-it`).
  If this option is not specified, the result will contain all the available languages.
- `--reduce=yes|no`
  If set to yes the output won't contain languages with the same base language and rules.
  For instance `nl_BE` (`Flemish`) will be omitted because it's the same as `nl` (`Dutch`).
  Defaults to `no` if `--languages` is specified, to `yes` otherwise.
- `--output=<file name>`
  If specified, the output will be saved to <file name>. If not specified we'll output to standard output.

#### Export formats
`export-plural-rules` can generate data in the following formats:

- `json`: compressed JSON data
  ```bash
  export-plural-rules json
  ```

- `prettyjson`: uncompressed JSON data
  ```bash
  export-plural-rules prettyjson
  ```

- `html`: html table ([see the result](http://mlocati.github.io/cldr-to-gettext-plural-rules/))
  ```bash
  export-plural-rules html
  ```

- `php`: build a php file that can be included
  ```bash
  export-plural-rules php > yourfile.php
  ```
  Then you can use that generated file in your php scripts:
  ```php
  $languages = include 'yourfile.php';
  ```

- `xml`: generate an XML document ([here you can find the xsd XML schema](http://mlocati.github.io/cldr-to-gettext-plural-rules/GettextLanguages.xsd))
  ```bash
  export-plural-rules xml
  ```

- `po`: generate the gettext .po headers for a single language
  ```bash
  export-plural-rules po --language=YourLanguageCode
  ```


## Dynamic usage

#### With Composer
You can use [Composer](https://getcomposer.org/) to include this tool in your project.
Simply launch `composer require gettext/languages` or add `"gettext/languages": "*"` to the `"require"` section of your `composer.json` file.

#### Without Composer
If you don't use composer in your project, you can download this package in a directory of your project and include the autoloader file:
```php
require_once 'path/to/src/autoloader.php';
```

#### Main methods
The most useful functions of this tools are the following
```php
$allLanguages = Gettext\Languages\Language::getAll();
...
$oneLanguage = Gettext\Languages\Language::getById('en_US');
...
```
`getAll` returns a list of `Gettext\Languages\Language` instances, `getById` returns a single `Gettext\Languages\Language` instance (or `null` if the specified language identifier is not valid).

The main properties of the `Gettext\Languages\Language` instances are:
- `id`: the normalized language ID (for instance `en_US`)
- `name`: the language name (for instance `American English` for `en_US`)
- `supersededBy`: the code of a language that supersedes this language code (for instance, `jw` is superseded by `jv` to represent the Javanese language) 
- `script`: the script name (for instance, for `zh_Hans` - `Simplified Chinese` - the script is `Simplified Han`)
- `territory`: the name of the territory (for instance `United States` for `en_US`)
- `baseLanguage`: the name of the base language  (for instance `English` for `en_US`)
- `formula`: the [gettext formula](http://www.gnu.org/savannah-checkouts/gnu/gettext/manual/html_node/Plural-forms.html) to distinguish between different plural rules. For instance `n != 1` 
- `categories`: the plural cases applicable for this language. It's an array of `Gettext\Languages\Category` instances. Each instance has these properties:
  - `id`: can be (in this order) one of `zero`, `one`, `two`, `few`, `many` or `other`. The `other` case is always present.
  - `examples`: a representation of some values for which this plural case is valid (examples are simple numbers like `1` or complex ranges like `0, 2~16, 100, 1000, 10000, 100000, 1000000, …`)  

## Is this data correct?

Yes - as far as you trust the [Unicode CLDR](http://cldr.unicode.org) project.

The conversion from CLDR to gettext includes also [a lot of tests](https://travis-ci.org/mlocati/cldr-to-gettext-plural-rules) to check the results.
And all passes :wink:.



## Reference

#### CLDR

The [CLDR specifications](http://unicode.org/reports/tr35/tr35-numbers.html#Language_Plural_Rules) define the following variables to be used in the CLDR plural formulas:
- `n`: absolute value of the source number (integer and decimals) (eg: `9.870` => `9.87`)
- `i`: integer digits of n (eg: `9.870` => `9`)
- `v`: number of visible fraction digits in n, with trailing zeros (eg: `9.870` => `3`)
- `w`: number of visible fraction digits in n, without trailing zeros (eg: `9.870` => `2`)
- `f`: visible fractional digits in n, with trailing zeros (eg: `9.870` => `870`)
- `t`: visible fractional digits in n, without trailing zeros (eg: `9.870` => `87`)

#### gettext
The [gettext specifications](http://www.gnu.org/savannah-checkouts/gnu/gettext/manual/html_node/Plural-forms.html) define the following variables to be used in the gettext plural formulas:
- `n`: unsigned long int

### Conversion CLDR > gettext

| CLDR variable | gettext equivalent |
|---------------|--------------------|
| `n`           | `n`                |
| `i`           | `n`                |
| `v`           | `0`                |
| `w`           | `0`                |
| `f`           | *empty*            |
| `t`           | *empty*            |


## Parenthesis in ternary operators

The generated gettext formulas contain some extra parenthesis, in order to avoid problems in some programming language.
For instance, let's assume we have this formula:
`(0 == 0) ? 0 : (0 == 1) ? 1 : 2`
- [in C it evaluates to `0`](http://codepad.org/Epw5WkmJ) since is the same as `(0 == 0) ? 0 : ((0 == 1) ? 1 : 2)`
- [in Java it evaluates to `0`](https://ideone.com/vbRHjW) since is the same as `(0 == 0) ? 0 : ((0 == 1) ? 1 : 2)`
- [in JavaScript it evaluates to `0`](http://jsfiddle.net/7fnxa599/) since is the same as `(0 == 0) ? 0 : ((0 == 1) ? 1 : 2)`
- [in PHP it evaluates to `2`](http://3v4l.org/QAAnA) since is the same as `((0 == 0) ? 0 : (0 == 1)) ? 1 : 2`

So, in order to avoid problems, instead of a simple
`a ? 0 : b ? 1 : 2`
the resulting formulas will be in this format:
`a ? 0 : (b ? 1 : 2)`
