<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;

class UnwrapStrReplace extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Unwraps `str_replace` calls.';

    public const DIFF = <<<'DIFF'
        $a = str_replace('Hello', 'Hi', 'Hello World');  // [tl! remove]
        $a = 'Hello World';  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'str_replace';
    }

    public static function mutate(Node $node): Node
    {
        /** @var FuncCall $node */
        return $node->args[2]->value; // @phpstan-ignore-line
    }
}
