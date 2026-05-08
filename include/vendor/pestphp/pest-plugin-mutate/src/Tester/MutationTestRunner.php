<?php

declare(strict_types=1);

namespace Pest\Mutate\Tester;

use Composer\InstalledVersions;
use Pest\Mutate\Contracts\MutationTestRunner as MutationTestRunnerContract;
use Pest\Mutate\Contracts\Printer;
use Pest\Mutate\Event\Facade;
use Pest\Mutate\MutationSuite;
use Pest\Mutate\MutationTest;
use Pest\Mutate\Plugins\Mutate;
use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\Mutate\Repositories\TelemetryRepository;
use Pest\Mutate\Support\Configuration\Configuration;
use Pest\Mutate\Support\FileFinder;
use Pest\Mutate\Support\MutationGenerator;
use Pest\Support\Container;
use Pest\Support\Coverage;
use Psr\SimpleCache\CacheInterface;
use SebastianBergmann\CodeCoverage\CodeCoverage;

class MutationTestRunner implements MutationTestRunnerContract
{
    private bool $enabled = false;

    private bool $codeCoverageRequested = false;

    private bool $stop = false;

    /**
     * @var array<int, string>
     */
    private array $originalArguments;

    /**
     * @var array<int, ?MutationTest>
     */
    private array $runningTests;

    private float $startTime;

    public static function fake(): MutationTestRunnerFake
    {
        $fake = new MutationTestRunnerFake;

        Container::getInstance()->add(MutationTestRunnerContract::class, $fake);

        return $fake;
    }

    public function stopExecution(): void
    {
        $this->stop = true;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public function setOriginalArguments(array $arguments): void
    {
        $this->originalArguments = $arguments;
    }

    public function enable(): void
    {
        if (getenv(Mutate::ENV_MUTATION_TESTING) !== false) {
            return;
        }

        $this->enabled = true;
    }

    public function isEnabled(): bool
    {
        if (str_starts_with((string) $this->enabled, ConfigurationRepository::FAKE)) {
            return false;
        }

        return $this->enabled;
    }

    public function doNotDisableCodeCoverage(): void
    {
        $this->codeCoverageRequested = true;
    }

    public function isCodeCoverageRequested(): bool
    {
        return $this->codeCoverageRequested;
    }

    public function run(): int
    {
        Container::getInstance()->get(TelemetryRepository::class)->initialTestSuiteDuration( // @phpstan-ignore-line
            microtime(true) - $this->startTime
        );

        if (! Coverage::isAvailable() || ! file_exists($reportPath = Coverage::getPath())) {
            Container::getInstance()->get(Printer::class)->reportError('No coverage report found, aborting mutation testing.'); // @phpstan-ignore-line

            return 1;
        }

        $this->clearCacheIfPluginVersionChanged();

        $mutationSuite = MutationSuite::instance();

        Facade::instance()->emitter()->startMutationGeneration($mutationSuite);

        /** @var CodeCoverage $codeCoverage */
        $codeCoverage = require $reportPath;

        unlink($reportPath);
        $coveredLines = array_map(fn (array $lines): array => array_filter($lines, fn (?array $tests): bool => $tests !== [] && $tests !== null), $codeCoverage->getData()->lineCoverage());
        $coveredLines = array_filter($coveredLines, fn (array $lines): bool => $lines !== []);

        $files = FileFinder::files($this->getConfiguration()->paths, $this->getConfiguration()->pathsToIgnore);

        /** @var MutationGenerator $generator */
        $generator = Container::getInstance()->get(MutationGenerator::class);

        foreach ($files as $file) {
            $linesToMutate = [];

            if ($this->getConfiguration()->coveredOnly) {

                if (! isset($coveredLines[$file->getRealPath()])) {
                    continue;
                }

                $linesToMutate = array_keys($coveredLines[$file->getRealPath()]);
            }

            foreach ($generator->generate(
                file: $file,
                mutators: $this->getConfiguration()->mutators,
                linesToMutate: $linesToMutate,
                classesToMutate: $this->getConfiguration()->everything ? [] : $this->getConfiguration()->classes,
            ) as $mutation) {
                if ($this->getConfiguration()->mutationId !== null && $mutation->id !== $this->getConfiguration()->mutationId) {
                    continue;
                }

                $mutationSuite->repository->add($mutation);
            }
        }

        Facade::instance()->emitter()->finishMutationGeneration($mutationSuite);

        if ($this->getConfiguration()->retry) {
            $mutationSuite->repository->sortByEscapedFirst();
        }

        Facade::instance()->emitter()->startMutationSuite($mutationSuite);

        if ($this->getConfiguration()->parallel) {
            $this->runTestsInParallel(
                mutationSuite: $mutationSuite,
                coveredLines: $coveredLines,
                processes: $this->getConfiguration()->processes,
            );
        } else {
            $this->runTests(
                mutationSuite: $mutationSuite,
                coveredLines: $coveredLines,
            );
        }

        $mutationSuite->repository->saveResults();

        Facade::instance()->emitter()->finishMutationSuite($mutationSuite);

        return $this->isMinScoreIsReached($mutationSuite) ? 0 : 1;
    }

    private function getConfiguration(): Configuration
    {
        return Container::getInstance()->get(ConfigurationRepository::class)->mergedConfiguration(); // @phpstan-ignore-line
    }

    private function isMinScoreIsReached(MutationSuite $mutationSuite): bool
    {
        /** @var Configuration $configuration */
        $configuration = Container::getInstance()->get(ConfigurationRepository::class) // @phpstan-ignore-line
            ->mergedConfiguration();

        $minScore = $configuration->minScore;

        if ($minScore === null) {
            return true;
        }

        if ($mutationSuite->repository->count() === 0 && $configuration->ignoreMinScoreOnZeroMutations) {
            return true;
        }

        $score = $mutationSuite->score();
        if ($score >= $minScore) {
            return true;
        }

        Container::getInstance()->get(Printer::class) // @phpstan-ignore-line
            ->reportScoreNotReached($score, $minScore);

        return false;
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $coveredLines
     */
    private function runTestsInParallel(MutationSuite $mutationSuite, array $coveredLines, int $processes): void
    {
        $tests = [];
        foreach ($mutationSuite->repository->all() as $testCollection) {
            foreach ($testCollection->tests() as $test) {
                $tests[] = $test;
            }
        }

        $this->runningTests = array_fill(1, $processes, null);

        foreach ($tests as $test) {
            if ($this->stop) {
                break;
            }

            while (count(array_filter($this->runningTests, fn (?MutationTest $process): bool => $process instanceof MutationTest)) >= $processes) {
                if ($this->checkRunningTestsHaveFinished()) {
                    continue;
                }

                usleep(1000); // @pest-arch-ignore-line
            }

            $processId = (int) array_key_first(array_filter($this->runningTests, fn (?MutationTest $process): bool => ! $process instanceof MutationTest));
            if ($test->start($coveredLines, $this->getConfiguration(), $this->originalArguments, $processId)) {
                $this->runningTests[$processId] = $test;
            }
        }

        while (! $this->stop && array_filter($this->runningTests, fn (?MutationTest $process): bool => $process instanceof MutationTest) !== []) {
            $this->checkRunningTestsHaveFinished();
        }
    }

    /**
     * @param  array<string, array<int, array<int, string>>>  $coveredLines
     */
    private function runTests(MutationSuite $mutationSuite, array $coveredLines): void
    {
        foreach ($mutationSuite->repository->all() as $testCollection) {
            Facade::instance()->emitter()->startTestCollection($testCollection);

            foreach ($testCollection->tests() as $test) {
                if ($this->stop) {
                    break 2;
                }

                if ($test->start($coveredLines, $this->getConfiguration(), $this->originalArguments)) {
                    while (! $test->hasFinished()) {
                        usleep(1000);
                    }
                }
            }
        }
    }

    private function checkRunningTestsHaveFinished(): bool
    {
        foreach ($this->runningTests as $index => $runningTest) {
            if (! $runningTest instanceof MutationTest) {
                continue;
            }

            if ($runningTest->hasFinished()) {
                $this->runningTests[$index] = null;

                return true;
            }
        }

        return false;
    }

    private function clearCacheIfPluginVersionChanged(): void
    {
        $cache = Container::getInstance()->get(CacheInterface::class);

        $pluginVersion = InstalledVersions::getVersion('pestphp/pest-plugin-mutate');

        /** @var ?string $previousVersion */
        $previousVersion = $cache->get('mutation-plugin-version'); // @phpstan-ignore-line

        if ($previousVersion === null || $previousVersion !== $pluginVersion) {
            $cache->clear(); // @phpstan-ignore-line
        }

        $cache->set('mutation-plugin-version', $pluginVersion); // @phpstan-ignore-line
    }

    public function setStartTime(float $startTime): void
    {
        $this->startTime = $startTime;
    }
}
