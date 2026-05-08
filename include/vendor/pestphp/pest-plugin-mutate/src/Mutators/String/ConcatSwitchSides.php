<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;

class ConcatSwitchSides extends AbstractMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Switches the sides of a concat expression.';

    public const DIFF = <<<'DIFF'
        $a = 'Hello' . ' World';  // [tl! remove]
        $a = ' World' . 'Hello';  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Concat::class];
    }

    public static function can(Node $node): bool
    {
        if (! $node instanceof Concat) {
            return false;
        }

        if ($node->left->getType() !== $node->right->getType()) {
            return true;
        }

        if ($node->left instanceof ConstFetch && $node->right instanceof ConstFetch) {
            return $node->left->name->toString() !== $node->right->name->toString();
        }

        if ($node->left instanceof String_ && $node->right instanceof String_) {
            return $node->left->value !== $node->right->value;
        }
        if (! $node->left instanceof Variable) {
            return true;
        }
        if (! $node->right instanceof Variable) {
            return true;
        }

        return $node->left->name !== $node->right->name;
    }

    public static function mutate(Node $node): Node
    {
        /** @var Concat $node */
        $tmp = $node->left;
        $node->left = $node->right;
        $node->right = $tmp;

        return $node;
    }
}
