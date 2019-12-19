<?php

namespace Gettext\Extractors;

use Exception;
use Gettext\Translations;
use Gettext\Utils\StringReader;

/**
 * Class to get gettext strings from .mo files.
 */
class Mo extends Extractor implements ExtractorInterface
{
    const MAGIC1 = -1794895138;
    const MAGIC2 = -569244523;
    const MAGIC3 = 2500072158;

    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        $stream = new StringReader($string);
        $magic = self::readInt($stream, 'V');

        if (($magic === self::MAGIC1) || ($magic === self::MAGIC3)) { //to make sure it works for 64-bit platforms
            $byteOrder = 'V'; //low endian
        } elseif ($magic === (self::MAGIC2 & 0xFFFFFFFF)) {
            $byteOrder = 'N'; //big endian
        } else {
            throw new Exception('Not MO file');
        }

        self::readInt($stream, $byteOrder);

        $total = self::readInt($stream, $byteOrder); //total string count
        $originals = self::readInt($stream, $byteOrder); //offset of original table
        $tran = self::readInt($stream, $byteOrder); //offset of translation table

        $stream->seekto($originals);
        $table_originals = self::readIntArray($stream, $byteOrder, $total * 2);

        $stream->seekto($tran);
        $table_translations = self::readIntArray($stream, $byteOrder, $total * 2);

        for ($i = 0; $i < $total; ++$i) {
            $next = $i * 2;

            $stream->seekto($table_originals[$next + 2]);
            $original = $stream->read($table_originals[$next + 1]);

            $stream->seekto($table_translations[$next + 2]);
            $translated = $stream->read($table_translations[$next + 1]);

            if ($original === '') {
                // Headers
                foreach (explode("\n", $translated) as $headerLine) {
                    if ($headerLine === '') {
                        continue;
                    }

                    $headerChunks = preg_split('/:\s*/', $headerLine, 2);
                    $translations->setHeader($headerChunks[0], isset($headerChunks[1]) ? $headerChunks[1] : '');
                }

                continue;
            }

            $chunks = explode("\x04", $original, 2);

            if (isset($chunks[1])) {
                $context = $chunks[0];
                $original = $chunks[1];
            } else {
                $context = '';
            }

            $chunks = explode("\x00", $original, 2);

            if (isset($chunks[1])) {
                $original = $chunks[0];
                $plural = $chunks[1];
            } else {
                $plural = '';
            }

            $translation = $translations->insert($context, $original, $plural);

            if ($translated === '') {
                continue;
            }

            if ($plural === '') {
                $translation->setTranslation($translated);
                continue;
            }

            $v = explode("\x00", $translated);
            $translation->setTranslation(array_shift($v));
            $translation->setPluralTranslations($v);
        }
    }

    /**
     * @param StringReader $stream
     * @param string       $byteOrder
     */
    private static function readInt(StringReader $stream, $byteOrder)
    {
        if (($read = $stream->read(4)) === false) {
            return false;
        }

        $read = unpack($byteOrder, $read);

        return array_shift($read);
    }

    /**
     * @param StringReader $stream
     * @param string       $byteOrder
     * @param int          $count
     */
    private static function readIntArray(StringReader $stream, $byteOrder, $count)
    {
        return unpack($byteOrder.$count, $stream->read(4 * $count));
    }
}
