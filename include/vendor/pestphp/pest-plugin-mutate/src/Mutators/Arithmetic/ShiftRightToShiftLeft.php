<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\ShiftLeft;
use PhpParser\Node\Expr\BinaryOp\ShiftRight;

class ShiftRightToShiftLeft extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `>>` with `<<`.';

    public const DIFF = <<<'DIFF'
        $b = $a >> 1;  // [tl! remove]
        $b = $a << 1;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ShiftRight::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var ShiftRight $node */

        return new ShiftLeft($node->left, $node->right, $node->getAttributes());
    }
}
