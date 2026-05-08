<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Div;
use PhpParser\Node\Expr\BinaryOp\Mul;

class DivisionToMultiplication extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `/` with `*`.';

    public const DIFF = <<<'DIFF'
        $c = $a / $b;  // [tl! remove]
        $c = $a * $b;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Div::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Div $node */
        return new Mul($node->left, $node->right);
    }
}
