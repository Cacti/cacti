<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionReplaceMutator;

class ArrayPopToArrayShift extends AbstractFunctionReplaceMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Replaces `array_pop` with `array_shift`.';

    public const DIFF = <<<'DIFF'
        $a = array_pop([1, 2, 3]);  // [tl! remove]
        $a = array_shift([1, 2, 3]);  // [tl! add]
        DIFF;

    public static function from(): string
    {
        return 'array_pop';
    }

    public static function to(): string
    {
        return 'array_shift';
    }
}
