<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Casting\RemoveArrayCast;
use Pest\Mutate\Mutators\Casting\RemoveBooleanCast;
use Pest\Mutate\Mutators\Casting\RemoveDoubleCast;
use Pest\Mutate\Mutators\Casting\RemoveIntegerCast;
use Pest\Mutate\Mutators\Casting\RemoveObjectCast;
use Pest\Mutate\Mutators\Casting\RemoveStringCast;
use Pest\Mutate\Mutators\Concerns\HasName;

class CastingSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            RemoveArrayCast::class,
            RemoveBooleanCast::class,
            RemoveDoubleCast::class,
            RemoveIntegerCast::class,
            RemoveObjectCast::class,
            RemoveStringCast::class,
        ];
    }
}
