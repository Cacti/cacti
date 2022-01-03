# Gettext

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
![ico-ga]
[![Total Downloads][ico-downloads]][link-downloads]

> Note: this is the documentation of the new 5.x version. Go to [4.x branch](https://github.com/php-gettext/Gettext/tree/4.x) if you're looking for the old 4.x version

Created by Oscar Otero <http://oscarotero.com> <oom@oscarotero.com> (MIT License)

Gettext is a PHP (^7.2) library to import/export/edit gettext from PO, MO, PHP, JS files, etc.

## Installation

```
composer require gettext/gettext
```

## Classes and functions

This package contains the following classes:

* `Gettext\Translation` - A translation definition
* `Gettext\Translations` - A collection of translations (under the same domain)
* `Gettext\Scanner\*` - Scan files to extract translations (php, js, twig templates, ...)
* `Gettext\Loader\*` - Load translations from different formats (po, mo, json, ...)
* `Gettext\Generator\*` - Export translations to various formats (po, mo, json, ...)

## Usage example

```php
use Gettext\Loader\PoLoader;
use Gettext\Generator\MoGenerator;

//import from a .po file:
$loader = new PoLoader();
$translations = $loader->loadFile('locales/gl.po');

//edit some translations:
$translation = $translations->find(null, 'apple');

if ($translation) {
    $translation->translate('Mazá');
}

//export to a .mo file:
$generator = new MoGenerator();
$generator->generateFile($translations, 'Locale/gl/LC_MESSAGES/messages.mo');
```

## Translation

The `Gettext\Translation` class stores all information about a translation: the original text, the translated text, source references, comments, etc.

```php
use Gettext\Translation;

$translation = Translation::create('comments', 'One comment', '%s comments');

$translation->translate('Un comentario');
$translation->translatePlural('%s comentarios');

$translation->getReferences()->add('templates/comments/comment.php', 34);
$translation->getComments()->add('To display the amount of comments in a post');

echo $translation->getContext(); // comments
echo $translation->getOriginal(); // One comment
echo $translation->getTranslation(); // Un comentario

// etc...
```

## Translations

The `Gettext\Translations` class stores a collection of translations:

```php
use Gettext\Translations;

$translations = Translations::create('my-domain');

//You can add new translations:
$translation = Translation::create('comments', 'One comment', '%s comments');
$translations->add($translation);

//Find a specific translation
$translation = $translations->find('comments', 'One comment');

//Edit headers, domain, etc
$translations->getHeaders()->set('Last-Translator', 'Oscar Otero');
$translations->setDomain('my-blog');
```

## Loaders

The loaders allows to get gettext values from any format. For example, to load a .po file:

```php
use Gettext\Loader\PoLoader;

$loader = new PoLoader();

//From a file
$translations = $loader->loadFile('locales/en.po');

//From a string
$string = file_get_contents('locales2/en.po');
$translations = $loader->loadString($string);
```

This package includes the following loaders:

- `MoLoader`
- `PoLoader`

And you can install other formats with loaders and generators:

- [Json](https://github.com/php-gettext/Json)

## Generators

The generators export a `Gettext\Translations` instance to any format (po, mo, etc).

```php
use Gettext\Loader\PoLoader;
use Gettext\Generator\MoGenerator;

//Load a PO file
$poLoader = new PoLoader();

$translations = $poLoader->loadFile('locales/en.po');

//Save to MO file
$moGenerator = new MoGenerator();

$moGenerator->generateFile($translations, 'locales/en.mo');

//Or return as a string
$content = $moGenerator->generateString($translations);
file_put_contents('locales/en.mo', $content);
```

This package includes the following generators:

- `MoGenerator`
- `PoGenerator`

And you can install other formats with loaders and generators:

- [Json](https://github.com/php-gettext/Json)


## Scanners

Scanners allow to search and extract new gettext entries from different sources like php files, twig templates, blade templates, etc. Unlike loaders, scanners allows to extract gettext entries with different domains at the same time:

```php
use Gettext\Scanner\PhpScanner;
use Gettext\Translations;

//Create a new scanner, adding a translation for each domain we want to get:
$phpScanner = new PhpScanner(
    Translations::create('domain1'),
    Translations::create('domain2'),
    Translations::create('domain3')
);

//Set a default domain, so any translations with no domain specified, will be added to that domain
$phpScanner->setDefaultDomain('domain1');

//Extract all comments starting with 'i18n:' and 'Translators:'
$phpScanner->extractCommentsStartingWith('i18n:', 'Translators:');

//Scan files
foreach (glob('*.php') as $file) {
    $phpScanner->scanFile($file);
}

//Get the translations
list('domain1' => $domain1, 'domain2' => $domain2, 'domain3' => $domain3) = $phpScanner->getTranslations();
```

This package does not include any scanner by default. But there are some that you can install:

- [PHP Scanner](https://github.com/php-gettext/PHP-Scanner)
- [JS Scanner](https://github.com/php-gettext/JS-Scanner)

## Merging translations

You will want to update or merge translations. The function `mergeWith` create a new `Translations` instance with other translations merged:

```php
$translations3 = $translations1->mergeWith($translations2);
```

But sometimes this is not enough, and this is why we have merging options, allowing to configure how two translations will be merged. These options are defined as constants in the `Gettext\Merge` class, and are the following:

Constant | Description
--------- | -----------
`Merge::TRANSLATIONS_OURS` | Use only the translations present in `$translations1`
`Merge::TRANSLATIONS_THEIRS` | Use only the translations present in `$translations2`
`Merge::TRANSLATION_OVERRIDE` | Override the translation and plural translations with the value of `$translation2`
`Merge::HEADERS_OURS` | Use only the headers of `$translations1`
`Merge::HEADERS_REMOVE` | Use only the headers of `$translations2`
`Merge::HEADERS_OVERRIDE` | Overrides the headers with the values of `$translations2`
`Merge::COMMENTS_OURS` | Use only the comments of `$translation1`
`Merge::COMMENTS_THEIRS` | Use only the comments of `$translation2`
`Merge::EXTRACTED_COMMENTS_OURS` | Use only the extracted comments of `$translation1`
`Merge::EXTRACTED_COMMENTS_THEIRS` | Use only the extracted comments of `$translation2`
`Merge::FLAGS_OURS` | Use only the flags of `$translation1`
`Merge::FLAGS_THEIRS` | Use only the flags of `$translation2`
`Merge::REFERENCES_OURS` | Use only the references of `$translation1`
`Merge::REFERENCES_THEIRS` | Use only the references of `$translation2`

Use the second argument to configure the merging strategy:

```php
$strategy = Merge::TRANSLATIONS_OURS | Merge::HEADERS_OURS;

$translations3 = $translations1->mergeWith($translations2, $strategy);
```

There are some typical scenarios, one of the most common:

- Scan php templates searching for entries to translate
- Complete these entries with the translations stored in a .po file
- You may want to add new entries to the .po file
- And also remove those entries present in the .po file but not in the templates (because they were removed)
- But you want to update some translations with new references and extracted comments
- And keep the translations, comments and flags defined in .po file

For this scenario, you can use the option `Merge::SCAN_AND_LOAD` with the combination of options to fit this needs (SCAN new entries and LOAD a .po file).

```php
$newEntries = $scanner->scanFile('template.php');
$previousEntries = $loader->loadFile('translations.po');

$updatedEntries = $newEntries->mergeWith($previousEntries);
```

More common scenarios may be added in a future.

## Contributors

Thanks to all [contributors](https://github.com/oscarotero/Gettext/graphs/contributors) specially to [@mlocati](https://github.com/mlocati).

---

Please see [CHANGELOG](CHANGELOG.md) for more information about recent changes and [CONTRIBUTING](CONTRIBUTING.md) for contributing details.

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/gettext/gettext.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-ga]: https://github.com/php-gettext/Gettext/workflows/testing/badge.svg
[ico-downloads]: https://img.shields.io/packagist/dt/gettext/gettext.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/gettext/gettext
[link-downloads]: https://packagist.org/packages/gettext/gettext
