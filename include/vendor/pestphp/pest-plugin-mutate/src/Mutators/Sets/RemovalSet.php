<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Removal\RemoveArrayItem;
use Pest\Mutate\Mutators\Removal\RemoveEarlyReturn;
use Pest\Mutate\Mutators\Removal\RemoveFunctionCall;
use Pest\Mutate\Mutators\Removal\RemoveMethodCall;
use Pest\Mutate\Mutators\Removal\RemoveNullSafeOperator;

class RemovalSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            RemoveArrayItem::class,
            RemoveEarlyReturn::class,
            RemoveFunctionCall::class,
            RemoveMethodCall::class,
            RemoveNullSafeOperator::class,
        ];
    }
}
