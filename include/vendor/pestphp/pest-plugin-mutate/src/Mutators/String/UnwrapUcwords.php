<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapUcwords extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `ucwords` calls.';

    public const DIFF = <<<'DIFF'
        $a = ucwords('hello world');  // [tl! remove]
        $a = 'hello world';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'ucwords';
    }
}
