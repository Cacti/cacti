<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract
{
    private int $nodeCount = 0; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    /**
     * @param  array<int, int>  $linesToMutate
     * @param  array<int, array<int, string>>  $mutatorsToIgnoreByLine
     * @param  callable  $hasAlreadyMutated
     * @param  callable  $trackMutation
     */
    public function __construct(
        private readonly string $mutator,
        private readonly int $offset,
        private readonly array $linesToMutate,
        private readonly array $mutatorsToIgnoreByLine,
        private $hasAlreadyMutated, // @pest-ignore-type
        private $trackMutation, // @pest-ignore-type
    ) {}

    public function enterNode(Node $node): Node|int|null
    {
        if ($node->getAttribute('comments') !== null) {
            foreach ($node->getAttribute('comments') as $comment) { // @phpstan-ignore-line
                preg_match('/@pest-mutate-ignore(.*)/', (string) $comment->getText(), $matches); // @phpstan-ignore-line
                if ($matches !== []) {
                    if ($matches[1] === '') {
                        return NodeTraverser::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
                    }

                    if (in_array($this->mutator::name(), array_map(fn (string $mutatorToIgnore): string => trim($mutatorToIgnore, ' */:'), explode(',', $matches[1])), true)) {
                        return NodeTraverser::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
                    }
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): Node|int|null
    {
        if (($this->hasAlreadyMutated)()) {
            return NodeTraverser::STOP_TRAVERSAL;
        }

        if ($this->nodeCount++ < $this->offset) { // @pest-mutate-ignore: SmallerToSmallerOrEqual
            return null;
        }

        if ($this->linesToMutate !== [] && array_filter(range($node->getStartLine(), $node->getEndLine()), fn (int $line): bool => in_array($line, $this->linesToMutate, true)) === []) {
            return null;
        }

        if (isset($this->mutatorsToIgnoreByLine[$node->getStartLine()])) {
            foreach ($this->mutatorsToIgnoreByLine[$node->getStartLine()] as $ignore) {
                if ($ignore === 'all') {
                    return null;
                }
                if ($ignore === $this->mutator::name()) {
                    return null;
                }
            }
        }

        if ($this->mutator::can($node)) {
            $originalNode = clone $node;
            $mutatedNode = $this->mutator::mutate($node);

            ($this->trackMutation)(
                $this->nodeCount,
                $originalNode,
                $mutatedNode instanceof Node ? $mutatedNode : null,
            );

            return $mutatedNode;
        }

        return null;
    }
}
