<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Math;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionReplaceMutator;

class MinToMax extends AbstractFunctionReplaceMutator
{
    public const SET = 'Math';

    public const DESCRIPTION = 'Replaces `min` function with `max` function.';

    public const DIFF = <<<'DIFF'
        $a = min(1, 2);  // [tl! remove]
        $a = max(1, 2);  // [tl! add]
        DIFF;

    public static function from(): string
    {
        return 'min';
    }

    public static function to(): string
    {
        return 'max';
    }
}
