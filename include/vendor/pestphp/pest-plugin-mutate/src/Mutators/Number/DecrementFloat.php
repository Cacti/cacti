<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Number;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\LNumber;

class DecrementFloat extends AbstractMutator
{
    public const SET = 'Number';

    public const DESCRIPTION = 'Decrements a float number by 1.';

    public const DIFF = <<<'DIFF'
        $a = 1.2;  // [tl! remove]
        $a = 0.2;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [
            DNumber::class,
            Float_::class,
        ];
    }

    public static function mutate(Node $node): Node
    {
        /** @var LNumber|Float_ $node */
        $node->value--;

        return $node;
    }
}
