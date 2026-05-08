<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Equality;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\BinaryOp\SmallerOrEqual;

class SmallerOrEqualToSmaller extends AbstractMutator
{
    public const SET = 'Equality';

    public const DESCRIPTION = 'Converts the smaller or equal operator to the smaller operator.';

    public const DIFF = <<<'DIFF'
        if ($a <= $b) {  // [tl! remove]
        if ($a < $b) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [SmallerOrEqual::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var SmallerOrEqual $node */
        return new Smaller($node->left, $node->right);
    }
}
