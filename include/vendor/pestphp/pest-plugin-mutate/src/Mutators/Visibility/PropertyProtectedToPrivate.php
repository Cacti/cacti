<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Visibility;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;

class PropertyProtectedToPrivate extends AbstractMutator
{
    public const SET = 'Visibility';

    public const DESCRIPTION = 'Mutates a protected property to a private property';

    public const DIFF = <<<'DIFF'
        protected bool $foo = true;  // [tl! remove]
        private bool $foo = true;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Property::class, Param::class];
    }

    public static function can(Node $node): bool
    {
        if ($node instanceof Property && $node->isProtected()) {
            return true;
        }

        return $node instanceof Param &&
        $node->flags === Class_::MODIFIER_PROTECTED;
    }

    public static function mutate(Node $node): Node
    {
        /** @var Property $node */
        $node->flags = Class_::MODIFIER_PRIVATE;

        return $node;
    }
}
