<?php
declare(strict_types = 1);

namespace Gettext\Loader;

use Gettext\Translation;
use Gettext\Translations;

/**
 * Class to load a PO file.
 */
final class PoLoader extends Loader
{
    public function loadString(string $string, Translations $translations = null): Translations
    {
        $translations = parent::loadString($string, $translations);

        $lines = explode("\n", $string);
        $line = current($lines);
        $translation = $this->createTranslation(null, '');

        while ($line !== false) {
            $line = trim($line);
            $nextLine = next($lines);

            //Multiline
            while (substr($line, -1, 1) === '"'
                && $nextLine !== false
                && (substr(trim($nextLine), 0, 1) === '"' || substr(trim($nextLine), 0, 4) === '#~ "')
            ) {
                if (substr(trim($nextLine), 0, 1) === '"') { // Normal multiline
                    $line = substr($line, 0, -1).substr(trim($nextLine), 1);
                } elseif (substr(trim($nextLine), 0, 4) === '#~ "') { // Disabled multiline
                    $line = substr($line, 0, -1).substr(trim($nextLine), 4);
                }
                $nextLine = next($lines);
            }

            //End of translation
            if ($line === '') {
                if (!self::isEmpty($translation)) {
                    $translations->add($translation);
                }

                $translation = $this->createTranslation(null, '');
                $line = $nextLine;
                continue;
            }

            $splitLine = preg_split('/\s+/', $line, 2);
            $key = $splitLine[0];
            $data = $splitLine[1] ?? '';

            if ($key === '#~') {
                $translation->disable();

                $splitLine = preg_split('/\s+/', $data, 2);
                $key = $splitLine[0];
                $data = $splitLine[1] ?? '';
            }

            if ($data === '') {
                $line = $nextLine;
                continue;
            }

            switch ($key) {
                case '#':
                    $translation->getComments()->add($data);
                    break;
                case '#.':
                    $translation->getExtractedComments()->add($data);
                    break;
                case '#,':
                    foreach (array_map('trim', explode(',', trim($data))) as $value) {
                        $translation->getFlags()->add($value);
                    }
                    break;
                case '#:':
                    foreach (preg_split('/\s+/', trim($data)) as $value) {
                        if (preg_match('/^(.+)(:(\d*))?$/U', $value, $matches)) {
                            $line = isset($matches[3]) ? intval($matches[3]) : null;
                            $translation->getReferences()->add($matches[1], $line);
                        }
                    }
                    break;
                case 'msgctxt':
                    $translation = $translation->withContext(self::decode($data));
                    break;
                case 'msgid':
                    $translation = $translation->withOriginal(self::decode($data));
                    break;
                case 'msgid_plural':
                    $translation->setPlural(self::decode($data));
                    break;
                case 'msgstr':
                case 'msgstr[0]':
                    $translation->translate(self::decode($data));
                    break;
                case 'msgstr[1]':
                    $translation->translatePlural(self::decode($data));
                    break;
                default:
                    if (strpos($key, 'msgstr[') === 0) {
                        $p = $translation->getPluralTranslations();
                        $p[] = self::decode($data);

                        $translation->translatePlural(...$p);
                        break;
                    }
                    break;
            }

            $line = $nextLine;
        }

        if (!self::isEmpty($translation)) {
            $translations->add($translation);
        }

        //Headers
        $translation = $translations->find(null, '');

        if (!$translation) {
            return $translations;
        }

        $translations->remove($translation);

        $description = $translation->getComments()->toArray();

        if (!empty($description)) {
            $translations->setDescription(implode("\n", $description));
        }

        $flags = $translation->getFlags()->toArray();

        if (!empty($flags)) {
            $translations->getFlags()->add(...$flags);
        }

        $headers = $translations->getHeaders();

        foreach (self::parseHeaders($translation->getTranslation()) as $name => $value) {
            $headers->set($name, $value);
        }

        return $translations;
    }

    private static function parseHeaders(?string $string): array
    {
        if (empty($string)) {
            return [];
        }

        $headers = [];
        $lines = explode("\n", $string);
        $name = null;

        foreach ($lines as $line) {
            $line = self::decode($line);

            if ($line === '') {
                continue;
            }

            // Checks if it is a header definition line.
            // Useful for distinguishing between header definitions and possible continuations of a header entry.
            if (preg_match('/^[\w-]+:/', $line)) {
                $pieces = array_map('trim', explode(':', $line, 2));
                list($name, $value) = $pieces;

                $headers[$name] = $value;
                continue;
            }

            $value = $headers[$name] ?? '';
            $headers[$name] = $value.$line;
        }

        return $headers;
    }

    /**
     * Convert a string from its PO representation.
     */
    public static function decode(string $value): string
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

    private static function isEmpty(Translation $translation): bool
    {
        if (!empty($translation->getOriginal())) {
            return false;
        }

        if (!empty($translation->getTranslation())) {
            return false;
        }

        return true;
    }
}
