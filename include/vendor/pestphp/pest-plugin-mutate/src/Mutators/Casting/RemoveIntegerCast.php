<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Casting;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\Cast\Int_;

class RemoveIntegerCast extends AbstractMutator
{
    public const SET = 'Casting';

    public const DESCRIPTION = 'Removes integer cast.';

    public const DIFF = <<<'DIFF'
        $a = (int) $b;  // [tl! remove]
        $a = $b;        // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Int_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Int_ $node */
        return $node->expr;
    }
}
