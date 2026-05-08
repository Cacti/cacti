<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;

class UnwrapHtmlspecialcharsDecode extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `htmlspecialchars_decode` calls.';

    public const DIFF = <<<'DIFF'
        $a = htmlspecialchars_decode('&lt;h1&gt;Hello World&lt;/h1&gt;');  // [tl! remove]
        $a = '&lt;h1&gt;Hello World&lt;/h1&gt;';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'htmlspecialchars_decode';
    }
}
