<?php

declare(strict_types=1);

namespace Pest\Mutate\Support\Configuration;

use Pest\Mutate\Contracts\Configuration as ConfigurationContract;
use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Exceptions\InvalidMutatorException;

abstract class AbstractConfiguration implements ConfigurationContract
{
    /**
     * @var string[]|null
     */
    private ?array $paths = null;

    private ?bool $coveredOnly = null;

    /**
     * @var string[]|null
     */
    private ?array $pathsToIgnore = null;

    /**
     * @var class-string<Mutator>[]|null
     */
    private ?array $mutators = null;

    /**
     * @var class-string<Mutator>[]|null
     */
    private ?array $excludedMutators = null;

    /**
     * @var string[]|null
     */
    private ?array $classes = null;

    private ?float $minScore = null;

    private ?bool $ignoreMinScoreOnZeroMutations = null;

    private ?bool $parallel = null;

    private ?int $processes = null;

    private ?bool $profile = null;

    private ?bool $stopOnUntested = null;

    private ?bool $stopOnUncovered = null;

    private ?bool $retry = null;

    private ?string $mutationId = null;

    private ?bool $everything = null;

    /**
     * {@inheritDoc}
     */
    public function path(array|string ...$paths): self
    {
        $this->paths = array_merge(...array_map(fn (string|array $path): array => is_string($path) ? [$path] : $path, $paths));

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function ignore(array|string ...$paths): self
    {
        $this->pathsToIgnore = array_merge(...array_map(fn (string|array $path): array => is_string($path) ? [$path] : $path, $paths));

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function mutator(array|string ...$mutators): self
    {
        $this->mutators = $this->buildMutatorsList(...$mutators);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function except(array|string ...$mutators): self
    {
        $this->excludedMutators = $this->buildMutatorsList(...$mutators);

        return $this;
    }

    public function min(float $minScore, ?bool $failOnZeroMutations = null): self
    {
        $this->minScore = $minScore;

        if ($failOnZeroMutations !== null) {
            $this->ignoreMinScoreOnZeroMutations = $failOnZeroMutations;
        }

        return $this;
    }

    public function ignoreMinScoreOnZeroMutations(bool $ignore = true): self
    {
        $this->ignoreMinScoreOnZeroMutations = $ignore;

        return $this;
    }

    public function coveredOnly(bool $coveredOnly = true): self
    {
        $this->coveredOnly = $coveredOnly;

        return $this;
    }

    public function parallel(bool $parallel = true): self
    {
        $this->parallel = $parallel;

        return $this;
    }

    public function processes(?int $processes = null): self
    {
        $this->processes = $processes;

        return $this;
    }

    public function profile(bool $profile = true): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function stopOnUntested(bool $stopOnUntested = true): self
    {
        $this->stopOnUntested = $stopOnUntested;

        return $this;
    }

    public function stopOnUncovered(bool $stopOnUncovered = true): self
    {
        $this->stopOnUncovered = $stopOnUncovered;

        return $this;
    }

    public function bail(): self
    {
        $this->stopOnUntested = true;
        $this->stopOnUncovered = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function class(string|array ...$classes): self
    {
        $this->classes = array_unique([
            ...array_merge(...array_map(fn (string|array $class): array => is_string($class) ? [$class] : $class, $classes)),
            ...$this->classes ?? [],
        ]);

        return $this;
    }

    public function mutationId(string $id): self
    {
        $this->mutationId = $id;

        return $this;
    }

    public function everything(): self
    {
        $this->everything = true;

        return $this;
    }

    /**
     * @return array{covered_only?: bool, paths?: string[], paths_to_ignore?: string[], mutators?: class-string<Mutator>[], excluded_mutators?: class-string<Mutator>[], classes?: string[], parallel?: bool, processes?: int, profile?: bool, min_score?: float, ignore_min_score_on_zero_mutations?: bool, covered_only?: bool, stop_on_untested?: bool, stop_on_uncovered?: bool, mutation_id?: string, retry?: bool, everything?: bool}
     */
    public function toArray(): array
    {
        return array_filter([
            'covered_only' => $this->coveredOnly,
            'paths' => $this->paths,
            'paths_to_ignore' => $this->pathsToIgnore,
            'mutators' => $this->mutators !== null ? array_values(array_diff($this->mutators, $this->excludedMutators ?? [])) : null,
            'excluded_mutators' => $this->excludedMutators,
            'classes' => $this->classes,
            'parallel' => $this->parallel,
            'processes' => $this->processes,
            'profile' => $this->profile,
            'min_score' => $this->minScore,
            'ignore_min_score_on_zero_mutations' => $this->ignoreMinScoreOnZeroMutations,
            'stop_on_untested' => $this->stopOnUntested,
            'stop_on_uncovered' => $this->stopOnUncovered,
            'mutation_id' => $this->mutationId,
            'retry' => $this->retry,
            'everything' => $this->everything,
        ], fn (mixed $value): bool => ! is_null($value));
    }

    /**
     * @param  array<int, class-string<Mutator|MutatorSet>>|class-string<Mutator|MutatorSet>  ...$mutators
     * @return array<int, class-string<Mutator>>
     */
    private function buildMutatorsList(array|string ...$mutators): array
    {
        $mutators = array_map(fn (string|array $mutator): array => is_string($mutator) ? [$mutator] : $mutator, $mutators);

        $mutators = array_merge(...$mutators);

        $mutators = array_map(
            function (string $mutator): string {
                $constant = strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $mutator));

                return (string) (defined('Pest\\Mutate\\Mutators::'.$constant) ? constant('Pest\\Mutate\\Mutators::'.$constant) : $mutator); // @phpstan-ignore-line
            },
            $mutators
        );

        $mutators = array_merge(...array_map(
            fn (string $mutator): array => is_a($mutator, MutatorSet::class, true) ? $mutator::mutators() : [$mutator],
            $mutators
        ));

        foreach ($mutators as $mutator) {
            if (! is_a($mutator, Mutator::class, true)) {
                throw new InvalidMutatorException("{$mutator} is not a valid mutator");
            }
        }

        return $mutators; // @phpstan-ignore-line
    }

    public function retry(bool $retry = true): self
    {
        $this->retry = $retry;

        if ($retry) {
            $this->stopOnUntested = true;
        }

        return $this;
    }
}
