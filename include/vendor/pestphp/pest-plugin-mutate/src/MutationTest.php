<?php

declare(strict_types=1);

namespace Pest\Mutate;

use ParaTest\Options;
use Pest\Mutate\Event\Facade;
use Pest\Mutate\Plugins\Mutate;
use Pest\Mutate\Repositories\TelemetryRepository;
use Pest\Mutate\Support\Configuration\Configuration;
use Pest\Mutate\Support\MutationTestResult;
use Pest\Support\Container;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class MutationTest
{
    private MutationTestResult $result = MutationTestResult::None;

    private ?float $start = null;

    private ?float $finish = null;

    private Process $process;

    public function __construct(public readonly Mutation $mutation) {}

    public function getId(): string
    {
        return $this->mutation->id;
    }

    public function result(): MutationTestResult
    {
        return $this->result;
    }

    public function updateResult(MutationTestResult $result): void
    {
        $this->result = $result;
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $coveredLines
     * @param  array<int, string>  $originalArguments
     */
    public function start(array $coveredLines, Configuration $configuration, array $originalArguments, ?int $processId = null): bool
    {
        // TODO: we should pass the tests to run in another way, maybe via cache, mutation or env variable
        $filters = [];
        foreach (range($this->mutation->startLine, $this->mutation->endLine) as $lineNumber) {
            foreach ($coveredLines[$this->mutation->file->getRealPath()][$lineNumber] ?? [] as $test) {
                preg_match('/\\\\([a-zA-Z0-9]*)::(__pest_evaluable_)?([^#]*)"?/', $test, $matches);
                if ($matches[2] === '__pest_evaluable_') { // @phpstan-ignore-line
                    $filters[] = $matches[1].'::(.*)'.str_replace(['__', '_'], ['.{1,2}', '.'], $matches[3]); // @phpstan-ignore-line
                } else {
                    $filters[] = $matches[1].'::(.*)'.$matches[3]; // @phpstan-ignore-line
                }
            }
        }
        $filters = array_unique($filters);

        if ($filters === []) {
            $this->updateResult(MutationTestResult::Uncovered);

            Facade::instance()->emitter()->mutationUncovered($this);

            return false;
        }

        $envs = [
            Mutate::ENV_MUTATION_TESTING => $this->mutation->file->getRealPath(),
            Mutate::ENV_MUTATION_FILE => $this->mutation->modifiedSourcePath,
        ];

        if ($processId !== null) {
            $envs['PARATEST'] = '1';
            $envs[Options::ENV_KEY_TOKEN] = $processId;
            $envs[Options::ENV_KEY_UNIQUE_TOKEN] = uniqid($processId.'_');
            $envs['LARAVEL_PARALLEL_TESTING'] = 1;
        }

        // TODO: filter arguments to remove unnecessary stuff (Teamcity, Coverage, etc.)
        $process = new Process(
            command: [
                ...$originalArguments,
                '--bail',
                '--filter="'.implode('|', $filters).'"',
            ],
            env: $envs,
            timeout: $this->calculateTimeout(),
        );

        $this->start = microtime(true);

        $process->start();

        $this->process = $process;

        return true;
    }

    private function calculateTimeout(): int
    {
        $initialTestSuiteDuration = Container::getInstance()->get(TelemetryRepository::class) // @phpstan-ignore-line
            ->getInitialTestSuiteDuration();

        return (int) ($initialTestSuiteDuration + max(5, $initialTestSuiteDuration * 0.2));
    }

    public function hasFinished(): bool
    {
        try {
            if ($this->process->isRunning()) {
                $this->process->checkTimeout();

                return false;
            }
        } catch (ProcessTimedOutException) {
            $this->updateResult(MutationTestResult::Timeout);

            Facade::instance()->emitter()->mutationTimedOut($this);

            $this->finish = microtime(true);

            return true;
        }

        if ($this->process->isSuccessful()) {
            $this->updateResult(MutationTestResult::Untested);

            Facade::instance()->emitter()->mutationEscaped($this);

            $this->finish = microtime(true);

            return true;
        }

        $this->updateResult(MutationTestResult::Tested);

        Facade::instance()->emitter()->mutationTested($this);

        $this->finish = microtime(true);

        return true;
    }

    public function duration(): float
    {
        if ($this->start === null) {
            return 0;
        }
        if ($this->finish === null) {
            return 0;
        }

        return $this->finish - $this->start;
    }

    /**
     * @param  array<string, string>  $results
     */
    public function lastRunResult(array $results): MutationTestResult
    {
        return MutationTestResult::from($results[$this->getId()] ?? 'none');
    }
}
