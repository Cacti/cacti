<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Arithmetic;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;

class PreDecrementToPreIncrement extends AbstractMutator
{
    public const SET = 'Arithmetic';

    public const DESCRIPTION = 'Replaces `--` with `++`.';

    public const DIFF = <<<'DIFF'
        $b = --$a;  // [tl! remove]
        $b = ++$a;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [PreDec::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var PreDec $node */
        return new PreInc($node->var, $node->getAttributes());
    }
}
