<?php
use Gettext\Languages\Language;

class GetTest extends PHPUnit_Framework_TestCase
{
    public function testGetAll()
    {
        $list = Language::getAll();
        $count = count($list);
        $this->assertGreaterThan(100, $count, 'The number of all languages is too small');
        $this->assertLessThan(10000, $count, 'The number of all languages is too big');
    }

    public function testGetById()
    {
        $this->assertNull(Language::getById('root'), 'The root language is found!');

        $language = Language::getById('it');
        $this->assertNotNull($language, "The language 'it' has not been found");
        $this->assertInstanceOf('Gettext\Languages\Language', $language);
        $this->assertSame('Italian', $language->name);
        $this->assertNull($language->territory);

        $language = Language::getById('it-IT');
        $this->assertNotNull($language, "The language 'it-IT' has not been found");
        $this->assertSame('it_IT', $language->id);
        $this->assertSame('Italian (Italy)', $language->name);
        $this->assertSame('Italy', $language->territory);

        $language = Language::getById('it_IT');
        $this->assertNotNull($language, "The language 'it_IT' has not been found");
        $this->assertSame('it_IT', $language->id);
        $this->assertSame('Italian (Italy)', $language->name);

        $language1 = Language::getById('nl_BE');
        $this->assertNotNull($language1, "The language 'nl_BE' has not been found");
        $language2 = Language::getById('nl');
        $this->assertNotNull($language2, "The language 'nl' has not been found");
        $this->assertSame($language1->baseLanguage, $language2->name);

        $language = Language::getById('it');
        $this->assertNull($language->script);
        $language = Language::getById('it_Xxxxx');
        $this->assertNull($language);
        $language = Language::getById('it_Latn');
        $this->assertNotNull($language);
        $this->assertNotNull($language->script);
    }

    public function testPortuguese()
    {
        $pt = Language::getById('pt');
        $this->assertSame('Portuguese', $pt->name);
        $this->assertSame(2, count($pt->categories));
        $this->assertSame('one', $pt->categories[0]->id);
        
        $ptPT = Language::getById('pt-PT');
        $this->assertSame('European Portuguese', $ptPT->name);
        $this->assertSame(2, count($ptPT->categories));
        $this->assertSame('one', $ptPT->categories[0]->id);
        
        $ptBR = Language::getById('pt-BR');
        $this->assertSame('Brazilian Portuguese', $ptBR->name);
        $this->assertSame(2, count($ptBR->categories));
        $this->assertSame('one', $ptBR->categories[0]->id);
        
        $ptCV = Language::getById('pt-CV');
        $this->assertSame('Portuguese (Cape Verde)', $ptCV->name);
        $this->assertSame(2, count($ptCV->categories));
        $this->assertSame('one', $ptCV->categories[0]->id);
        
        $this->assertSame($pt->formula, $ptBR->formula);
        $this->assertNotSame($pt->formula, $ptPT->formula);
        $this->assertSame($ptBR->formula, $ptCV->formula);
    }
}
