<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint;

use LogicException;
use Overtrue\PHPLint\Configuration\OptionDefinition;
use Overtrue\PHPLint\Configuration\Resolver;
use Overtrue\PHPLint\Event\AfterCheckingEvent;
use Overtrue\PHPLint\Event\AfterLintFileEvent;
use Overtrue\PHPLint\Event\BeforeCheckingEvent;
use Overtrue\PHPLint\Event\BeforeLintFileEvent;
use Overtrue\PHPLint\Helper\ProcessHelper;
use Overtrue\PHPLint\Output\LinterOutput;
use Overtrue\PHPLint\Process\LintProcess;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

use function array_chunk;
use function array_push;
use function count;
use function md5_file;
use function microtime;
use function phpversion;
use function version_compare;

/**
 * @author Overtrue
 * @author Laurent Laville (code-rewrites since v9.0)
 */
final class Linter
{
    private Resolver $configResolver;
    private EventDispatcherInterface $dispatcher;
    private Cache $cache;
    private ?HelperSet $helperSet;
    private ?OutputInterface $output;
    private array $results;
    private int $processLimit;
    private string $memoryLimit;
    private bool $warning;

    public function __construct(
        Resolver $configResolver,
        EventDispatcherInterface $dispatcher,
        private readonly ?Client $client = null,
        ?HelperSet $helperSet = null,
        ?OutputInterface $output = null,
    ) {
        $this->configResolver = $configResolver;
        $this->dispatcher = $dispatcher;
        $this->processLimit = $configResolver->getOption(OptionDefinition::JOBS);
        $this->memoryLimit = (string) $configResolver->getOption(OptionDefinition::OPTION_MEMORY_LIMIT);
        $this->warning = $configResolver->getOption(OptionDefinition::WARNING);

        if ($configResolver->getOption(OptionDefinition::NO_CACHE)) {
            $defaultLifetime = OptionDefinition::DEFAULT_CACHE_TTL;
            $adapter = new NullAdapter();
        } else {
            $defaultLifetime = $configResolver->getOption(OptionDefinition::CACHE_TTL);
            $adapter = new FilesystemAdapter('paths', $defaultLifetime, $configResolver->getOption(OptionDefinition::CACHE_DIR));
        }
        $this->cache = new Cache($adapter);

        if ($defaultLifetime < OptionDefinition::DEFAULT_CACHE_TTL) {
            $this->cache->clear();
        }

        $this->results = [
            'errors' => [],
            'warnings' => [],
            'hits' => [],
            'misses' => [],
        ];

        $this->helperSet = $helperSet;
        $this->output = $output;
    }

    /**
     * @throws Throwable
     */
    public function lintFiles(Finder $finder, ?float $startTime = null): LinterOutput
    {
        if (null === $startTime) {
            $startTime = microtime(true);
        }

        try {
            $fileCount = count($finder);
        } catch (LogicException) {
            $fileCount = 0;
        }

        $this->dispatcher->dispatch(new BeforeCheckingEvent($this, ['fileCount' => $fileCount]));

        $processCount = 0;
        if ($fileCount > 0) {
            $results = $this->doLint($finder, $processCount);
        } else {
            $results = [];
        }

        if (null !== $this->client) {
            $default = [
                'application_version' => [
                    'long' => $this->client->getApplication()->getLongVersion(),
                    'short' => $this->client->getApplication()->getVersion(),
                ]
            ];
        }
        $finalResults = new LinterOutput($results, $finder);
        $finalResults->setContext($this->configResolver, $startTime, $processCount, $default ?? []);

        $this->cache->prune();

        $this->dispatcher->dispatch(new AfterCheckingEvent($this, ['results' => $finalResults]));

        return $finalResults;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function doLint(Finder $finder, int &$processCount): array
    {
        $iterator = $finder->getIterator();

        while ($iterator->valid()) {
            $fileInfo = $iterator->current();

            if ($this->cache->isHit($fileInfo->getRealPath())) {
                $this->results['hits'][] = $fileInfo;
            } else {
                $this->results['misses'][] = $fileInfo;
            }

            $iterator->next();
        }
        unset($iterator);

        if (version_compare(phpversion(), '8.3', 'ge')) {
            $chunkSize = $this->processLimit;
        } else {
            $chunkSize = 1;
        }
        $chunks = array_chunk($this->results['misses'], $chunkSize);
        $processRunning = [];

        /** @var ?ProcessHelper $helper */
        $helper = $this->helperSet?->has('process') ? $this->helperSet?->get('process') : null;  // @phpstan-ignore-line

        foreach ($chunks as $loop => $files) {
            $lintProcess = $this->createLintProcess($files)
                ->setHelper($helper)
                ->setOutput($this->output)
            ;
            $lintProcess->begin();

            // enqueue lint process as much as authorized by --jobs option (number of paralleled jobs to run)
            ++$processCount;
            $processRunning[$processCount] = $lintProcess;

            while (count($processRunning) >= $this->processLimit || (!empty($processRunning) && $loop == count($chunks) - 1)) {
                $this->checkProcessRunning($processRunning);
            }
        }

        return $this->results;
    }

    /**
     * @param array<int, LintProcess> $processRunning
     * @throws InvalidArgumentException
     */
    private function checkProcessRunning(array &$processRunning): void
    {
        foreach ($processRunning as $pid => $lintProcess) {
            if (!$lintProcess->isFinished()) {
                // php lint process is still running in background, wait until it's finished
                continue;
            }
            unset($processRunning[$pid]);

            // checks status of all files linked at end of the php lint process
            foreach ($lintProcess->getFiles() as $fileInfo) {
                $this->dispatcher->dispatch(new BeforeLintFileEvent($this, ['file' => $fileInfo]));

                $status = $this->processFile($fileInfo, $lintProcess);

                $this->dispatcher->dispatch(
                    new AfterLintFileEvent($this, ['file' => $fileInfo, 'status' => $status])
                );
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function processFile(SplFileInfo $fileInfo, LintProcess $lintProcess): string
    {
        $filename = $fileInfo->getRealPath();

        $item = $lintProcess->getItem($fileInfo);

        if ($item->hasSyntaxError()) {
            $status = 'error';
        } elseif ($this->warning && $item->hasSyntaxWarning()) {
            $status = 'warning';
        } else {
            $status = 'ok';

            $item = $this->cache->getItem($filename);
            $item->set(md5_file($filename));
            $this->cache->saveItem($item);
        }

        if ($status !== 'ok') {
            $this->results[$status . 's'][$filename] = [
                'absolute_file' => $filename,
                'relative_file' => $item->getFileInfo()->getRelativePathname(),
                'error' => $item->getMessage(),
                'line' => $item->getLine(),
            ];
        }

        return $status;
    }

    private function createLintProcess(array $files): LintProcess
    {
        $command = [
            PHP_SAPI == 'cli' ? PHP_BINARY : PHP_BINDIR . '/php',
            '-d error_reporting=E_ALL',
            '-d display_errors=On',
        ];

        if (!empty($this->memoryLimit)) {
            $command[] = '-d memory_limit=' . $this->memoryLimit;
        }

        $command[] = '-l';
        $command[] = '-n';
        array_push($command, ...$files);

        return (new LintProcess($command))->setFiles($files);
    }
}
