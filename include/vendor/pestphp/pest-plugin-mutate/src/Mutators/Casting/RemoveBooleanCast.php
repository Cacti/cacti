<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Casting;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\Cast\Bool_;

class RemoveBooleanCast extends AbstractMutator
{
    public const SET = 'Casting';

    public const DESCRIPTION = 'Removes boolean cast.';

    public const DIFF = <<<'DIFF'
        $a = (bool) $b;  // [tl! remove]
        $a = $b;         // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Bool_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Bool_ $node */
        return $node->expr;
    }
}
