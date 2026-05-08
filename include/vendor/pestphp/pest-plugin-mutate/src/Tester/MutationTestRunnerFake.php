<?php

declare(strict_types=1);

namespace Pest\Mutate\Tester;

use Pest\Mutate\Contracts\MutationTestRunner as MutationTestRunnerContract;

class MutationTestRunnerFake implements MutationTestRunnerContract
{
    public function run(): int
    {
        return 0;
    }

    public function enable(): void {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function isCodeCoverageRequested(): bool
    {
        return false;
    }

    public function setOriginalArguments(array $arguments): void
    {
        // TODO: Implement setOriginalArguments() method.
    }

    public function setStartTime(float $startTime): void
    {
        // TODO: Implement setStartTime() method.
    }
}
