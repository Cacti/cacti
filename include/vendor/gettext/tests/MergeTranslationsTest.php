<?php

namespace Gettext\Tests;

use Gettext\Translation;
use Gettext\Merge;

class MergeTranslationsTest extends AbstractTest
{
    protected $t1;
    protected $t2;

    public function setUp()
    {
        $this->t1 = new Translation(null, 'original');
        $this->t2 = new Translation(null, 'original');

        $this->t1->setTranslation('translation-1');
        $this->t2->setTranslation('translation-2');

        $this->t2->setPlural('');
        $this->t2->setPlural('plural-2');

        $this->t1->setPluralTranslations(['plural-translation-1']);
        $this->t2->setPluralTranslations(['plural-translation-2']);

        $this->t1->addComment('comment 1');
        $this->t1->addComment('comment 2');
        $this->t2->addComment('comment 2');
        $this->t2->addComment('comment 3');

        $this->t1->addExtractedComment('extracted comment 1');
        $this->t1->addExtractedComment('extracted comment 2');
        $this->t2->addExtractedComment('extracted comment 2');
        $this->t2->addExtractedComment('extracted comment 3');

        $this->t1->addReference('filename.php', 1);
        $this->t1->addReference('filename.php', 2);
        $this->t2->addReference('filename.php', 2);
        $this->t2->addReference('filename.php', 3);

        $this->t1->addFlag('flag-1');
        $this->t1->addFlag('flag-2');
        $this->t2->addFlag('flag-2');
        $this->t2->addFlag('flag-3');
    }

    public function testComments()
    {
        $options = Merge::DEFAULTS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['comment 1', 'comment 2', 'comment 3'], $this->t1->getComments());
    }

    public function testOursComments()
    {
        $options = Merge::COMMENTS_OURS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['comment 1', 'comment 2'], $this->t1->getComments());
    }

    public function testTheirsComments()
    {
        $options = Merge::COMMENTS_THEIRS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['comment 2', 'comment 3'], $this->t1->getComments());
    }

    public function testExtractedComments()
    {
        $options = Merge::DEFAULTS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['extracted comment 1', 'extracted comment 2', 'extracted comment 3'], $this->t1->getExtractedComments());
    }

    public function testOursExtractedComments()
    {
        $options = Merge::EXTRACTED_COMMENTS_OURS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['extracted comment 1', 'extracted comment 2'], $this->t1->getExtractedComments());
    }

    public function testTheirsExtractedComments()
    {
        $options = Merge::EXTRACTED_COMMENTS_THEIRS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['extracted comment 2', 'extracted comment 3'], $this->t1->getExtractedComments());
    }

    public function testReferences()
    {
        $options = Merge::DEFAULTS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals([['filename.php', 1], ['filename.php', 2], ['filename.php', 3]], $this->t1->getReferences());
    }

    public function testOursReferences()
    {
        $options = Merge::REFERENCES_OURS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals([['filename.php', 1], ['filename.php', 2]], $this->t1->getReferences());
    }

    public function testTheirsReferences()
    {
        $options = Merge::REFERENCES_THEIRS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals([['filename.php', 2], ['filename.php', 3]], $this->t1->getReferences());
    }

    public function testFlags()
    {
        $options = Merge::DEFAULTS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['flag-1', 'flag-2', 'flag-3'], $this->t1->getFlags());
    }

    public function testOursFlags()
    {
        $options = Merge::FLAGS_OURS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['flag-1', 'flag-2'], $this->t1->getFlags());
    }

    public function testTheirsFlags()
    {
        $options = Merge::FLAGS_THEIRS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals(['flag-2', 'flag-3'], $this->t1->getFlags());
    }

    public function testTranslation()
    {
        $options = Merge::DEFAULTS;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals('translation-1', $this->t1->getTranslation());
        $this->assertEquals('plural-2', $this->t1->getPlural());
        $this->assertEquals(['plural-translation-1'], $this->t1->getPluralTranslations());
    }

    public function testTranslationOverride()
    {
        $options = Merge::DEFAULTS | Merge::TRANSLATION_OVERRIDE;

        $this->t1->mergeWith($this->t2, $options);

        $this->assertEquals('translation-2', $this->t1->getTranslation());
        $this->assertEquals('plural-2', $this->t1->getPlural());
        $this->assertEquals(['plural-translation-2'], $this->t1->getPluralTranslations());
    }
}
