<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Assignment\BitwiseAndToBitwiseOr;
use Pest\Mutate\Mutators\Assignment\BitwiseOrToBitwiseAnd;
use Pest\Mutate\Mutators\Assignment\BitwiseXorToBitwiseAnd;
use Pest\Mutate\Mutators\Assignment\CoalesceEqualToEqual;
use Pest\Mutate\Mutators\Assignment\ConcatEqualToEqual;
use Pest\Mutate\Mutators\Assignment\DivideEqualToMultiplyEqual;
use Pest\Mutate\Mutators\Assignment\MinusEqualToPlusEqual;
use Pest\Mutate\Mutators\Assignment\ModulusEqualToMultiplyEqual;
use Pest\Mutate\Mutators\Assignment\MultiplyEqualToDivideEqual;
use Pest\Mutate\Mutators\Assignment\PlusEqualToMinusEqual;
use Pest\Mutate\Mutators\Assignment\PowerEqualToMultiplyEqual;
use Pest\Mutate\Mutators\Assignment\ShiftLeftToShiftRight;
use Pest\Mutate\Mutators\Assignment\ShiftRightToShiftLeft;
use Pest\Mutate\Mutators\Concerns\HasName;

class AssignmentSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            BitwiseAndToBitwiseOr::class,
            BitwiseOrToBitwiseAnd::class,
            BitwiseXorToBitwiseAnd::class,
            CoalesceEqualToEqual::class,
            ConcatEqualToEqual::class,
            DivideEqualToMultiplyEqual::class,
            MinusEqualToPlusEqual::class,
            ModulusEqualToMultiplyEqual::class,
            MultiplyEqualToDivideEqual::class,
            PlusEqualToMinusEqual::class,
            PowerEqualToMultiplyEqual::class,
            ShiftLeftToShiftRight::class,
            ShiftRightToShiftLeft::class,
        ];
    }
}
