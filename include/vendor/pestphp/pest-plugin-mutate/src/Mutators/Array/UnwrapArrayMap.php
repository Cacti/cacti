<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Array;

use Pest\Mutate\Mutators\Abstract\AbstractFunctionCallUnwrapMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;

class UnwrapArrayMap extends AbstractFunctionCallUnwrapMutator
{
    public const SET = 'Array';

    public const DESCRIPTION = 'Unwraps `array_map` calls.';

    public const DIFF = <<<'DIFF'
        $a = array_map(fn ($value) => $value + 1, [1, 2, 3]);  // [tl! remove]
        $a = [1, 2, 3];  // [tl! add]
        DIFF;

    public static function functionName(): string
    {
        return 'array_map';
    }

    public static function mutate(Node $node): Node
    {
        /** @var FuncCall $node */
        return $node->args[1]->value; // @phpstan-ignore-line
    }
}
