<?php
declare(strict_types = 1);

namespace Gettext\Loader;

use Exception;
use Gettext\Translation;
use Gettext\Translations;

/**
 * Class to load a MO file.
 */
final class MoLoader extends Loader
{
    private $string;
    private $position;
    private $length;

    private const MAGIC1 = -1794895138;
    private const MAGIC2 = -569244523;
    private const MAGIC3 = 2500072158;

    public function loadString(string $string, Translations $translations = null): Translations
    {
        $translations = parent::loadString($string, $translations);
        $this->init($string);

        $magic = $this->readInt('V');

        if (($magic === self::MAGIC1) || ($magic === self::MAGIC3)) { //to make sure it works for 64-bit platforms
            $byteOrder = 'V'; //low endian
        } elseif ($magic === (self::MAGIC2 & 0xFFFFFFFF)) {
            $byteOrder = 'N'; //big endian
        } else {
            throw new Exception('Not MO file');
        }

        $this->readInt($byteOrder);

        $total = $this->readInt($byteOrder); //total string count
        $originals = $this->readInt($byteOrder); //offset of original table
        $tran = $this->readInt($byteOrder); //offset of translation table

        $this->seekto($originals);
        $table_originals = $this->readIntArray($byteOrder, $total * 2);

        $this->seekto($tran);
        $table_translations = $this->readIntArray($byteOrder, $total * 2);

        for ($i = 0; $i < $total; ++$i) {
            $next = $i * 2;

            $this->seekto($table_originals[$next + 2]);
            $original = $this->read($table_originals[$next + 1]);

            $this->seekto($table_translations[$next + 2]);
            $translated = $this->read($table_translations[$next + 1]);

            // Headers
            if ($original === '') {
                foreach (explode("\n", $translated) as $headerLine) {
                    if ($headerLine === '') {
                        continue;
                    }

                    $headerChunks = preg_split('/:\s*/', $headerLine, 2);
                    $translations->getHeaders()->set($headerChunks[0], isset($headerChunks[1]) ? $headerChunks[1] : '');
                }

                continue;
            }

            $context = $plural = null;
            $chunks = explode("\x04", $original, 2);

            if (isset($chunks[1])) {
                list($context, $original) = $chunks;
            }

            $chunks = explode("\x00", $original, 2);

            if (isset($chunks[1])) {
                list($original, $plural) = $chunks;
            }

            $translation = $this->createTranslation($context, $original, $plural);
            $translations->add($translation);

            if ($translated === '') {
                continue;
            }

            if ($plural === null) {
                $translation->translate($translated);
                continue;
            }

            $v = explode("\x00", $translated);
            $translation->translate(array_shift($v));
            $translation->translatePlural(...array_filter($v));
        }

        return $translations;
    }

    private function init(string $string): void
    {
        $this->string = $string;
        $this->position = 0;
        $this->length = strlen($string);
    }

    private function read(int $bytes): string
    {
        $data = substr($this->string, $this->position, $bytes);

        $this->seekTo($this->position + $bytes);

        return $data;
    }

    private function seekTo(int $position): void
    {
        $this->position = ($this->length < $position) ? $this->length : $position;
    }

    private function readInt(string $byteOrder): int
    {
        if (($read = $this->read(4)) === false) {
            return 0;
        }

        $read = (array) unpack($byteOrder, $read);

        return (int) array_shift($read);
    }

    private function readIntArray(string $byteOrder, int $count): array
    {
        return unpack($byteOrder.$count, $this->read(4 * $count)) ?: [];
    }
}
