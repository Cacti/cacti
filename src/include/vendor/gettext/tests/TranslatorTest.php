<?php

namespace Gettext\Tests;

use Gettext\Languages\Language;
use Gettext\Translations;
use Gettext\Translation;
use Gettext\Translator;

class TranslatorTest extends AbstractTest
{
    public function testNoop()
    {
        $t = new Translator();
        $original = 'original string';
        $this->assertEquals($original, $t->noop($original));
    }

    public function testOne()
    {
        $t = new Translator();

        $t->loadTranslations(static::get('po/Po'));
        $t->loadTranslations(static::get('po2/Po'));

        $this->assertEquals('test', $t->gettext('single'));
        $this->assertEquals('test', $t->dgettext('', 'single'));

        $this->assertEquals('Cijeo broj', $t->dgettext('testingdomain', 'Integer'));

        $t->defaultDomain('testingdomain');

        $this->assertEquals('Ovo polje ne može biti prazno.', $t->gettext('This field cannot be blank.'));
        $this->assertEquals('Value %sr is not a valid choice.', $t->gettext('Value %sr is not a valid choice.'));
    }

    public function testOneFunction()
    {
        $t = new Translator();
        $t->loadTranslations(static::get('po2/Po'));

        $t->register();

        $this->assertEquals('Cijeo broj', __('Integer'));
        $this->assertEquals('Ovo polje ne može biti prazno.', __('This field cannot be blank.'));
        $this->assertEquals('Value %sr is not a valid choice.', __('Value %sr is not a valid choice.'));
        $this->assertEquals('Value hellor is not a valid choice.', __('Value %sr is not a valid choice.', 'hello'));
        $this->assertEquals('Value 0r is not a valid choice.', __('Value %sr is not a valid choice.', 0));
        $this->assertEquals('Value r is not a valid choice.', __('Value %sr is not a valid choice.', null));
        $this->assertEquals('1s mora da bude jedinstven za 2s 3s.', __('%ss must be unique for %ss %ss.', '1', '2', '3'));
        $this->assertEquals('Value hellor is not a valid choice.', __('Value %sr is not a valid choice.', ['%s' => 'hello']));
    }

    public function testPlural()
    {
        $t = new Translator();
        $t->loadTranslations(static::get('po/Po'));

        // Test that nplural=3 plural translation check comes up with the correct translation key.
        $this->assertEquals('1 plik', $t->ngettext('one file', 'multiple files', 1));
        $this->assertEquals('2,3,4 pliki', $t->ngettext('one file', 'multiple files', 2));
        $this->assertEquals('2,3,4 pliki', $t->ngettext('one file', 'multiple files', 3));
        $this->assertEquals('2,3,4 pliki', $t->ngettext('one file', 'multiple files', 4));
        $this->assertEquals('5-21 plików', $t->ngettext('one file', 'multiple files', 5));
        $this->assertEquals('5-21 plików', $t->ngettext('one file', 'multiple files', 6));

        // Test that non-plural translations the fallback still works.
        $this->assertEquals('more', $t->ngettext('single', 'more', 3));

        $t = new Translator();
        $t->loadTranslations(static::get('po2/Po'));

        // Test that if the translation is unknown, English plural rules are applied
        $this->assertEquals('more', $t->ngettext('single', 'more', 21));
    }

    public function testPluralFunction()
    {
        $translations = new Translations();
        $translations[] = 
            (new Translation(null, 'One comment', '%s comments'))
            ->setTranslation('Un commentaire')
            ->setPluralTranslations(['%s commentaires']);
        $t = new Translator();
        $t->loadTranslations($translations);

        $t->register();

        $this->assertEquals('%s commentaires', n__('One comment', '%s comments', 3));
        $this->assertEquals('beaucoup de commentaires', n__('One comment', '%s comments', 3, 'beaucoup de'));
        $this->assertEquals('0 commentaires', n__('One comment', '%s comments', 3, 0));
        $this->assertEquals(' commentaires', n__('One comment', '%s comments', 3, null));
        $this->assertEquals('beaucoup de commentaires', n__('One comment', '%s comments', 3, ['%s' => 'beaucoup de']));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPluralInjection()
    {
        $translations = new Translations();
        $translations->setPluralForms(2, 'fuu_call()');
    }

    public function testPluralFromValidation()
    {
        $translations = new Translations();
        $languages = Language::getAll();

        foreach ($languages as $language) {
            $result = $translations->setPluralForms(2, $language->formula);
            $this->assertInstanceOf('Gettext\Translations', $result);
        }
    }

    public function testContextFunction()
    {
        $translations = new Translations();
        $translations[] = 
            (new Translation('daytime', 'Hello %s'))
            ->setTranslation('Bonjour %s');
        $translations[] = 
            (new Translation('nightime', 'Hello %s'))
            ->setTranslation('Bonsoir %s');
        $t = new Translator();
        $t->loadTranslations($translations);

        $t->register();

        $this->assertEquals('Bonjour %s', p__('daytime','Hello %s'));
        $this->assertEquals('Bonjour John', p__('daytime','Hello %s', 'John'));
        $this->assertEquals('Bonjour 0', p__('daytime','Hello %s', 0));
        $this->assertEquals('Bonjour ', p__('daytime','Hello %s', null));
        $this->assertEquals('Bonjour John', p__('daytime','Hello %s',['%s' => 'John']));
        $this->assertEquals('Bonsoir John', p__('nightime','Hello %s', 'John'));
        $this->assertEquals('Bonsoir John', p__('nightime','Hello %s',['%s' => 'John']));
    }

    public function testDomainFunction()
    {
        $translations = new Translations();
        $translations->setDomain('messages');
        $translations[] = 
            (new Translation(null, 'Hello %s'))
            ->setTranslation('Bonjour %s');
        $t = new Translator();
        $t->loadTranslations($translations);

        $t->register();

        $this->assertEquals('Bonjour %s', d__('messages','Hello %s'));
        $this->assertEquals('Bonjour John', d__('messages','Hello %s','John'));
        $this->assertEquals('Bonjour 0', d__('messages','Hello %s',0));
        $this->assertEquals('Bonjour ', d__('messages','Hello %s',null));
        $this->assertEquals('Bonjour John', d__('messages','Hello %s',['%s' => 'John']));
        $this->assertEquals('Hello %s', d__('errors','Hello %s'));
        $this->assertEquals('Hello John', d__('errors','Hello %s',['%s' => 'John']));
    }

    public function testDomainPluralFunction()
    {
        $translations = new Translations();
        $translations->setDomain('messages');
        $translations[] = 
            (new Translation(null, 'One comment', '%s comments'))
            ->setTranslation('Un commentaire')
            ->setPluralTranslations(['%s commentaires']);
        $t = new Translator();
        $t->loadTranslations($translations);

        $t->register();

        $this->assertEquals('%s commentaires', dn__('messages', 'One comment', '%s comments', 3));
        $this->assertEquals('beaucoup de commentaires', dn__('messages', 'One comment', '%s comments', 3, 'beaucoup de'));
        $this->assertEquals('0 commentaires', dn__('messages', 'One comment', '%s comments', 3, 0));
        $this->assertEquals(' commentaires', dn__('messages', 'One comment', '%s comments', 3, null));
        $this->assertEquals('beaucoup de commentaires', dn__('messages', 'One comment', '%s comments', 3, ['%s' => 'beaucoup de']));
        $this->assertEquals('One comment', dn__('messages-2', 'One comment', '%s comments', 1, 1));
        $this->assertEquals('3 comments', dn__('messages-2', 'One comment', '%s comments', 3, 3));
    }

    public function testDomainAndContextFunction()
    {
        $translations = new Translations();
        $translations->setDomain('messages');
        $translations[] = 
            (new Translation('daytime', 'Hello %s'))
            ->setTranslation('Bonjour %s');
        $translations[] = 
            (new Translation('nightime', 'Hello %s'))
            ->setTranslation('Bonsoir %s');
        $t = new Translator();
        $t->loadTranslations($translations);

        $t->register();

        $this->assertEquals('Bonjour %s', dp__('messages','daytime','Hello %s'));
        $this->assertEquals('Bonjour John', dp__('messages','daytime','Hello %s', 'John'));
        $this->assertEquals('Bonjour 0', dp__('messages','daytime','Hello %s', 0));
        $this->assertEquals('Bonjour ', dp__('messages','daytime','Hello %s', null));
        $this->assertEquals('Bonjour John', dp__('messages','daytime','Hello %s',['%s' => 'John']));
        $this->assertEquals('Bonsoir John', dp__('messages','nightime','Hello %s', 'John'));
        $this->assertEquals('Bonsoir John', dp__('messages','nightime','Hello %s',['%s' => 'John']));
        $this->assertEquals('Hello John', dp__('errors','daytime','Hello %s',['%s' => 'John']));
    }

    public function testPluralAndContextFunction()
    {
        $translations = new Translations();
        $translations[] = 
            (new Translation('comment', 'One comment', '%s comments'))
            ->setTranslation('Un commentaire')
            ->setPluralTranslations(['%s commentaires']);
        $t = new Translator();
        $t->loadTranslations($translations);

        $t->register();

        $this->assertEquals('%s commentaires', np__('comment', 'One comment', '%s comments', 3));
        $this->assertEquals('0 commentaires', np__('comment', 'One comment', '%s comments', 3, 0));
        $this->assertEquals(' commentaires', np__('comment', 'One comment', '%s comments', 3, null));
        $this->assertEquals('beaucoup de commentaires', np__('comment', 'One comment', '%s comments', 3, 'beaucoup de'));
        $this->assertEquals('beaucoup de commentaires', np__('comment', 'One comment', '%s comments', 3, ['%s' => 'beaucoup de']));
        $this->assertEquals('3 comments', np__(null, 'One comment', '%s comments', 3, ['%s' => 3]));
    }

    public function testPluralAndContextAndDomainFunction()
    {
        $translations = new Translations();
        $translations->setDomain('messages');
        $translations[] = 
            (new Translation('comment', 'One comment', '%s comments'))
            ->setTranslation('Un commentaire')
            ->setPluralTranslations(['%s commentaires']);
        $t = new Translator();
        $t->loadTranslations($translations);

        $t->register();

        $this->assertEquals('%s commentaires', dnp__('messages', 'comment', 'One comment', '%s comments', 3));
        $this->assertEquals('0 commentaires', dnp__('messages', 'comment', 'One comment', '%s comments', 3, 0));
        $this->assertEquals(' commentaires', dnp__('messages', 'comment', 'One comment', '%s comments', 3, null));
        $this->assertEquals('beaucoup de commentaires', dnp__('messages', 'comment', 'One comment', '%s comments', 3, 'beaucoup de'));
        $this->assertEquals('beaucoup de commentaires', dnp__('messages', 'comment', 'One comment', '%s comments', 3, ['%s' => 'beaucoup de']));
        $this->assertEquals('beaucoup de comments', dnp__('errors', 'comment', 'One comment', '%s comments', 3, ['%s' => 'beaucoup de']));
        $this->assertEquals('beaucoup de comments', dnp__('messages', null, 'One comment', '%s comments', 3, ['%s' => 'beaucoup de']));
    }

    public function testNonLoadedTranslations()
    {
        $t = new Translator();

        $this->assertEquals('hello', $t->gettext('hello'));
        $this->assertEquals('worlds', $t->ngettext('world', 'worlds', 0));
        $this->assertEquals('world', $t->ngettext('world', 'worlds', 1));
        $this->assertEquals('worlds', $t->ngettext('world', 'worlds', 2));
    }

    public function testHeaders()
    {
        $po = (new Translator())->loadTranslations(static::get('po/Po'));
        $mo = (new Translator())->loadTranslations(static::get('po/Mo'));
        $array = (new Translator())->loadTranslations(static::get('po/PhpArray'));

        $this->assertEmpty($po->gettext(''));
    }
}
