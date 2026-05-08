<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayDiffUkey extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_diff_ukey` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_diff_ukey(['foo' => 1, 'bar' => 2], ['foo' => 1], 'strcmp');  // [tl! remove]
        $a = ['foo' => 1, 'bar' => 2];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_diff_ukey';
    }
}
