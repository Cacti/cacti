<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;

class PreIncrementToPreDecrement extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `++` with `--`.';

    public const DIFF = <<<'DIFF'
        $b = ++$a;  // [tl! remove]
        $b = --$a;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [PreInc::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var PreInc $node */
        return new PreDec($node->var, $node->getAttributes());
    }
}
