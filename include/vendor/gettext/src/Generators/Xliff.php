<?php

namespace Gettext\Generators;

use Gettext\Translations;
use DOMDocument;

class Xliff extends Generator implements GeneratorInterface
{
    /**
     * {@inheritdoc}
     */
    public static function toString(Translations $translations, array $options = [])
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $xliff = $dom->appendChild($dom->createElement('xliff'));
        $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:2.0');
        $xliff->setAttribute('version', '2.0');
        $xliff->setAttribute('srcLang', $translations->getLanguage());
        $xliff->setAttribute('trgLang', $translations->getLanguage());
        $file = $xliff->appendChild($dom->createElement('file'));
        $file->setAttribute('id', $translations->getDomain().'.'.$translations->getLanguage());

        //Save headers as notes
        $notes = $dom->createElement('notes');

        foreach ($translations->getHeaders() as $name => $value) {
            $notes->appendChild(self::createTextNode($dom, 'note', $value))->setAttribute('id', $name);
        }

        if ($notes->hasChildNodes()) {
            $file->appendChild($notes);
        }

        foreach ($translations as $translation) {
            $unit = $dom->createElement('unit');
            $unit->setAttribute('id', md5($translation->getContext().$translation->getOriginal()));

            //Save comments as notes
            $notes = $dom->createElement('notes');

            $notes->appendChild(self::createTextNode($dom, 'note', $translation->getContext()))
                ->setAttribute('category', 'context');

            foreach ($translation->getComments() as $comment) {
                $notes->appendChild(self::createTextNode($dom, 'note', $comment))
                    ->setAttribute('category', 'comment');
            }

            foreach ($translation->getExtractedComments() as $comment) {
                $notes->appendChild(self::createTextNode($dom, 'note', $comment))
                    ->setAttribute('category', 'extracted-comment');
            }

            foreach ($translation->getFlags() as $flag) {
                $notes->appendChild(self::createTextNode($dom, 'note', $flag))
                    ->setAttribute('category', 'flag');
            }

            foreach ($translation->getReferences() as $reference) {
                $notes->appendChild(self::createTextNode($dom, 'note', $reference[0].':'.$reference[1]))
                    ->setAttribute('category', 'reference');
            }

            $unit->appendChild($notes);

            $segment = $unit->appendChild($dom->createElement('segment'));
            $segment->appendChild(self::createTextNode($dom, 'source', $translation->getOriginal()));
            $segment->appendChild(self::createTextNode($dom, 'target', $translation->getTranslation()));

            foreach ($translation->getPluralTranslations() as $plural) {
                if ($plural !== '') {
                    $segment->appendChild(self::createTextNode($dom, 'target', $plural));
                }
            }

            $file->appendChild($unit);
        }

        return $dom->saveXML();
    }

    private static function createTextNode(DOMDocument $dom, $name, $string)
    {
        $node = $dom->createElement($name);
        $text = (preg_match('/[&<>]/', $string) === 1)
             ? $dom->createCDATASection($string)
             : $dom->createTextNode($string);
        $node->appendChild($text);

        return $node;
    }
}
