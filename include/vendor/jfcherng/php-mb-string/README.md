# php-mb-string

[![GitHub Workflow Status (branch)](https://img.shields.io/github/actions/workflow/status/jfcherng/php-mb-string/php.yml?branch=master&style=flat-square)](https://github.com/jfcherng/php-mb-string/actions)
[![Packagist](https://img.shields.io/packagist/dt/jfcherng/php-mb-string?style=flat-square)](https://packagist.org/packages/jfcherng/php-mb-string)
[![Packagist Version](https://img.shields.io/packagist/v/jfcherng/php-mb-string?style=flat-square)](https://packagist.org/packages/jfcherng/php-mb-string)
[![Project license](https://img.shields.io/github/license/jfcherng/php-mb-string?style=flat-square)](https://github.com/jfcherng/php-mb-string/blob/master/LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/jfcherng/php-mb-string?style=flat-square&logo=github)](https://github.com/jfcherng/php-mb-string/stargazers)
[![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-blue.svg?style=flat-square&logo=paypal)](https://www.paypal.me/jfcherng/5usd)

A high performance multibyte sting implementation for frequently reading/writing operations.

## Why I Write This Package?

Consider that you have a **LONG** multibyte string and
you want to do lots of following operations on it.

- Random reading/writing such as `$char = $str[5];` or `$str[5] = '許';`.
- Replacement such as `str_replace($search, $replace, $str);`.
- Insertion such as `substr_replace($insert, $str, $position, 0);`.
- Get substring such as `substr($str, $start, $length);`.

Because strings in PHP are not UTF-8, to do operations above safely,
you have to either use `mb_*()` functions or calculate the index by yourself.
Using `mb_*()` functions frequently can be a performance loss because it has
to re-decode the source string basing on the given encoding every time when you call it.
The longer the string is, the severer the problem becomes.

Instead, this class internally stores the string in its UTF-32 form,
which is fixed-width (1 char always occupies 4 bytes) so we are able to
perform speedy random accesses. With the power of random access, we could
use `str_*()` functions to do the job internally.

## Installation

```bash
composer require jfcherng/php-mb-string
```

## Example

See [tests/MbStringTest.php](https://github.com/jfcherng/php-mb-string/blob/master/tests/MbStringTest.php).

## Benchmark

See [benchmark/\_results.txt](https://github.com/jfcherng/php-mb-string/blob/master/benchmark/_results.txt).

## What Are You Doing With This Package?

I develop this for a PHP diff package, [jfcherng/php-diff](https://github.com/jfcherng/php-diff).
