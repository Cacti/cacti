<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapLcfirst extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `lcfirst` calls.';

    public const DIFF = <<<'DIFF'
        $a = lcfirst('Hello World');  // [tl! remove]
        $a = 'Hello World';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'lcfirst';
    }
}
