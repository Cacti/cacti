<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Logical;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;

class FalseToTrue extends AbstractMutator
{
    public const SET = 'Logical';

    public const DESCRIPTION = 'Converts `false` to `true`.';

    public const DIFF = <<<'DIFF'
        if (false) {  // [tl! remove]
        if (true) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ConstFetch::class];
    }

    public static function can(Node $node): bool
    {
        if (! parent::can($node)) {
            return false;
        }

        /** @var ConstFetch $node */
        return $node->name->toCodeString() === 'false';
    }

    public static function mutate(Node $node): Node
    {
        return new ConstFetch(new Name('true'));
    }
}
