<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use Pest\Mutate\MutationTestCollection;
use Pest\Support\Container;
use Psr\SimpleCache\CacheInterface;

class ResultCache
{
    private static ?self $instance = null;

    private readonly CacheInterface $cache;

    /**
     * @var array<string, array<string, string>>
     */
    private array $results = [];

    public static function instance(): self
    {
        return self::$instance ?? self::$instance = new self;
    }

    public function __construct()
    {
        $this->cache = Container::getInstance()->get(CacheInterface::class); // @phpstan-ignore-line
    }

    /**
     * @return array<string, string>
     */
    public function get(MutationTestCollection $testCollection): array
    {
        return $this->results[$this->key($testCollection)] ?? // @phpstan-ignore-line
            $this->results[$this->key($testCollection)] = $this->cache->get($this->key($testCollection), []); // @phpstan-ignore-line
    }

    public function put(MutationTestCollection $testCollection): void
    {
        if ($testCollection->isComplete()) {
            $this->cache->set($this->key($testCollection), $testCollection->results());

            return;
        }

        $this->cache->set($this->key($testCollection), [...$this->get($testCollection), ...$testCollection->results()]);
    }

    private function key(MutationTestCollection $testCollection): string
    {
        return 'test-result-'.hash('xxh3', $testCollection->file->getRealPath());
    }
}
