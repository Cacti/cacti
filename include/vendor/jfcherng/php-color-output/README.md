# php-color-output

[![GitHub Workflow Status (branch)](https://img.shields.io/github/workflow/status/jfcherng/php-color-output/Main/master?style=flat-square)](https://github.com/jfcherng/php-color-output/actions)
[![Packagist](https://img.shields.io/packagist/dt/jfcherng/php-color-output?style=flat-square)](https://packagist.org/packages/jfcherng/php-color-output)
[![Packagist Version](https://img.shields.io/packagist/v/jfcherng/php-color-output?style=flat-square)](https://packagist.org/packages/jfcherng/php-color-output)
[![Project license](https://img.shields.io/github/license/jfcherng/php-color-output?style=flat-square)](https://github.com/jfcherng/php-color-output/blob/master/LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/jfcherng/php-color-output?style=flat-square&logo=github)](https://github.com/jfcherng/php-color-output/stargazers)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-blue.svg?style=flat-square&logo=paypal)](https://www.paypal.me/jfcherng/5usd)

![demo.gif](https://github.com/jfcherng/php-color-output/blob/gh-pages/images/demo.gif?raw=true)

The above screenshot is the output of `demo.php`. See the [Example](#example) section.


## Installation

```text
composer require jfcherng/php-color-output
```


## Available Styles

| Background   | Foreground   | Compound       | Special   | Alias         |
| ---          | ---          | ---            | ---       | ---           |
| b_black      | f_black      | f_dark_gray    | blink     | b (bold)      |
| b_blue       | f_blue       | f_light_blue   | bold      | blk (blink)   |
| b_cyan       | f_brown      | f_light_cyan   | dim       | h (hidden)    |
| b_green      | f_cyan       | f_light_green  | hidden    | rev (reverse) |
| b_light_gray | f_green      | f_light_purple | reset     | rst (reset)   |
| b_magenta    | f_light_gray | f_light_red    | reverse   | u (underline) |
| b_red        | f_normal     | f_white        | underline | -             |
| b_yellow     | f_purple     | f_yellow       | -         | -             |
| -            | f_red        | -              | -         | -             |


## Functions and Methods

```php
<?php

/**
 * Make a string colorful.
 *
 * @param string          $str       the string
 * @param string|string[] $colors    the colors
 * @param bool            $reset     reset color at the end of the string?
 *
 * @return string the colored string
 */
\Jfcherng\Utility\CliColor::color(string $str, $colors = [], bool $reset = true): string

/**
 * Remove all colors from a string.
 *
 * @param string $str the string
 *
 * @return string the string without colors
 */
\Jfcherng\Utility\CliColor::noColor(string $str): string
```


## Example

```php
<?php

include __DIR__ . '/vendor/autoload.php';

use \Jfcherng\Utility\CliColor;

// colors in a string using a comma as the delimiter
echo CliColor::color('foo', 'f_light_cyan, b_yellow');  // "\033[1;36;43mfoo\033[0m"

echo PHP_EOL;

// colors in an array
echo CliColor::color('foo', ['f_white', 'b_magenta']); // "\033[1;37;45mfoo\033[0m"

echo PHP_EOL;

// do not auto reset color at the end of string
echo CliColor::color('foo', ['f_red', 'b_green', 'b', 'blk'], false); // "\033[31;42;1;5mfoo"

// manually add color reset
echo CliColor::color('', 'reset'); // "\033[0m"

echo PHP_EOL;

// remove all color codes from a string
echo CliColor::noColor("\033[31;42;5mfoo\033[0mbar"); // "foobar"

echo PHP_EOL;
```
