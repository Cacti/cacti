<?php

declare(strict_types=1);

namespace Pest\Mutate;

use Pest\Exceptions\ShouldNotHappen;
use Pest\Mutate\Support\PhpParserFactory;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\PrettyPrinter\Standard;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Finder\SplFileInfo;

class Mutation
{
    private const TMP_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp'
        .DIRECTORY_SEPARATOR
        .'mutations';

    private const DIFF_SEPARATOR = '--- Expected'.PHP_EOL.'+++ Actual'.PHP_EOL.'@@ @@'.PHP_EOL;

    public function __construct(
        public readonly SplFileInfo $file,
        public readonly string $id,
        public readonly string $mutator,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly string $diff,
        public readonly string $modifiedSourcePath,
    ) {}

    /**
     * @param  array<array-key, Node>  $modifiedAst
     */
    public static function create(
        SplFileInfo $file,
        string $mutator,
        Node $originalNode,
        ?Node $modifiedNode,
        array $modifiedAst,
    ): self {
        $modifiedSource = (new Standard)->prettyPrintFile($modifiedAst);
        $modifiedSourcePath = self::TMP_FOLDER.DIRECTORY_SEPARATOR.hash('xxh3', $modifiedSource);
        file_put_contents($modifiedSourcePath, $modifiedSource);

        $parser = PhpParserFactory::make();
        $orignalAst = $parser->parse($file->getContents());

        assert($orignalAst !== null);

        $newlyRenderedOriginalSource = (new Standard)->prettyPrintFile($orignalAst);

        $endLine = $originalNode->getEndLine();

        if (
            $originalNode->getAttribute('parent') instanceof Param &&
            $originalNode->getAttribute('parent')->getAttribute('parent') instanceof ClassMethod
        ) {
            // use the end line of the method instead if a parameter is mutated, otherwise it is not considered as covered
            $endLine = $originalNode->getAttribute('parent')->getAttribute('parent')->getEndLine();
        }

        $id = hash('xxh3', $file->getRealPath().$mutator.$modifiedSource);

        return new self(
            $file,
            $id,
            $mutator,
            $originalNode->getStartLine(),
            $endLine,
            self::diff($newlyRenderedOriginalSource, $modifiedSource),
            $modifiedSourcePath,
        );
    }

    public function modifiedSource(): string
    {
        $source = file_get_contents($this->modifiedSourcePath);

        if ($source === false) {
            throw ShouldNotHappen::fromMessage('Unable to read modified source file.');
        }

        return $source;
    }

    private static function diff(string $originalSource, string $modifiedSource): string
    {
        $diff = (new Differ(new UnifiedDiffOutputBuilder("\n--- Expected\n+++ Actual\n")))
            ->diff($originalSource, $modifiedSource);

        if (! str_contains($diff, self::DIFF_SEPARATOR)) {
            return '';
        }

        $tmp = '';
        $lines = explode(PHP_EOL, explode(self::DIFF_SEPARATOR, $diff)[1]);

        foreach ($lines as $line) {
            $tmp .= self::colorizeLine(OutputFormatter::escape($line), str_starts_with($line, '-') ? 'red' : (str_starts_with($line, '+') ? 'green' : 'gray')).PHP_EOL;
        }

        $diff = str_replace(explode(self::DIFF_SEPARATOR, $diff)[1], $tmp, $diff);

        return str_replace(self::DIFF_SEPARATOR, '', $diff);
    }

    private static function colorizeLine(string $line, string $color): string
    {
        return sprintf('  <fg=%s>%s</>', $color, $line);
    }

    /**
     * @return array{file: string, id: string, mutator: string, start_line: int, end_line: int, diff: string, modified_source_path: string}
     */
    public function __serialize(): array
    {
        return [
            'file' => $this->file->getRealPath(),
            'id' => $this->id,
            'mutator' => $this->mutator,
            'start_line' => $this->startLine,
            'end_line' => $this->endLine,
            'diff' => $this->diff,
            'modified_source_path' => $this->modifiedSourcePath,
        ];
    }

    /**
     * @param  array{file: string, id: string, mutator: string, start_line: int, end_line: int, diff: string, modified_source_path: string}  $data
     */
    public function __unserialize(array $data): void
    {
        $this->file = new SplFileInfo($data['file'], '', '');
        $this->id = $data['id'];
        $this->mutator = $data['mutator'];
        $this->startLine = $data['start_line'];
        $this->endLine = $data['end_line'];
        $this->diff = $data['diff'];
        $this->modifiedSourcePath = $data['modified_source_path'];
    }
}
