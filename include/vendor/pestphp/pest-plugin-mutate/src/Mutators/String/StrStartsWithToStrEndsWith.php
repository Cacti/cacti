<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionReplaceMutator;

class StrStartsWithToStrEndsWith extends AbstractFunctionReplaceMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Replaces `str_starts_with` with `str_ends_with`.';

    public const DIFF = <<<'DIFF'
        $a = str_starts_with('Hello World', 'World');  // [tl! remove]
        $a = str_ends_with('Hello World', 'World');  // [tl! add]
        DIFF;

    public static function from(): string
    {
        return 'str_starts_with';
    }

    public static function to(): string
    {
        return 'str_ends_with';
    }
}
