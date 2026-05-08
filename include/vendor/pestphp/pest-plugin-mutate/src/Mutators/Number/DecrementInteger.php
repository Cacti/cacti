<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Number;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\DeclareDeclare;

class DecrementInteger extends AbstractMutator
{
    public const SET = 'Number';

    public const DESCRIPTION = 'Decrements an integer number by 1.';

    public const DIFF = <<<'DIFF'
        $a = 1;  // [tl! remove]
        $a = 0;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [
            LNumber::class,
            Int_::class,
        ];
    }

    public static function can(Node $node): bool
    {
        if (! parent::can($node)) {
            return false;
        }

        /** @var LNumber $node */
        return $node->value < PHP_INT_MAX &&
            ! $node->getAttribute('parent') instanceof DeclareDeclare;
    }

    public static function mutate(Node $node): Node
    {
        /** @var LNumber|Int_ $node */
        $node->value -= $node->getAttribute('parent') instanceof UnaryMinus ? -1 : 1;

        return $node;
    }
}
