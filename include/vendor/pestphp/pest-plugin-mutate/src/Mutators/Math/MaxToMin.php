<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Math;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionReplaceMutator;

class MaxToMin extends AbstractFunctionReplaceMutator
{
    public const SET = 'Math';

    public const DESCRIPTION = 'Replaces `max` function with `min` function.';

    public const DIFF = <<<'DIFF'
        $a = max(1, 2);  // [tl! remove]
        $a = min(1, 2);  // [tl! add]
        DIFF;

    public static function from(): string
    {
        return 'max';
    }

    public static function to(): string
    {
        return 'min';
    }
}
