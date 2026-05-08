<?php

declare(strict_types=1);

namespace Pest\Arch\Options;

use Pest\Arch\Support\UserDefinedFunctions;

/**
 * @internal
 */
final class TestCaseOptions
{
    /**
     * The list of "targets" or "dependencies" to ignore.
     *
     * @var array<int, string>
     */
    public array $ignore = [];

    /**
     * Ignores the given "targets" or "dependencies".
     *
     * @param  array<int, string>|string  $targetsOrDependencies
     * @return $this
     */
    public function ignore(array|string $targetsOrDependencies): self
    {
        $targetsOrDependencies = is_array($targetsOrDependencies) ? $targetsOrDependencies : [$targetsOrDependencies];

        $this->ignore = [...$this->ignore, ...$targetsOrDependencies];

        return $this;
    }

    /**
     * Ignores global "user defined" functions.
     *
     * @return $this
     */
    public function ignoreGlobalFunctions(): self
    {
        return $this->ignore(UserDefinedFunctions::get());
    }
}
