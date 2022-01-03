<?php
declare(strict_types = 1);

namespace Gettext\Generator;

use Gettext\Translations;

interface GeneratorInterface
{
    public function generateFile(Translations $translations, string $filename): bool;

    public function generateString(Translations $translations): string;
}
