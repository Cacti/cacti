<?php

declare(strict_types=1);

namespace Pest\Mutate\Contracts;

interface MutationTestRunner
{
    public function enable(): void;

    public function isEnabled(): bool;

    /**
     * @param  array<int, string>  $arguments
     */
    public function setOriginalArguments(array $arguments): void;

    public function setStartTime(float $startTime): void;

    public function isCodeCoverageRequested(): bool;

    public function run(): int;
}
