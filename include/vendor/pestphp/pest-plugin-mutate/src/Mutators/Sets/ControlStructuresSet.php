<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\ControlStructures\BreakToContinue;
use Pest\Mutate\Mutators\ControlStructures\ContinueToBreak;
use Pest\Mutate\Mutators\ControlStructures\DoWhileAlwaysFalse;
use Pest\Mutate\Mutators\ControlStructures\ElseIfNegated;
use Pest\Mutate\Mutators\ControlStructures\ForAlwaysFalse;
use Pest\Mutate\Mutators\ControlStructures\ForeachEmptyIterable;
use Pest\Mutate\Mutators\ControlStructures\IfNegated;
use Pest\Mutate\Mutators\ControlStructures\TernaryNegated;
use Pest\Mutate\Mutators\ControlStructures\WhileAlwaysFalse;

class ControlStructuresSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            IfNegated::class,
            ElseIfNegated::class,
            TernaryNegated::class,
            ForAlwaysFalse::class,
            ForeachEmptyIterable::class,
            DoWhileAlwaysFalse::class,
            WhileAlwaysFalse::class,
            BreakToContinue::class,
            ContinueToBreak::class,
        ];
    }
}
