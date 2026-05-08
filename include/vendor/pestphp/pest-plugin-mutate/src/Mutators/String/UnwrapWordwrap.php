<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapWordwrap extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `wordwrap` calls.';

    public const DIFF = <<<'DIFF'
        $a = wordwrap('Hello World', 5);  // [tl! remove]
        $a = 'Hello World';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'wordwrap';
    }
}
