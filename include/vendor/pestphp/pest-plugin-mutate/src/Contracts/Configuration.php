<?php

declare(strict_types=1);

namespace Pest\Mutate\Contracts;

interface Configuration
{
    /**
     * @param  array<int, string>|string  ...$paths
     */
    public function path(array|string ...$paths): self;

    /**
     * @param  array<int, string>|string  ...$paths
     */
    public function ignore(array|string ...$paths): self;

    /**
     * @param  array<int, class-string<Mutator|MutatorSet>>|class-string<Mutator|MutatorSet>  ...$mutators
     */
    public function mutator(array|string ...$mutators): self;

    /**
     * @param  array<int, class-string<Mutator|MutatorSet>>|class-string<Mutator|MutatorSet>  ...$mutators
     */
    public function except(array|string ...$mutators): self;

    public function min(float $minScore, ?bool $failOnZeroMutations = null): self;

    public function ignoreMinScoreOnZeroMutations(bool $ignore = true): self;

    public function coveredOnly(bool $coveredOnly = true): self;

    public function parallel(bool $parallel = true): self;

    public function processes(?int $processes = null): self;

    public function profile(bool $profile = true): self;

    public function stopOnUntested(bool $stopOnUntested = true): self;

    public function stopOnUncovered(bool $stopOnUncovered = true): self;

    public function bail(): self;

    /**
     * @param  array<int, class-string>|class-string  ...$classes
     */
    public function class(array|string ...$classes): self;

    public function retry(bool $retry = true): self;
}
