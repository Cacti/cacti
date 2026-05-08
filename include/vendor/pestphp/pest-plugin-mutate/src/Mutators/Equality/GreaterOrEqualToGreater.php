<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Equality;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;

class GreaterOrEqualToGreater extends AbstractMutator
{
    public const SET = 'Equality';

    public const DESCRIPTION = 'Converts the greater or equal operator to the greater operator.';

    public const DIFF = <<<'DIFF'
        if ($a >= $b) {  // [tl! remove]
        if ($a > $b) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [GreaterOrEqual::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var GreaterOrEqual $node */
        return new Greater($node->left, $node->right);
    }
}
