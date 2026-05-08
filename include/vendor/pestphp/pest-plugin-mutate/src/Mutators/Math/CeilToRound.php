<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Math;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionReplaceMutator;

class CeilToRound extends AbstractFunctionReplaceMutator
{
    public const SET = 'Math';

    public const DESCRIPTION = 'Replaces `ceil` function with `round` function.';

    public const DIFF = <<<'DIFF'
        $a = ceil(1.2);  // [tl! remove]
        $a = round(1.2);  // [tl! add]
        DIFF;

    public static function from(): string
    {
        return 'ceil';
    }

    public static function to(): string
    {
        return 'round';
    }
}
