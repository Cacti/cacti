<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Number\DecrementFloat;
use Pest\Mutate\Mutators\Number\DecrementInteger;
use Pest\Mutate\Mutators\Number\IncrementFloat;
use Pest\Mutate\Mutators\Number\IncrementInteger;

class NumberSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            DecrementFloat::class,
            IncrementFloat::class,
            DecrementInteger::class,
            IncrementInteger::class,
        ];
    }
}
