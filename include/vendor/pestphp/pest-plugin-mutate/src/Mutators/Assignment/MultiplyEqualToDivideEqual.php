<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Assignment;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\AssignOp\Div;
use PhpParser\Node\Expr\AssignOp\Mul;

class MultiplyEqualToDivideEqual extends AbstractMutator
{
    public const SET = 'Assignment';

    public const DESCRIPTION = 'Replaces `*=` with `/=`.';

    public const DIFF = <<<'DIFF'
        $a *= $b;  // [tl! remove]
        $a /= $b;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Mul::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Mul $node */
        return new Div($node->var, $node->expr, $node->getAttributes());
    }
}
