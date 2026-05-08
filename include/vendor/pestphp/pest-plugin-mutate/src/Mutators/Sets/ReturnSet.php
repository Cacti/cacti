<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Return\AlwaysReturnEmptyArray;
use Pest\Mutate\Mutators\Return\AlwaysReturnNull;

class ReturnSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            AlwaysReturnNull::class,
            AlwaysReturnEmptyArray::class,
        ];
    }
}
