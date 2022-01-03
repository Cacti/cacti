<?php
declare(strict_types = 1);

namespace Gettext;

use ArrayIterator;
use Countable;
use Gettext\Languages\Language;
use InvalidArgumentException;
use IteratorAggregate;
use ReturnTypeWillChange;

/**
 * Class to manage a collection of translations under the same domain.
 */
class Translations implements Countable, IteratorAggregate
{
    protected $description;
    protected $translations = [];
    protected $headers;
    protected $flags;

    public static function create(string $domain = null, string $language = null): Translations
    {
        $translations = new static();

        if (isset($domain)) {
            $translations->setDomain($domain);
        }

        if (isset($language)) {
            $translations->setLanguage($language);
        }

        return $translations;
    }

    protected function __construct()
    {
        $this->headers = new Headers();
        $this->flags = new Flags();
    }

    public function __clone()
    {
        foreach ($this->translations as $id => $translation) {
            $this->translations[$id] = clone $translation;
        }

        $this->headers = clone $this->headers;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getFlags(): Flags
    {
        return $this->flags;
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'headers' => $this->headers->toArray(),
            'flags' => $this->flags->toArray(),
            'translations' => array_map(
                function ($translation) {
                    return $translation->toArray();
                },
                array_values($this->translations)
            ),
        ];
    }

    #[ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->translations);
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function count(): int
    {
        return count($this->translations);
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function add(Translation $translation): self
    {
        $id = $translation->getId();

        $this->translations[$id] = $translation;

        return $this;
    }

    public function addOrMerge(Translation $translation, int $mergeStrategy = 0): Translation
    {
        $id = $translation->getId();

        if (isset($this->translations[$id])) {
            return $this->translations[$id] = $this->translations[$id]->mergeWith($translation, $mergeStrategy);
        }

        return $this->translations[$id] = $translation;
    }

    public function remove(Translation $translation): self
    {
        $key = array_search($translation, $this->translations);

        if ($key !== false) {
            unset($this->translations[$key]);
        }

        return $this;
    }

    public function setDomain(string $domain): self
    {
        $this->getHeaders()->setDomain($domain);

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->getHeaders()->getDomain();
    }

    public function setLanguage(string $language): self
    {
        $info = Language::getById($language);

        if (empty($info)) {
            throw new InvalidArgumentException(sprintf('The language "%s" is not valid', $language));
        }

        $this->getHeaders()
            ->setLanguage($language)
            ->setPluralForm(count($info->categories), $info->formula);

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->getHeaders()->getLanguage();
    }

    public function find(?string $context, string $original): ?Translation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getContext() === $context && $translation->getOriginal() === $original) {
                return $translation;
            }
        }

        return null;
    }

    public function mergeWith(Translations $translations, int $strategy = 0): Translations
    {
        $merged = clone $this;

        if ($strategy & Merge::HEADERS_THEIRS) {
            $merged->headers = clone $translations->headers;
        } elseif (!($strategy & Merge::HEADERS_OURS)) {
            $merged->headers = $merged->headers->mergeWith($translations->headers);
        }

        if ($strategy & Merge::FLAGS_THEIRS) {
            $merged->flags = clone $translations->flags;
        } elseif (!($strategy & Merge::FLAGS_OURS)) {
            $merged->flags = $merged->flags->mergeWith($translations->flags);
        }

        if (!$merged->description) {
            $merged->description = $translations->description;
        }

        foreach ($translations as $id => $translation) {
            if (isset($merged->translations[$id])) {
                $translation = $merged->translations[$id]->mergeWith($translation, $strategy);
            }

            $merged->add($translation);
        }

        if ($strategy & Merge::TRANSLATIONS_THEIRS) {
            $merged->translations = array_intersect_key($merged->translations, $translations->translations);
        } elseif ($strategy & Merge::TRANSLATIONS_OURS) {
            $merged->translations = array_intersect_key($merged->translations, $this->translations);
        }

        return $merged;
    }
}
