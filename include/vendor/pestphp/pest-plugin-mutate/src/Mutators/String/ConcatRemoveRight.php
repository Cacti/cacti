<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;

class ConcatRemoveRight extends AbstractMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Removes the right part of a concat expression.';

    public const DIFF = <<<'DIFF'
        $a = 'Hello' . ' World';  // [tl! remove]
        $a = 'Hello';  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Concat::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Concat $node */

        return $node->left;
    }
}
