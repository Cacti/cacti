<?php

namespace Gettext\Extractors;

use Gettext\Translations;
use Gettext\Translation;
use Gettext\Utils\HeadersExtractorTrait;

/**
 * Class to get gettext strings from php files returning arrays.
 */
class Po extends Extractor implements ExtractorInterface
{
    use HeadersExtractorTrait;

    /**
     * Parses a .po file and append the translations found in the Translations instance.
     *
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        $lines = explode("\n", $string);
        $i = 0;

        $translation = new Translation('', '');

        for ($n = count($lines); $i < $n; ++$i) {
            $line = trim($lines[$i]);
            $line = self::fixMultiLines($line, $lines, $i);

            if ($line === '') {
                if ($translation->is('', '')) {
                    self::extractHeaders($translation->getTranslation(), $translations);
                } elseif ($translation->hasOriginal()) {
                    $translations[] = $translation;
                }

                $translation = new Translation('', '');
                continue;
            }

            $splitLine = preg_split('/\s+/', $line, 2);
            $key = $splitLine[0];
            $data = isset($splitLine[1]) ? $splitLine[1] : '';

            if ($key === '#~') {
                $translation->setDisabled(true);

                $splitLine = preg_split('/\s+/', $data, 2);
                $key = $splitLine[0];
                $data = isset($splitLine[1]) ? $splitLine[1] : '';
            }

            switch ($key) {
                case '#':
                    $translation->addComment($data);
                    $append = null;
                    break;

                case '#.':
                    $translation->addExtractedComment($data);
                    $append = null;
                    break;

                case '#,':
                    foreach (array_map('trim', explode(',', trim($data))) as $value) {
                        $translation->addFlag($value);
                    }
                    $append = null;
                    break;

                case '#:':
                    foreach (preg_split('/\s+/', trim($data)) as $value) {
                        if (preg_match('/^(.+)(:(\d*))?$/U', $value, $matches)) {
                            $translation->addReference($matches[1], isset($matches[3]) ? $matches[3] : null);
                        }
                    }
                    $append = null;
                    break;

                case 'msgctxt':
                    $translation = $translation->getClone(self::convertString($data));
                    $append = 'Context';
                    break;

                case 'msgid':
                    $translation = $translation->getClone(null, self::convertString($data));
                    $append = 'Original';
                    break;

                case 'msgid_plural':
                    $translation->setPlural(self::convertString($data));
                    $append = 'Plural';
                    break;

                case 'msgstr':
                case 'msgstr[0]':
                    $translation->setTranslation(self::convertString($data));
                    $append = 'Translation';
                    break;

                case 'msgstr[1]':
                    $translation->setPluralTranslations([self::convertString($data)]);
                    $append = 'PluralTranslation';
                    break;

                default:
                    if (strpos($key, 'msgstr[') === 0) {
                        $p = $translation->getPluralTranslations();
                        $p[] = self::convertString($data);

                        $translation->setPluralTranslations($p);
                        $append = 'PluralTranslation';
                        break;
                    }

                    if (isset($append)) {
                        if ($append === 'Context') {
                            $translation = $translation->getClone($translation->getContext()
                                ."\n"
                                .self::convertString($data));
                            break;
                        }

                        if ($append === 'Original') {
                            $translation = $translation->getClone(null, $translation->getOriginal()
                                ."\n"
                                .self::convertString($data));
                            break;
                        }

                        if ($append === 'PluralTranslation') {
                            $p = $translation->getPluralTranslations();
                            $p[] = array_pop($p)."\n".self::convertString($data);
                            $translation->setPluralTranslations($p);
                            break;
                        }

                        $getMethod = 'get'.$append;
                        $setMethod = 'set'.$append;
                        $translation->$setMethod($translation->$getMethod()."\n".self::convertString($data));
                    }
                    break;
            }
        }

        if ($translation->hasOriginal() && !in_array($translation, iterator_to_array($translations))) {
            $translations[] = $translation;
        }
    }

    /**
     * Gets one string from multiline strings.
     *
     * @param string $line
     * @param array  $lines
     * @param int    &$i
     *
     * @return string
     */
    private static function fixMultiLines($line, array $lines, &$i)
    {
        for ($j = $i, $t = count($lines); $j < $t; ++$j) {
            if (substr($line, -1, 1) == '"'
                && isset($lines[$j + 1])
                && substr(trim($lines[$j + 1]), 0, 1) == '"'
            ) {
                $line = substr($line, 0, -1).substr(trim($lines[$j + 1]), 1);
            } else {
                $i = $j;
                break;
            }
        }

        return $line;
    }

    /**
     * Convert a string from its PO representation.
     *
     * @param string $value
     *
     * @return string
     */
    public static function convertString($value)
    {
        if (!$value) {
            return '';
        }

        if ($value[0] === '"') {
            $value = substr($value, 1, -1);
        }

        return strtr(
            $value,
            [
                '\\\\' => '\\',
                '\\a' => "\x07",
                '\\b' => "\x08",
                '\\t' => "\t",
                '\\n' => "\n",
                '\\v' => "\x0b",
                '\\f' => "\x0c",
                '\\r' => "\r",
                '\\"' => '"',
            ]
        );
    }
}
