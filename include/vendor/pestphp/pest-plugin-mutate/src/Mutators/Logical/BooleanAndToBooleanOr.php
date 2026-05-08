<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Logical;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;

class BooleanAndToBooleanOr extends AbstractMutator
{
    public const SET = 'Logical';

    public const DESCRIPTION = 'Converts the boolean and operator to the boolean or operator.';

    public const DIFF = <<<'DIFF'
        if ($a && $b) {  // [tl! remove]
        if ($a || $b) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [BooleanAnd::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var BooleanAnd $node */
        return new BooleanOr($node->left, $node->right);
    }
}
