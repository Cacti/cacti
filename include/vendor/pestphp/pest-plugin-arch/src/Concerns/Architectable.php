<?php

declare(strict_types=1);

namespace Pest\Arch\Concerns;

use Pest\Arch\Options\TestCaseOptions;

/**
 * @internal
 */
trait Architectable // @phpstan-ignore-line
{
    /**
     * The options to use when generating the architecture.
     */
    private ?TestCaseOptions $options = null;

    /**
     * Returns the architecture options.
     */
    public function arch(): TestCaseOptions
    {
        $options = $this->options ??= new TestCaseOptions;

        return $this->options = $options;
    }
}
