<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Math\CeilToFloor;
use Pest\Mutate\Mutators\Math\CeilToRound;
use Pest\Mutate\Mutators\Math\FloorToCiel;
use Pest\Mutate\Mutators\Math\FloorToRound;
use Pest\Mutate\Mutators\Math\MaxToMin;
use Pest\Mutate\Mutators\Math\MinToMax;
use Pest\Mutate\Mutators\Math\RoundToCeil;
use Pest\Mutate\Mutators\Math\RoundToFloor;

class MathSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            MinToMax::class,
            MaxToMin::class,
            RoundToFloor::class,
            RoundToCeil::class,
            FloorToRound::class,
            FloorToCiel::class,
            CeilToFloor::class,
            CeilToRound::class,
        ];
    }
}
