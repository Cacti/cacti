<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Logical;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Coalesce;

class CoalesceRemoveLeft extends AbstractMutator
{
    public const SET = 'Logical';

    public const DESCRIPTION = 'Removes the left side of the coalesce operator.';

    public const DIFF = <<<'DIFF'
        return $a ?? $b;  // [tl! remove]
        return $b;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Coalesce::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Coalesce $node */

        return $node->right;
    }
}
