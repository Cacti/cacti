<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayUdiffUassoc extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_udiff_uassoc` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_udiff_uassoc([1, 2, 3], [1, 2, 4], fn ($a, $b) => $a <=> $b, fn ($a, $b) => $a <=> $b);  // [tl! remove]
        $a = [1, 2, 3];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_udiff_uassoc';
    }
}
