<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Logical\BooleanAndToBooleanOr;
use Pest\Mutate\Mutators\Logical\BooleanOrToBooleanAnd;
use Pest\Mutate\Mutators\Logical\CoalesceRemoveLeft;
use Pest\Mutate\Mutators\Logical\FalseToTrue;
use Pest\Mutate\Mutators\Logical\InstanceOfToFalse;
use Pest\Mutate\Mutators\Logical\InstanceOfToTrue;
use Pest\Mutate\Mutators\Logical\LogicalAndToLogicalOr;
use Pest\Mutate\Mutators\Logical\LogicalOrToLogicalAnd;
use Pest\Mutate\Mutators\Logical\LogicalXorToLogicalAnd;
use Pest\Mutate\Mutators\Logical\RemoveNot;
use Pest\Mutate\Mutators\Logical\TrueToFalse;

class LogicalSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            BooleanAndToBooleanOr::class,
            BooleanOrToBooleanAnd::class,
            CoalesceRemoveLeft::class,
            LogicalAndToLogicalOr::class,
            LogicalOrToLogicalAnd::class,
            LogicalXorToLogicalAnd::class,
            FalseToTrue::class,
            TrueToFalse::class,
            InstanceOfToTrue::class,
            InstanceOfToFalse::class,
            RemoveNot::class,
        ];
    }
}
