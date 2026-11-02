<?php

namespace Gettext\Tests;

use Gettext\Extractors\PhpCode;
use Gettext\Translations;

class AssetsTest extends AbstractTest
{
    public function testPo()
    {
        $translations = static::get('po/input', 'Po');
        $countTranslations = 3;
        $countTranslated = 3;
        $countHeaders = 9;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(3, $translations->countTranslated());

        $disabled = $translations->find('', 'one');
        $this->assertTrue($disabled->isDisabled());

        $disabled = $translations->find('', 'single');
        $this->assertFalse($disabled->isDisabled());

        $this->assertContent($translations, 'po/Po');
        $this->assertContent($translations, 'po/Mo');
        $this->assertContent($translations, 'po/PhpArray');
        $this->assertContent($translations, 'po/Jed');
        $this->assertContent($translations, 'po/Json');
        $this->assertContent($translations, 'po/JsonDictionary');
        $this->assertContent($translations, 'po/Csv');
        $this->assertContent($translations, 'po/CsvDictionary');
        $this->assertContent($translations, 'po/Xliff');
        $this->assertContent($translations, 'po/Yaml');
        $this->assertContent($translations, 'po/YamlDictionary');

        $this->runTestFormat('po/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po/Mo', $countTranslations - 1, $countTranslated - 1, $countHeaders);
        $this->runTestFormat('po/PhpArray', $countTranslations - 1, $countTranslated - 1, $countHeaders);
        $this->runTestFormat('po/Jed', $countTranslations - 1, $countTranslated - 1, 10);
        $this->runTestFormat('po/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po/Json', $countTranslations - 1, $countTranslated - 1, $countHeaders);
        $this->runTestFormat('po/JsonDictionary', $countTranslations - 1, $countTranslated - 1);
        $this->runTestFormat('po/Csv', $countTranslations - 1, $countTranslated - 1, $countHeaders);
        $this->runTestFormat('po/CsvDictionary', $countTranslations - 1, $countTranslated - 1);
        $this->runTestFormat('po/Yaml', $countTranslations - 1, $countTranslated - 1, $countHeaders);
        $this->runTestFormat('po/YamlDictionary', $countTranslations - 1, $countTranslated - 1);
    }

    public function testPo2()
    {
        $translations = static::get('po2/input', 'Po');
        $countTranslations = 13;
        $countTranslated = 10;
        $countHeaders = 13;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(10, $translations->countTranslated());

        $this->assertContent($translations, 'po2/Po');
        $this->assertContent($translations, 'po2/Mo');
        $this->assertContent($translations, 'po2/PhpArray');
        $this->assertContent($translations, 'po2/Jed');
        $this->assertContent($translations, 'po2/Json');
        $this->assertContent($translations, 'po2/JsonDictionary');
        $this->assertContent($translations, 'po2/Csv');
        $this->assertContent($translations, 'po2/CsvDictionary');
        $this->assertContent($translations, 'po2/Xliff');
        $this->assertContent($translations, 'po2/Yaml');
        $this->assertContent($translations, 'po2/YamlDictionary');

        $this->runTestFormat('po2/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po2/Mo', $countTranslations - 3, $countTranslated, $countHeaders);
        $this->runTestFormat('po2/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po2/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('po2/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po2/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po2/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('po2/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po2/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('po2/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po2/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testPo3()
    {
        $translations = static::get('po3/input', 'Po');
        $countTranslations = 8;
        $countTranslated = 8;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(8, $translations->countTranslated());

        $this->assertContent($translations, 'po3/Po');
        $this->assertContent($translations, 'po3/Mo');
        $this->assertContent($translations, 'po3/PhpArray');
        $this->assertContent($translations, 'po3/Jed');
        $this->assertContent($translations, 'po3/Json');
        $this->assertContent($translations, 'po3/JsonDictionary');
        $this->assertContent($translations, 'po3/Csv');
        $this->assertContent($translations, 'po3/CsvDictionary');
        $this->assertContent($translations, 'po3/Xliff');
        $this->assertContent($translations, 'po3/Yaml');
        $this->assertContent($translations, 'po3/YamlDictionary');

        $this->runTestFormat('po3/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po3/Mo', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po3/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po3/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('po3/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po3/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po3/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('po3/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po3/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('po3/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('po3/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testBlade()
    {
        $translations = static::get('blade/input', 'Blade');
        $countTranslations = 11;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());

        $this->assertContent($translations, 'blade/Po');
        $this->assertContent($translations, 'blade/Mo');
        $this->assertContent($translations, 'blade/PhpArray');
        $this->assertContent($translations, 'blade/Jed');
        $this->assertContent($translations, 'blade/Json');
        $this->assertContent($translations, 'blade/JsonDictionary');
        $this->assertContent($translations, 'blade/Csv');
        $this->assertContent($translations, 'blade/CsvDictionary');
        $this->assertContent($translations, 'blade/Xliff');
        $this->assertContent($translations, 'blade/Yaml');
        $this->assertContent($translations, 'blade/YamlDictionary');

        $this->runTestFormat('blade/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('blade/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('blade/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('blade/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('blade/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('blade/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('blade/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('blade/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('blade/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('blade/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('blade/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testJed()
    {
        $translations = static::get('jed/input', 'Jed');
        $countTranslations = 13;
        $countTranslated = 10;
        $countHeaders = 10;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(10, $translations->countTranslated());

        $this->assertContent($translations, 'jed/Po');
        $this->assertContent($translations, 'jed/Mo');
        $this->assertContent($translations, 'jed/PhpArray');
        $this->assertContent($translations, 'jed/Jed');
        $this->assertContent($translations, 'jed/Json');
        $this->assertContent($translations, 'jed/JsonDictionary');
        $this->assertContent($translations, 'jed/Csv');
        $this->assertContent($translations, 'jed/CsvDictionary');
        $this->assertContent($translations, 'jed/Xliff');
        $this->assertContent($translations, 'jed/Yaml');
        $this->assertContent($translations, 'jed/YamlDictionary');

        $this->runTestFormat('jed/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jed/Mo', 10, $countTranslated, $countHeaders);
        $this->runTestFormat('jed/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jed/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('jed/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jed/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jed/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('jed/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jed/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('jed/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jed/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testJsCode()
    {
        $translations = static::get('jscode/input', 'JsCode');
        $countTranslations = 7;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());

        $this->assertContent($translations, 'jscode/Po');
        $this->assertContent($translations, 'jscode/Mo');
        $this->assertContent($translations, 'jscode/PhpArray');
        $this->assertContent($translations, 'jscode/Jed');
        $this->assertContent($translations, 'jscode/Json');
        $this->assertContent($translations, 'jscode/JsonDictionary');
        $this->assertContent($translations, 'jscode/Csv');
        $this->assertContent($translations, 'jscode/CsvDictionary');
        $this->assertContent($translations, 'jscode/Xliff');
        $this->assertContent($translations, 'jscode/Yaml');
        $this->assertContent($translations, 'jscode/YamlDictionary');

        $this->runTestFormat('jscode/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('jscode/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('jscode/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('jscode/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testJs2Code()
    {
        $translations = static::get('jscode2/input', 'JsCode');
        $countTranslations = 4;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());
        $this->assertContent($translations, 'jscode2/Po');
        $this->assertContent($translations, 'jscode2/Mo');
        $this->assertContent($translations, 'jscode2/PhpArray');
        $this->assertContent($translations, 'jscode2/Jed');
        $this->assertContent($translations, 'jscode2/Json');
        $this->assertContent($translations, 'jscode2/JsonDictionary');
        $this->assertContent($translations, 'jscode2/Csv');
        $this->assertContent($translations, 'jscode2/CsvDictionary');
        $this->assertContent($translations, 'jscode2/Xliff');
        $this->assertContent($translations, 'jscode2/Yaml');
        $this->assertContent($translations, 'jscode2/YamlDictionary');

        $this->runTestFormat('jscode2/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode2/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode2/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode2/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('jscode2/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode2/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode2/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('jscode2/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode2/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('jscode2/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('jscode2/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testPhpCode()
    {
        $translations = static::get('phpcode/input', 'PhpCode');
        $countTranslations = 12;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());

        $this->assertContent($translations, 'phpcode/Po');
        $this->assertContent($translations, 'phpcode/Mo');
        $this->assertContent($translations, 'phpcode/PhpArray');
        $this->assertContent($translations, 'phpcode/Jed');
        $this->assertContent($translations, 'phpcode/Json');
        $this->assertContent($translations, 'phpcode/JsonDictionary');
        $this->assertContent($translations, 'phpcode/Csv');
        $this->assertContent($translations, 'phpcode/CsvDictionary');
        $this->assertContent($translations, 'phpcode/Xliff');
        $this->assertContent($translations, 'phpcode/Yaml');
        $this->assertContent($translations, 'phpcode/YamlDictionary');

        $this->runTestFormat('phpcode/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('phpcode/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testPhpCode2()
    {
        $translations = static::get('phpcode2/input', 'PhpCode', [
            'constants' => [
                'CONTEXT' => 'my-context',
            ],
        ]);
        $countTranslations = 14;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals($countTranslated, $translations->countTranslated());

        $this->assertContent($translations, 'phpcode2/Po');
        $this->assertContent($translations, 'phpcode2/Mo');
        $this->assertContent($translations, 'phpcode2/PhpArray');
        $this->assertContent($translations, 'phpcode2/Jed');
        $this->assertContent($translations, 'phpcode2/Json');
        $this->assertContent($translations, 'phpcode2/JsonDictionary');
        $this->assertContent($translations, 'phpcode2/Csv');
        $this->assertContent($translations, 'phpcode2/CsvDictionary');
        $this->assertContent($translations, 'phpcode2/Xliff');
        $this->assertContent($translations, 'phpcode2/Yaml');
        $this->assertContent($translations, 'phpcode2/YamlDictionary');

        $this->runTestFormat('phpcode2/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode2/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode2/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode2/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('phpcode2/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode2/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode2/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode2/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode2/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode2/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode2/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testPhpCode3()
    {
        $translations = static::get('phpcode3/input', 'PhpCode', [
            'extractComments' => ['allowed1', 'allowed2'],
        ]);
        $countTranslations = 2;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());

        $this->assertContent($translations, 'phpcode3/Po');
        $this->assertContent($translations, 'phpcode3/Mo');
        $this->assertContent($translations, 'phpcode3/PhpArray');
        $this->assertContent($translations, 'phpcode3/Jed');
        $this->assertContent($translations, 'phpcode3/Json');
        $this->assertContent($translations, 'phpcode3/JsonDictionary');
        $this->assertContent($translations, 'phpcode3/Csv');
        $this->assertContent($translations, 'phpcode3/CsvDictionary');
        $this->assertContent($translations, 'phpcode3/Xliff');
        $this->assertContent($translations, 'phpcode3/Yaml');
        $this->assertContent($translations, 'phpcode3/YamlDictionary');

        $this->runTestFormat('phpcode3/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode3/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode3/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode3/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('phpcode3/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode3/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode3/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode3/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode3/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode3/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode3/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testTwig()
    {
        $translations = static::get('twig/input', 'Twig');
        $countTranslations = 10;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());

        //Ignored in php5 because the line numbers doesn't match due different version of twig
        if (PHP_MAJOR_VERSION >= 7) {
            $this->assertContent($translations, 'twig/Po');
            $this->assertContent($translations, 'twig/Xliff');
        }

        $this->assertContent($translations, 'twig/Mo');
        $this->assertContent($translations, 'twig/PhpArray');
        $this->assertContent($translations, 'twig/Jed');
        $this->assertContent($translations, 'twig/Json');
        $this->assertContent($translations, 'twig/JsonDictionary');
        $this->assertContent($translations, 'twig/Csv');
        $this->assertContent($translations, 'twig/CsvDictionary');
        $this->assertContent($translations, 'twig/Yaml');
        $this->assertContent($translations, 'twig/YamlDictionary');

        $this->runTestFormat('twig/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('twig/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('twig/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('twig/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('twig/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('twig/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('twig/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('twig/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('twig/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('twig/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('twig/YamlDictionary', $countTranslations, $countTranslated);
    }

    public function testVueJs()
    {
        $translations = static::get('vuejs/input', 'VueJs');

        $countTranslations = 34;
        $countTranslated = 0;
        $countHeaders = 8;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());


        $this->assertContent($translations, 'vuejs/Po');
        $this->assertContent($translations, 'vuejs/Mo');
        $this->assertContent($translations, 'vuejs/PhpArray');
        $this->assertContent($translations, 'vuejs/Jed');
        $this->assertContent($translations, 'vuejs/Json');
        $this->assertContent($translations, 'vuejs/JsonDictionary');
        $this->assertContent($translations, 'vuejs/Csv');
        $this->assertContent($translations, 'vuejs/CsvDictionary');
        $this->assertContent($translations, 'vuejs/Xliff');
        $this->assertContent($translations, 'vuejs/Yaml');
        $this->assertContent($translations, 'vuejs/YamlDictionary');

        $this->runTestFormat('vuejs/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('vuejs/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('vuejs/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('vuejs/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('vuejs/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('vuejs/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('vuejs/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('vuejs/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('vuejs/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('vuejs/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('vuejs/YamlDictionary', $countTranslations, $countTranslated);
    }

    /**
     * Test specific domain translation extraction excluding default domain messages
     */
    public function testPhpCode4()
    {
        $translations = new Translations;
        $translations->setDomain('domain1');

        PhpCode::fromFile(static::asset('phpcode4/input.php'), $translations, [
            'domainOnly' => true,
        ]);

        $countHeaders = 9;
        $countTranslated = 0;
        $countTranslations = 4;

        $this->assertCount($countTranslations, $translations);
        $this->assertCount($countHeaders, $translations->getHeaders());
        $this->assertEquals(0, $translations->countTranslated());

        $this->assertContent($translations, 'phpcode4/Po');
        $this->assertContent($translations, 'phpcode4/Mo');
        $this->assertContent($translations, 'phpcode4/PhpArray');
        $this->assertContent($translations, 'phpcode4/Jed');
        $this->assertContent($translations, 'phpcode4/Json');
        $this->assertContent($translations, 'phpcode4/JsonDictionary');
        $this->assertContent($translations, 'phpcode4/Csv');
        $this->assertContent($translations, 'phpcode4/CsvDictionary');
        $this->assertContent($translations, 'phpcode4/Xliff');
        $this->assertContent($translations, 'phpcode4/Yaml');
        $this->assertContent($translations, 'phpcode4/YamlDictionary');

        $this->runTestFormat('phpcode4/Po', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode4/Mo', 0, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode4/PhpArray', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode4/Jed', $countTranslations, $countTranslated, 10);
        $this->runTestFormat('phpcode4/Xliff', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode4/Json', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode4/JsonDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode4/Csv', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode4/CsvDictionary', $countTranslations, $countTranslated);
        $this->runTestFormat('phpcode4/Yaml', $countTranslations, $countTranslated, $countHeaders);
        $this->runTestFormat('phpcode4/YamlDictionary', $countTranslations, $countTranslated);
    }

}
