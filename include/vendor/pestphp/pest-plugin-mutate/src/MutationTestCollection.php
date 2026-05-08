<?php

declare(strict_types=1);

namespace Pest\Mutate;

use Pest\Mutate\Support\MutationTestResult;
use Pest\Mutate\Support\ResultCache;
use Symfony\Component\Finder\SplFileInfo;

class MutationTestCollection
{
    /**
     * @param  array<int, MutationTest>  $tests
     */
    public function __construct(
        public readonly SplFileInfo $file,
        private array $tests = [],
    ) {}

    public function add(MutationTest $test): void
    {
        $this->tests[] = $test;
    }

    /**
     * @return array<int, MutationTest>
     */
    public function tests(): array
    {
        return $this->tests;
    }

    public function count(): int
    {
        return count($this->tests);
    }

    public function untested(): int
    {
        return count(array_filter($this->tests, fn (MutationTest $test): bool => $test->result() === MutationTestResult::Untested));
    }

    public function tested(): int
    {
        return count(array_filter($this->tests, fn (MutationTest $test): bool => $test->result() === MutationTestResult::Tested));
    }

    public function timedOut(): int
    {
        return count(array_filter($this->tests, fn (MutationTest $test): bool => $test->result() === MutationTestResult::Timeout));
    }

    public function uncovered(): int
    {
        return count(array_filter($this->tests, fn (MutationTest $test): bool => $test->result() === MutationTestResult::Uncovered));
    }

    public function notRun(): int
    {
        return count(array_filter($this->tests, fn (MutationTest $test): bool => $test->result() === MutationTestResult::None));
    }

    public function hasLastRunEscapedMutation(): bool
    {
        return array_filter(ResultCache::instance()->get($this), fn (string $result): bool => $result === MutationTestResult::Untested->value) !== [];
    }

    /**
     * @return array<string, string>
     */
    public function results(): array
    {
        $results = [];

        foreach ($this->tests as $test) {
            if ($test->result() !== MutationTestResult::None) {
                $results[$test->getId()] = $test->result()->value;
            }
        }

        return $results;
    }

    public function sortByEscapedFirst(): void
    {
        $lastRunResults = ResultCache::instance()->get($this);

        usort($this->tests, fn (MutationTest $a, MutationTest $b): int => ($b->lastRunResult($lastRunResults) === MutationTestResult::Untested) <=> ($a->lastRunResult($lastRunResults) === MutationTestResult::Untested));
    }

    public function isComplete(): bool
    {
        return array_filter($this->tests, fn (MutationTest $test): bool => $test->result() === MutationTestResult::None) === [];
    }
}
