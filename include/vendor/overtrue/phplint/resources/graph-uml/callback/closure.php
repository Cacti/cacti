<?php

use Bartlett\GraphUml\Filter\NamespaceFilterInterface;
use Bartlett\GraphUml\Generator\GeneratorInterface;
use Graphp\Graph\Graph;

$callback = function (Generator $vertices, GeneratorInterface $generator, Graph $graph, array $options) {
    foreach ($vertices as $fqcn) {
        $this->createVertexClass($fqcn);

        $namespaceFilter = $options['namespace_filter'];
        if ($namespaceFilter instanceof NamespaceFilterInterface) {
            $cluster = $namespaceFilter->filter($fqcn);
            if (null !== $cluster) {
                // highlight this specific element
                $color = 'burlywood3';
            } else {
                $color = 'white';
            }
            $graph->setAttribute(
                $generator->getPrefix() . sprintf('cluster.%s.graph.bgcolor', $cluster),
                $color
            );
        }
    }
};
