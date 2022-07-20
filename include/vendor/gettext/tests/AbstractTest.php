<?php

namespace Gettext\Tests;

use Gettext\Translations;
use PHPUnit\Framework\TestCase;

abstract class AbstractTest extends TestCase
{
    protected static $ext = [
        'Blade' => 'php',
        'Csv' => 'csv',
        'CsvDictionary' => 'csv',
        'Jed' => 'json',
        'JsCode' => 'js',
        'JsonDictionary' => 'json',
        'Json' => 'json',
        'Mo' => 'mo',
        'PhpArray' => 'php',
        'PhpCode' => 'php',
        'Po' => 'po',
        'Twig' => 'php',
        'Xliff' => 'xlf',
        'Yaml' => 'yml',
        'YamlDictionary' => 'yml',
        'VueJs' => 'vue',
    ];

    protected static function asset($file)
    {
        return './tests/assets/'.$file;
    }

    /**
     * @param string $file
     * @param string|null $format
     * @param array $options
     * @return Translations
     */
    protected static function get($file, $format = null, array $options = [])
    {
        if ($format === null) {
            $format = basename($file);
        }

        $method = "from{$format}File";
        $file = static::asset($file.'.'.static::$ext[$format]);

        return Translations::$method($file, $options);
    }

    protected function assertContent(Translations $translations, $file, $format = null)
    {
        if ($format === null) {
            $format = basename($file);
        }

        $method = "to{$format}String";
        $content = file_get_contents(static::asset($file.'.'.static::$ext[$format]));

        // Po reference files are LittleEndian
        if ($format !== 'Mo' || self::isLittleEndian()) {
            $this->assertSame($content, $translations->$method(), $file);
        }
    }

    protected static function saveContent(Translations $translations, $file, $format = null)
    {
        if ($format === null) {
            $format = basename($file);
        }

        $method = "to{$format}String";
        $file = static::asset($file.'.'.static::$ext[$format]);

        file_put_contents($file, $translations->$method());
    }

    protected function runTestFormat($file, $countTranslations, $countTranslated = 0, $countHeaders = 8)
    {
        $format = basename($file);
        $method = "from{$format}File";

        /** @var Translations $translations */
        $translations = Translations::$method(static::asset($file.'.'.static::$ext[$format]));

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders(), json_encode($translations->getHeaders(), JSON_PRETTY_PRINT));
        $this->assertSame($countTranslated, $translations->countTranslated());
        $this->assertContent($translations, $file);
    }

    protected function isLittleEndian()
    {
        return pack("s", 0x3031) === "10";
    }
}
