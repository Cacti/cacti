<?php

declare(strict_types=1);

namespace Pest\Mutate\Support\Printers;

use Pest\Mutate\Contracts\Printer;
use Pest\Mutate\MutationSuite;
use Pest\Mutate\MutationTest;
use Pest\Mutate\MutationTestCollection;
use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\Mutate\Support\Configuration\Configuration;
use Pest\Mutate\Support\MutationTestResult;
use Pest\Support\Container;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\render;

class DefaultPrinter implements Printer
{
    private bool $compact = false;

    public function __construct(protected readonly OutputInterface $output) {}

    public function compact(): void
    {
        $this->compact = true;
    }

    public function reportTestedMutation(MutationTest $test): void
    {
        if ($this->compact) {
            $this->output->write('<fg=gray;options=bold>.</>');

            return;
        }

        $this->writeMutationTestLine('green', '✓', $test);
    }

    public function reportUntestedMutation(MutationTest $test): void
    {
        if ($this->compact) {
            $this->output->write('<fg=red;options=bold>x</>');

            return;
        }

        $this->writeMutationTestLine('red', '⨯', $test);
    }

    public function reportUncoveredMutation(MutationTest $test): void
    {
        if ($this->compact) {
            $this->output->write('<fg=yellow;options=bold>-</>');

            return;
        }

        $this->writeMutationTestLine('yellow', '-', $test);
    }

    public function reportTimedOutMutation(MutationTest $test): void
    {
        if ($this->compact) {
            $this->output->write('<fg=yellow;options=bold>t</>');

            return;
        }

        $this->writeMutationTestLine('yellow', 't', $test);
    }

    public function printFilename(MutationTestCollection $testCollection): void
    {
        if ($this->compact) {
            return;
        }

        $path = str_ireplace(getcwd().'/', '', $testCollection->file->getRealPath());

        $this->output->writeln('');
        $this->output->writeln('  <fg=default;bg=gray;options=bold> RUN </> '.$path);
    }

    public function reportError(string $message): void
    {
        $this->output->writeln([
            '',
            '  <fg=default;bg=red;options=bold> ERROR </> <fg=default>'.$message.'</>',
            '',
        ]);
    }

    public function reportScoreNotReached(float $scoreReached, float $scoreRequired): void
    {
        $this->output->writeln([
            '  <fg=white;bg=red;options=bold> FAIL </> <fg=gray>Mutation score below expected:</><fg=red;options=bold> '.number_format($scoreReached, 1).' %</><fg=gray>. Minimum: </><options=bold>'.number_format($scoreRequired, 1).' %</><fg=gray>.</>',
            '',
        ]);
    }

    public function reportMutationGenerationStarted(MutationSuite $mutationSuite): void
    {
        $this->output->writeln('  Mutating application files...');
    }

    public function reportMutationGenerationFinished(MutationSuite $mutationSuite): void
    {
        $this->output->writeln([
            '  <fg=gray>'.$mutationSuite->repository->total().' Mutations for '.$mutationSuite->repository->count().' Files created</>',
        ]);
    }

    public function reportMutationSuiteStarted(MutationSuite $mutationSuite): void
    {
        if ($this->compact) {
            $this->output->writeln('');
            $this->output->write('  ');  // ensure proper indentation before compact test output
        }
    }

    public function reportMutationSuiteFinished(MutationSuite $mutationSuite): void
    {
        /** @var Configuration $configuration */
        $configuration = Container::getInstance()->get(ConfigurationRepository::class)->mergedConfiguration(); // @phpstan-ignore-line

        if ($this->compact) {
            $this->output->writeln(''); // add new line after compact test output
        }

        $this->writeMutationSuiteSummary($mutationSuite);

        $this->output->writeln([
            '  <fg=gray>Mutations:</> <fg=default>'.($mutationSuite->repository->untested() !== 0 ? '<fg=red;options=bold>'.$mutationSuite->repository->untested().' untested</><fg=gray>,</> ' : '').($mutationSuite->repository->uncovered() !== 0 ? '<fg=yellow;options=bold>'.$mutationSuite->repository->uncovered().' uncovered</><fg=gray>,</> ' : '').($mutationSuite->repository->notRun() !== 0 ? '<fg=yellow;options=bold>'.$mutationSuite->repository->notRun().' pending</><fg=gray>,</> ' : '').($mutationSuite->repository->timedOut() !== 0 ? '<fg=green;options=bold>'.$mutationSuite->repository->timedOut().' timeout</><fg=gray>,</> ' : '').'<fg=green;options=bold>'.$mutationSuite->repository->tested().' tested</>',
        ]);

        $score = number_format($mutationSuite->score(), 2);
        $this->output->writeln('  <fg=gray>Score:</>     <fg='.($mutationSuite->minScoreReached() ? 'default' : 'red').'>'.$score.'%</>');

        $duration = number_format($mutationSuite->duration(), 2);
        $this->output->writeln('  <fg=gray>Duration:</>  <fg=default>'.$duration.'s</>');

        if ($configuration->parallel) {
            $processes = $configuration->processes;
            $this->output->writeln('  <fg=gray>Parallel:</>  <fg=default>'.$processes.' processes</>');
        }

        if ($mutationSuite->repository->count() === 0) {
            $this->output->writeln([
                '',
                '  <fg=white;options=bold;bg=blue> INFO </> No mutations created.',
                '',
            ]);

            return;
        }

        $this->output->writeln('');

        if ($configuration->profile) {
            $this->writeMutationTestProfile($mutationSuite);
        }
    }

    private function writeMutationTestLine(string $color, string $symbol, MutationTest $test): void
    {
        $this->output->writeln('  <fg='.$color.';options=bold>'.$symbol.'</> <fg=gray>Line '.$test->mutation->startLine.': '.$test->mutation->mutator::name().'</>'); // @pest-mutate-ignore
    }

    private function writeMutationSuiteSummary(MutationSuite $mutationSuite): void
    {
        foreach ($mutationSuite->repository->all() as $testCollection) {
            foreach ($testCollection->tests() as $test) {
                $this->writeMutationTestSummary($test);
            }
        }
    }

    private function writeMutationTestSummary(MutationTest $test): void
    {
        if (! in_array($test->result(), [MutationTestResult::Untested, MutationTestResult::Uncovered], true)) {
            return;
        }

        $path = str_ireplace(getcwd().'/', '', $test->mutation->file->getRealPath());

        render(<<<'HTML'
                        <div class="mx-2 flex">
                            <span class="flex-1 content-repeat-[-] text-red"></span>
                        </div>
                    HTML
        );

        if ($test->result() === MutationTestResult::Untested) {
            $color = 'red';
            $label = 'UNTESTED';
        } else {
            $color = 'bright-red';
            $label = 'UNCOVERED';
        }

        $this->output->writeln([
            '  <fg=default;bg='.$color.';options=bold> '.$label.' </> <fg=default;options=bold>'.$path.' <fg=gray> > Line '.$test->mutation->startLine.': '.$test->mutation->mutator::name().' - ID: '.$test->getId().'</>', // @pest-mutate-ignore
        ]);

        $diff = $test->mutation->diff;
        $this->output->writeln($diff);
    }

    private function writeMutationTestProfile(MutationSuite $mutationSuite): void
    {
        /** @var Configuration $configuration */
        $configuration = Container::getInstance()->get(ConfigurationRepository::class)->mergedConfiguration(); // @phpstan-ignore-line

        $this->output->writeln('  <fg=gray>Top 10 slowest mutation tests:</>');

        $timeElapsed = $mutationSuite->duration() * ($configuration->parallel ? $configuration->processes : 1);

        $slowTests = $mutationSuite->repository->slowest();

        foreach ($slowTests as $slowTest) {
            $path = str_ireplace(getcwd().'/', '', (string) $slowTest->mutation->file->getRealPath());

            $seconds = number_format($slowTest->duration(), 2, '.', '');

            $color = ($slowTest->duration()) > $timeElapsed * 0.25 ? 'red' : ($slowTest->duration() > $timeElapsed * 0.1 ? 'yellow' : 'gray');

            render(sprintf(<<<'HTML'
                <div class="flex justify-between space-x-1 mx-2">
                    <span class="flex-1">
                        <span class="font-bold">%s</span><span class="text-gray mx-1">></span><span class="text-gray">Line %s: %s - ID: %s</span>
                    </span>
                    <span class="ml-1 font-bold text-%s">
                        %ss
                    </span>
                </div>
            HTML, $path, $slowTest->mutation->startLine, $slowTest->mutation->mutator::name(), $slowTest->getId(), $color, $seconds));
        }

        $timeElapsedInSlowTests = array_sum(array_map(fn (MutationTest $testResult): float => $testResult->duration(), $slowTests));

        $timeElapsedAsString = number_format($timeElapsed, 2, '.', '');
        $percentageInSlowTestsAsString = number_format($timeElapsedInSlowTests * 100 / $timeElapsed, 2, '.', '');
        $timeElapsedInSlowTestsAsString = number_format($timeElapsedInSlowTests, 2, '.', '');

        render(sprintf(<<<'HTML'
            <div class="mx-2 mb-1 flex">
                <div class="text-gray">
                    <hr/>
                </div>
                <div class="flex space-x-1 justify-between">
                    <span>
                    </span>
                    <span>
                        <span class="text-gray">(%s%% of %ss)</span>
                        <span class="ml-1 font-bold">%ss</span>
                    </span>
                </div>
            </div>
        HTML, $percentageInSlowTestsAsString, $timeElapsedAsString, $timeElapsedInSlowTestsAsString));
    }
}
