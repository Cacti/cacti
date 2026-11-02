<?php

namespace Gettext\Tests;

use Gettext\Translations;
use Gettext\Merge;

class MergeHeadersTest extends AbstractTest
{
    protected $t1;
    protected $t2;

    public function setUp()
    {
        $this->t1 = new Translations();
        $this->t2 = new Translations();

        $this->t1
            ->setLanguage('es')
            ->setDomain('app1')
            ->setHeader('Header1', 't1-value1')
            ->setHeader('Header2', '')
            ->setHeader('Header3', 't1-value3')
            ->setHeader('Header5', 't1-value5');

        $this->t2
            ->setLanguage('gl')
            ->setDomain('app2')
            ->setHeader('Header1', '')
            ->setHeader('Header2', 't2-value2')
            ->setHeader('Header3', 't2-value3')
            ->setHeader('Header4', 't2-value4');
    }

    public function testHeadersAdd()
    {
        $options = Merge::HEADERS_ADD | Merge::LANGUAGE_OVERRIDE;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals('t1-value1', $this->t1->getHeader('Header1'));
        $this->assertEquals('t2-value2', $this->t1->getHeader('Header2'));
        $this->assertEquals('t1-value3', $this->t1->getHeader('Header3'));
        $this->assertEquals('t2-value4', $this->t1->getHeader('Header4'));
        $this->assertEquals('gl', $this->t1->getLanguage());
        $this->assertEquals('app1', $this->t1->getDomain());
    }

    public function testHeadersOverride()
    {
        $options = Merge::HEADERS_ADD | Merge::HEADERS_OVERRIDE | Merge::DOMAIN_OVERRIDE;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals('t1-value1', $this->t1->getHeader('Header1'));
        $this->assertEquals('t2-value2', $this->t1->getHeader('Header2'));
        $this->assertEquals('t2-value3', $this->t1->getHeader('Header3'));
        $this->assertEquals('t2-value4', $this->t1->getHeader('Header4'));
        $this->assertEquals('es', $this->t1->getLanguage());
        $this->assertEquals('app2', $this->t1->getDomain());
    }

    public function testHeadersRemove()
    {
        $options = Merge::HEADERS_REMOVE | Merge::DOMAIN_OVERRIDE | Merge::LANGUAGE_OVERRIDE;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals('t1-value1', $this->t1->getHeader('Header1'));
        $this->assertEquals('', $this->t1->getHeader('Header2'));
        $this->assertEquals('t1-value3', $this->t1->getHeader('Header3'));
        $this->assertEquals(null, $this->t1->getHeader('Header5'));
        $this->assertEquals('gl', $this->t1->getLanguage());
        $this->assertEquals('app2', $this->t1->getDomain());
    }
}
