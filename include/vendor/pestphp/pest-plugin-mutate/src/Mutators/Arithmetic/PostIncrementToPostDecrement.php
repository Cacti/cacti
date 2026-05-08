<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;

class PostIncrementToPostDecrement extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `++` with `--`.';

    public const DIFF = <<<'DIFF'
        $b = $a++;  // [tl! remove]
        $b = $a--;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [PostInc::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var PostInc $node */
        return new PostDec($node->var, $node->getAttributes());
    }
}
