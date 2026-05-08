<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Services;

use phpDocumentor\Reflection\DocBlockFactory;
use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Architecture\Storage\Filesystem;
use Symfony\Component\Finder\Finder;
use PhpParser\Parser;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\NodeFinder;

/**
 * For redefined to make extension
 */
final class ServiceContainer
{
    public static Finder $finder;

    public static string $descriptionClass = ObjectDescription::class;

    public static DocBlockFactory $docBlockFactory;

    public static Parser $parser;

    public static NodeTraverser $nodeTraverser;

    public static NodeFinder $nodeFinder;

    public static bool $showException = false;

    /**
     * @param string[] $excludedPaths
     */
    public static function init(array $excludedPaths = []): void
    {
        self::$finder = Finder::create()
            ->files()
            ->followLinks()
            ->name('/\.php$/')
            ->in(Filesystem::getBaseDir());

        foreach ($excludedPaths as $path) {
            self::$finder->exclude($path);
        }

        self::$parser = (new ParserFactory())->createForNewestSupportedVersion();

        self::$nodeTraverser = new NodeTraverser();
        self::$nodeTraverser->addVisitor(new NameResolver());

        self::$nodeFinder = new NodeFinder();

        /** @phpstan-ignore-next-line */
        self::$docBlockFactory = DocBlockFactory::createInstance();
    }
}
