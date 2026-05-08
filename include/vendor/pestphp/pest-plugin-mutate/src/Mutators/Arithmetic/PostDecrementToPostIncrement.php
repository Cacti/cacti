<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;

class PostDecrementToPostIncrement extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `--` with `++`.';

    public const DIFF = <<<'DIFF'
        $b = $a--;  // [tl! remove]
        $b = $a++;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [PostDec::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var PostDec $node */
        return new PostInc($node->var, $node->getAttributes());
    }
}
