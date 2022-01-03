<?php
declare(strict_types = 1);

namespace Gettext\Scanner;

use Exception;
use Gettext\Translation;
use Gettext\Translations;

/**
 * Base class with common funtions for all scanners.
 */
abstract class Scanner implements ScannerInterface
{
    protected $translations;
    protected $defaultDomain;

    public function __construct(Translations ...$allTranslations)
    {
        foreach ($allTranslations as $translations) {
            $domain = $translations->getDomain();
            $this->translations[$domain] = $translations;
        }
    }

    public function setDefaultDomain(string $defaultDomain): void
    {
        $this->defaultDomain = $defaultDomain;
    }

    public function getDefaultDomain(): string
    {
        return $this->defaultDomain;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function scanFile(string $filename): void
    {
        $string = static::readFile($filename);

        $this->scanString($string, $filename);
    }

    abstract public function scanString(string $string, string $filename): void;

    protected function saveTranslation(
        ?string $domain,
        ?string $context,
        string $original,
        string $plural = null
    ): ?Translation {
        if (is_null($domain)) {
            $domain = $this->defaultDomain;
        }

        if (!isset($this->translations[$domain])) {
            return null;
        }

        $translation = $this->translations[$domain]->addOrMerge(
            Translation::create($context, $original)
        );

        if (isset($plural)) {
            $translation->setPlural($plural);
        }

        return $translation;
    }

    /**
     * Reads and returns the content of a file.
     */
    protected static function readFile(string $file): string
    {
        $content = @file_get_contents($file);

        if (false === $content) {
            throw new Exception("Cannot read the file '$file', probably permissions");
        }

        return $content;
    }
}
