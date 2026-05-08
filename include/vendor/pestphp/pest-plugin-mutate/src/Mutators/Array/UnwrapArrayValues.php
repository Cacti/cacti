<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayValues extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_values` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_values(['a' => 1, 'b' => 2, 'c' => 3]);  // [tl! remove]
        $a = ['a' => 1, 'b' => 2, 'c' => 3];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_values';
    }
}
