<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayFlip extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_flip` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_flip(['foo' => 1, 'bar' => 2]);  // [tl! remove]
        $a = ['foo' => 1, 'bar' => 2];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_flip';
    }
}
