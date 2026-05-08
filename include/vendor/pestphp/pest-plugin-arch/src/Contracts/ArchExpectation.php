<?php

declare(strict_types=1);

namespace Pest\Arch\Contracts;

use Pest\Expectation;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * @internal
 *
 * @mixin Expectation<string>
 */
interface ArchExpectation
{
    /**
     * Ignores the given "targets" or "dependencies".
     *
     * @param  array<int, string>|string  $targetsOrDependencies
     * @return $this
     */
    public function ignoring(array|string $targetsOrDependencies): self;

    /**
     * Ignores global "user defined" functions.
     *
     * @return $this
     */
    public function ignoringGlobalFunctions(): self;

    /**
     * Merge the given exclude callbacks.
     *
     * @param  array<int, callable(ObjectDescription): bool>  $callbacks
     *
     * @internal
     */
    public function mergeExcludeCallbacks(array $callbacks): void;

    /**
     * Returns the exclude callbacks.
     *
     * @return array<int, callable(ObjectDescription): bool>
     *
     * @internal
     */
    public function excludeCallbacks(): array;
}
