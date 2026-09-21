<?php

declare(strict_types=1);

namespace Jfcherng\Diff;

use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;

final class DiffHelper
{
    /**
     * The constructor.
     */
    private function __construct()
    {
    }

    /**
     * Get the absolute path of the project root directory.
     */
    public static function getProjectDirectory(): string
    {
        static $path;

        return $path ??= realpath(__DIR__ . '/..');
    }

    /**
     * Get the information about available renderers.
     */
    public static function getRenderersInfo(): array
    {
        static $info;

        if (isset($info)) {
            return $info;
        }

        $glob = implode(\DIRECTORY_SEPARATOR, [
            self::getProjectDirectory(),
            'src',
            'Renderer',
            '{' . implode(',', RendererConstant::RENDERER_TYPES) . '}',
            '*.php',
        ]);

        $fileNames = array_map(
            // get basename without file extension
            static fn (string $file): string => pathinfo($file, \PATHINFO_FILENAME),
            // paths of all Renderer files
            glob($glob, \GLOB_BRACE),
        );

        $renderers = array_filter(
            $fileNames,
            // only normal class files are wanted
            static fn (string $fileName): bool => (
                substr($fileName, 0, 8) !== 'Abstract'
                && substr($fileName, -9) !== 'Interface'
                && substr($fileName, -5) !== 'Trait'
            ),
        );

        $info = [];
        foreach ($renderers as $renderer) {
            $info[$renderer] = RendererFactory::resolveRenderer($renderer)::INFO;
        }

        return $info;
    }

    /**
     * Get the available renderers.
     *
     * @return string[] the available renderers
     */
    public static function getAvailableRenderers(): array
    {
        return array_keys(self::getRenderersInfo());
    }

    /**
     * Get the content of the CSS style sheet for HTML renderers.
     *
     * @throws \LogicException   path is a directory
     * @throws \RuntimeException path cannot be opened
     */
    public static function getStyleSheet(): string
    {
        static $fileContent;

        if (isset($fileContent)) {
            return $fileContent;
        }

        $filePath = self::getProjectDirectory() . '/example/diff-table.css';

        $file = new \SplFileObject($filePath, 'r');

        return $fileContent = $file->fread($file->getSize());
    }

    /**
     * Gets the diff statistics such as inserted and deleted etc...
     *
     * @return array<string,float> the statistics
     */
    public static function getStatistics(): array
    {
        return Differ::getInstance()->getStatistics();
    }

    /**
     * All-in-one static method to calculate the diff between two strings (or arrays of strings).
     *
     * @param string|string[] $old             the old string (or array of lines)
     * @param string|string[] $new             the new string (or array of lines)
     * @param string          $renderer        the renderer name
     * @param array           $differOptions   the options for Differ object
     * @param array           $rendererOptions the options for renderer object
     *
     * @return string the rendered differences
     */
    public static function calculate(
        $old,
        $new,
        string $renderer = 'Unified',
        array $differOptions = [],
        array $rendererOptions = []
    ): string {
        // always convert into array form
        \is_string($old) && ($old = explode("\n", $old));
        \is_string($new) && ($new = explode("\n", $new));

        return RendererFactory::getInstance($renderer)
            ->setOptions($rendererOptions)
            ->render(
                Differ::getInstance()
                    ->setOldNew($old, $new)
                    ->setOptions($differOptions),
            )
        ;
    }

    /**
     * All-in-one static method to calculate the diff between two files.
     *
     * @param string $old             the path of the old file
     * @param string $new             the path of the new file
     * @param string $renderer        the renderer name
     * @param array  $differOptions   the options for Differ object
     * @param array  $rendererOptions the options for renderer object
     *
     * @throws \LogicException   path is a directory
     * @throws \RuntimeException path cannot be opened
     *
     * @return string the rendered differences
     */
    public static function calculateFiles(
        string $old,
        string $new,
        string $renderer = 'Unified',
        array $differOptions = [],
        array $rendererOptions = []
    ): string {
        // we want to leave the line-ending problem to static::calculate()
        // so do not set SplFileObject::DROP_NEW_LINE flag
        // otherwise, we will lose \r if the line-ending is \r\n
        $oldFile = new \SplFileObject($old, 'r');
        $newFile = new \SplFileObject($new, 'r');

        return self::calculate(
            // fread() requires the length > 0 hence we plus 1 for empty files
            $oldFile->fread($oldFile->getSize() + 1),
            $newFile->fread($newFile->getSize() + 1),
            $renderer,
            $differOptions,
            $rendererOptions,
        );
    }
}
