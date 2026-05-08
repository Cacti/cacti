<?php

declare(strict_types=1);

namespace Pest\Plugin\Commands;

use Composer\Command\BaseCommand;
use Pest\Plugin\Manager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class DumpCommand extends BaseCommand // @phpstan-ignore-line
{
    protected function configure(): void
    {
        $this->setName('pest:dump-plugins')
            ->setDescription('Dump all installed Pest plugins to the plugin cache.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->getComposer();

        if ($composer === null) {
            throw new \RuntimeException('Could not get Composer\Composer instance.');
        }

        $vendorDirectory = $composer->getConfig()->get('vendor-dir');
        $plugins = [];

        $packages = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();

        $packages[] = $composer->getPackage();

        /** @var \Composer\Package\PackageInterface $package */
        foreach ($packages as $package) {
            $extra = $package->getExtra();
            // @phpstan-ignore-next-line
            $plugins = array_merge($plugins, $extra['pest']['plugins'] ?? []);
        }

        file_put_contents(
            implode(DIRECTORY_SEPARATOR, [$vendorDirectory, Manager::PLUGIN_CACHE_FILE]),
            json_encode($plugins, JSON_PRETTY_PRINT)
        );

        return 0;
    }
}
