<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\String;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;

class EmptyStringToNotEmpty extends AbstractMutator
{
    public const SET = 'String';

    public const DESCRIPTION = 'Changes an empty string to a non-empty string.';

    public const DIFF = <<<'DIFF'
        $a = '';  // [tl! remove]
        $a = 'PEST Mutator was here!';  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [String_::class];
    }

    public static function can(Node $node): bool
    {
        return $node instanceof String_ &&
            $node->value === '';
    }

    public static function mutate(Node $node): Node
    {
        /** @var String_ $node */
        $node->value = 'PEST Mutator was here!';

        return $node;
    }
}
