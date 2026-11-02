<?php

namespace Gettext\Tests;

use Gettext\Translations;

class LocalesTest extends AbstractTest
{
    public function testPlurals()
    {
        $translations = static::get('po/Po');

        $this->assertInstanceOf('Gettext\\Translations', $translations);
        $this->assertEquals('nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);', $translations->getHeader('Plural-Forms'));

        $translations->setPluralForms(2, '(n != 1)');
        $this->assertEquals('nplurals=2; plural=(n != 1);', $translations->getHeader('Plural-Forms'));

        $translations->setLanguage('ru');
        $this->assertEquals('nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);', $translations->getHeader('Plural-Forms'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidLanguage()
    {
        $translations = new Translations();
        $translations->setLanguage('invalid');
    }

    public function testLocalesVariants()
    {
        $translations = new Translations();

        $translations->setLanguage('pt');

        $pluralForms = $translations->getPluralForms();

        $this->assertEquals(2, $pluralForms[0]);
        $this->assertEquals('n > 1', $pluralForms[1]);

        $translations->setLanguage('az');

        $pluralForms = $translations->getPluralForms();
        $this->assertEquals(2, $pluralForms[0]);
        $this->assertEquals('n != 1', $pluralForms[1]);
    }
}
