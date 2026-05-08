<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Laravel\Remove\LaravelRemoveStringableUpper;
use Pest\Mutate\Mutators\Laravel\Unwrap\LaravelUnwrapStrUpper;

class LaravelSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            LaravelRemoveStringableUpper::class,
            LaravelUnwrapStrUpper::class,
        ];
    }
}
