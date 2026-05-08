<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapStrShuffle extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `str_shuffle` calls.';

    public const DIFF = <<<'DIFF'
        $a = str_shuffle('Hello World');  // [tl! remove]
        $a = 'Hello World';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'str_shuffle';
    }
}
