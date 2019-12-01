<?php

namespace Gettext\Tests;

use Gettext\Translations;
use Gettext\Merge;

class MergeEntriesTest extends AbstractTest
{
    protected $t1;
    protected $t2;

    public function setUp()
    {
        $this->t1 = new Translations();
        $this->t2 = new Translations();

        $this->t1->insert(null, 'message-1');
        $this->t1->insert(null, 'message-2');

        $this->t2->insert(null, 'message-2');
        $this->t2->insert(null, 'message-3');
    }

    public function testAdd()
    {
        $options = Merge::ADD;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertNotFalse($this->t1->find(null, 'message-1'));
        $this->assertNotFalse($this->t1->find(null, 'message-2'));
        $this->assertNotFalse($this->t1->find(null, 'message-3'));
    }

    public function testRemove()
    {
        $options = Merge::REMOVE;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertFalse($this->t1->find(null, 'message-1'));
        $this->assertNotFalse($this->t1->find(null, 'message-2'));
        $this->assertFalse($this->t1->find(null, 'message-3'));
    }

    public function testAddRemove()
    {
        $options = Merge::REMOVE | Merge::ADD;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertFalse($this->t1->find(null, 'message-1'));
        $this->assertNotFalse($this->t1->find(null, 'message-2'));
        $this->assertNotFalse($this->t1->find(null, 'message-3'));
    }

    public function testNone()
    {
        $options = 0;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertNotFalse($this->t1->find(null, 'message-1'));
        $this->assertNotFalse($this->t1->find(null, 'message-2'));
        $this->assertFalse($this->t1->find(null, 'message-3'));
    }
}
