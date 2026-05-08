<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Removal;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;

class RemoveNullSafeOperator extends AbstractMutator
{
    public const SET = 'Removal';

    public const DESCRIPTION = 'Converts nullsafe method and property calls to regular calls.';

    public const DIFF = <<<'DIFF'
        $a?->b();  // [tl! remove]
        $a->b();  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [NullsafeMethodCall::class, NullsafePropertyFetch::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var NullsafeMethodCall|NullsafePropertyFetch $node */
        if ($node instanceof NullsafePropertyFetch) {
            return new PropertyFetch($node->var, $node->name, $node->getAttributes());
        }

        return new MethodCall($node->var, $node->name, $node->getRawArgs(), $node->getAttributes());
    }
}
