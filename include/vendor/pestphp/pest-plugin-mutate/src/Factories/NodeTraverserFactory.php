<?php

declare(strict_types=1);

namespace Pest\Mutate\Factories;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

class NodeTraverserFactory
{
    public static function create(): NodeTraverser
    {
        $traverser = new NodeTraverser;

        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor(new ParentConnectingVisitor);

        return $traverser;
    }
}
