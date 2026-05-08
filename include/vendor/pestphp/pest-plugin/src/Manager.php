<?php

declare(strict_types=1);

namespace Pest\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Pest\Plugin\Commands\DumpCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @internal
 */
final class Manager implements Capable, EventSubscriberInterface, PluginInterface
{
    /**
     * Holds the pest plugins file.
     */
    public const PLUGIN_CACHE_FILE = 'pest-plugins.json';

    /**
     * @var Composer
     */
    private $composer;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        /** @var string $vendorDirectory */
        $vendorDirectory = $composer->getConfig()->get('vendor-dir');
        $pluginFile = sprintf('%s/%s', $vendorDirectory, self::PLUGIN_CACHE_FILE);

        if (file_exists($pluginFile)) {
            unlink($pluginFile);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => 'registerPlugins',
        ];
    }

    public function getCapabilities()
    {
        return [
            \Composer\Plugin\Capability\CommandProvider::class => PestCommandProvider::class,
        ];
    }

    public function registerPlugins(): void
    {
        $cmd = new DumpCommand;
        $cmd->setComposer($this->composer);
        $cmd->run(new ArrayInput([]), new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, true));
    }

    /** {@inheritdoc} */
    public function deactivate(Composer $composer, IOInterface $io): void {}
}
