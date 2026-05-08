<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\ControlStructures;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;

class BreakToContinue extends AbstractMutator
{
    public const SET = 'ControlStructures';

    public const DESCRIPTION = 'Replaces `break` with `continue`.';

    public const DIFF = <<<'DIFF'
        foreach ($items as $item) {
            if ($item === 'foo') {
                break;  // [tl! remove]
                continue;  // [tl! add]
            }
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Break_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Break_ $node */
        return new Continue_($node->num, $node->getAttributes());
    }
}
