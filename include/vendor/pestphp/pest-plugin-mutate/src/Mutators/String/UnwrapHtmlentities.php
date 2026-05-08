<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapHtmlentities extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `htmlentities` calls.';

    public const DIFF = <<<'DIFF'
        $a = htmlentities('<h1>Hello World</h1>');  // [tl! remove]
        $a = '<h1>Hello World</h1>';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'htmlentities';
    }
}
