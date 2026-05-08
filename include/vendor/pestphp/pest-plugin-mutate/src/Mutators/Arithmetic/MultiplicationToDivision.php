<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Div;
use PhpParser\Node\Expr\BinaryOp\Mul;

class MultiplicationToDivision extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `*` with `/`.';

    public const DIFF = <<<'DIFF'
        $c = $a * $b;  // [tl! remove]
        $c = $a / $b;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Mul::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Mul $node */
        return new Div($node->left, $node->right);
    }
}
