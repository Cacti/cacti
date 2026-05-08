<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use Pest\Mutate\Mutation;
use Pest\Support\Container;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Finder\SplFileInfo;

class MutationCache
{
    private static ?self $instance = null;

    private readonly CacheInterface $cache;

    public static function instance(): self
    {
        return self::$instance ?? self::$instance = new self;
    }

    public function __construct()
    {
        $this->cache = Container::getInstance()->get(CacheInterface::class); // @phpstan-ignore-line
    }

    /**
     * @param  array<int, int>  $linesToMutate
     */
    public function has(SplFileInfo $file, string $content, array $linesToMutate, string $mutator): bool
    {
        return $this->cache->has($this->getKey($file, $content, $linesToMutate, $mutator));
    }

    /**
     * @param  array<int, int>  $linesToMutate
     * @return array<int, Mutation>
     */
    public function get(SplFileInfo $file, string $content, array $linesToMutate, string $mutator): array
    {
        return $this->cache->get($this->getKey($file, $content, $linesToMutate, $mutator)); // @phpstan-ignore-line
    }

    /**
     * @param  array<int, int>  $linesToMutate
     * @param  array<int, Mutation>  $mutations
     */
    public function put(SplFileInfo $file, string $content, array $linesToMutate, string $mutator, array $mutations): void
    {
        $this->cache->set($this->getKey($file, $content, $linesToMutate, $mutator), $mutations);
    }

    /**
     * @param  array<int, int>  $linesToMutate
     */
    private function getKey(SplFileInfo $file, string $content, array $linesToMutate, string $mutator): string
    {
        return $file->getRealPath().'::'.$mutator.'::'.hash('xxh3', $content).'::'.hash('xxh3', implode(',', $linesToMutate));
    }
}
