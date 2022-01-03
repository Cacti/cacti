<?php
declare(strict_types = 1);

namespace Gettext\Generator;

use Gettext\Translations;

abstract class Generator implements GeneratorInterface
{
    public function generateFile(Translations $translations, string $filename): bool
    {
        $content = $this->generateString($translations);

        return file_put_contents($filename, $content) !== false;
    }

    abstract public function generateString(Translations $translations): string;
}
