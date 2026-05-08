<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Removal;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;

class RemoveEarlyReturn extends AbstractMutator
{
    public const SET = 'Removal';

    public const DESCRIPTION = 'Removes an early return statement';

    public const DIFF = <<<'DIFF'
        if ($a > $b) {
            return true // [tl! remove]
        }
        
        return false;
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Return_::class];
    }

    public static function can(Node $node): bool
    {
        if (! $node instanceof Return_) {
            return false;
        }

        $classMethod = $node;
        do {
            $classMethod = $classMethod->getAttribute('parent');

            if ($classMethod instanceof ClassMethod) {
                break;
            }
        } while ($classMethod !== null);

        if (! $classMethod instanceof ClassMethod) {
            return false;
        }

        return self::isEarlyReturn($classMethod, $node);
    }

    public static function mutate(Node $node): Node
    {
        return new Nop;
    }

    private static function isEarlyReturn(ClassMethod $classMethod, Return_ $node): bool
    {
        $stmts = self::extractStmts($classMethod);

        $found = false;

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt === $node) {
                $found = true;

                continue;
            }
            if (! $found) {
                continue;
            }
            if (! $stmt instanceof Return_) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return mixed[]
     */
    private static function extractStmts(Stmt $stmt): array
    {
        $stmts = [];

        if (! property_exists($stmt, 'stmts')) {
            return $stmts;
        }

        foreach ($stmt->stmts as $childStmt) {
            $stmts = [
                ...$stmts,
                $childStmt,
                ...self::extractStmts($childStmt),
            ];
        }

        return $stmts;
    }
}
