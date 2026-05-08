<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayCombine extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_combine` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_combine([1, 2, 3], [3, 4]);  // [tl! remove]
        $a = [1, 2, 3]  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_combine';
    }
}
