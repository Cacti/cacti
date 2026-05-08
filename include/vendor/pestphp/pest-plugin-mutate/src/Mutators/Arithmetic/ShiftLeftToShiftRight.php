<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\ShiftLeft;
use PhpParser\Node\Expr\BinaryOp\ShiftRight;

class ShiftLeftToShiftRight extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `<<` with `>>`.';

    public const DIFF = <<<'DIFF'
        $b = $a << 1;  // [tl! remove]
        $b = $a >> 1;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ShiftLeft::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var ShiftLeft $node */

        return new ShiftRight($node->left, $node->right, $node->getAttributes());
    }
}
