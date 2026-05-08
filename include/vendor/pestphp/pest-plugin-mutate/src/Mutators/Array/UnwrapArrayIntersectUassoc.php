<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayIntersectUassoc extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_intersect_uassoc` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_intersect_uassoc([1, 2, 3], [1, 2], 'strcmp');  // [tl! remove]
        $a = [1, 2, 3];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_intersect_uassoc';
    }
}
