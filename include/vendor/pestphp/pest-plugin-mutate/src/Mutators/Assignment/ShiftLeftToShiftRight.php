<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Assignment;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\AssignOp\ShiftLeft;
use PhpParser\Node\Expr\AssignOp\ShiftRight;

class ShiftLeftToShiftRight extends AbstractMutator
{
    public const SET = 'Assignment';

    public const DESCRIPTION = 'Replaces `<<=` with `>>=`.';

    public const DIFF = <<<'DIFF'
        $a <<= $b;  // [tl! remove]
        $a >>= $b;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ShiftLeft::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var ShiftLeft $node */
        return new ShiftRight($node->var, $node->expr, $node->getAttributes());
    }
}
