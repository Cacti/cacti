<?php

namespace Gettext\Utils;

use Gettext\Translations;

/**
 * Trait used by all generators that exports the translations to plain dictionary (original => singular-translation).
 */
trait DictionaryTrait
{
    use HeadersGeneratorTrait;
    use HeadersExtractorTrait;

    /**
     * Returns a plain dictionary with the format [original => translation].
     *
     * @param Translations $translations
     * @param bool         $includeHeaders
     *
     * @return array
     */
    private static function toArray(Translations $translations, $includeHeaders)
    {
        $messages = [];

        if ($includeHeaders) {
            $messages[''] = self::generateHeaders($translations);
        }

        foreach ($translations as $translation) {
            if ($translation->isDisabled()) {
                continue;
            }

            $messages[$translation->getOriginal()] = $translation->getTranslation();
        }

        return $messages;
    }

    /**
     * Extract the entries from a dictionary.
     *
     * @param array        $messages
     * @param Translations $translations
     */
    private static function fromArray(array $messages, Translations $translations)
    {
        foreach ($messages as $original => $translation) {
            if ($original === '') {
                self::extractHeaders($translation, $translations);
                continue;
            }

            $translations->insert(null, $original)->setTranslation($translation);
        }
    }
}
