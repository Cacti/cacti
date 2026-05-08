<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\ControlStructures;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;

class ContinueToBreak extends AbstractMutator
{
    public const SET = 'ControlStructures';

    public const DESCRIPTION = 'Replaces `continue` with `break`.';

    public const DIFF = <<<'DIFF'
        foreach ($items as $item) {
            if ($item === 'foo') {
                continue;  // [tl! remove]
                break;  // [tl! add]
            }
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Continue_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Continue_ $node */
        return new Break_($node->num, $node->getAttributes());
    }
}
