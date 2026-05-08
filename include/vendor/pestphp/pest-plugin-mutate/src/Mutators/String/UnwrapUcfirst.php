<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapUcfirst extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `ucfirst` calls.';

    public const DIFF = <<<'DIFF'
        $a = ucfirst('hello world');  // [tl! remove]
        $a = 'hello world';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'ucfirst';
    }
}
