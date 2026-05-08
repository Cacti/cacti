<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Visibility;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Property;

class ConstantPublicToProtected extends AbstractMutator
{
    public const SET = 'Visibility';

    public const DESCRIPTION = 'Mutates a public constant to a protected constant';

    public const DIFF = <<<'DIFF'
        public const FOO = true;  // [tl! remove]
        protected const FOO = true;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ClassConst::class];
    }

    public static function can(Node $node): bool
    {
        return $node instanceof ClassConst && $node->isPublic();
    }

    public static function mutate(Node $node): Node
    {
        /** @var Property $node */
        $node->flags = Class_::MODIFIER_PROTECTED;

        return $node;
    }
}
