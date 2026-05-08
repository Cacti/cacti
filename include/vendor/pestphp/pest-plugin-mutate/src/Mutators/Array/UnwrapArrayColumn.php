<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapArrayColumn extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_column` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_column([['id' => 1], ['id' => 2]], 'id');  // [tl! remove]
        $a = [['id' => 1], ['id' => 2]];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_column';
    }
}
