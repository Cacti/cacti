<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Logical;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;

class BooleanOrToBooleanAnd extends AbstractMutator
{
    public const SET = 'Logical';

    public const DESCRIPTION = 'Converts the boolean or operator to the boolean and operator.';

    public const DIFF = <<<'DIFF'
        if ($a || $b) {  // [tl! remove]
        if ($a && $b) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [BooleanOr::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var BooleanOr $node */
        return new BooleanAnd($node->left, $node->right);
    }
}
