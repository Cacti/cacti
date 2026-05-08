<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Equality;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;

class NotEqualToNotIdentical extends AbstractMutator
{
    public const SET = 'Equality';

    public const DESCRIPTION = 'Converts the not equal operator to the not identical operator.';

    public const DIFF = <<<'DIFF'
        if ($a != $b) {  // [tl! remove]
        if ($a !== $b) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [NotEqual::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var NotEqual $node */
        return new NotIdentical($node->left, $node->right, $node->getAttributes());
    }
}
