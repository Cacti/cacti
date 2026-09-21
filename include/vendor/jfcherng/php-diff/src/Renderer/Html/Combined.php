<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Html;

use Jfcherng\Diff\Factory\LineRendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;
use Jfcherng\Diff\SequenceMatcher;
use Jfcherng\Diff\Utility\ReverseIterator;
use Jfcherng\Utility\MbString;

/**
 * Combined HTML diff generator.
 *
 * Note that this renderer always has no line number.
 */
final class Combined extends AbstractHtml
{
    /**
     * {@inheritdoc}
     */
    public const INFO = [
        'desc' => 'Combined',
        'type' => 'Html',
    ];

    /**
     * {@inheritdoc}
     */
    public const AUTO_FORMAT_CHANGES = false;

    protected function redererChanges(array $changes): string
    {
        if (empty($changes)) {
            return $this->getResultForIdenticals();
        }

        $wrapperClasses = [
            ...$this->options['wrapperClasses'],
            'diff', 'diff-html', 'diff-combined',
        ];

        return
            '<table class="' . implode(' ', $wrapperClasses) . '">' .
                $this->renderTableHeader() .
                $this->renderTableHunks($changes) .
            '</table>';
    }

    /**
     * Renderer the table header.
     */
    protected function renderTableHeader(): string
    {
        if (!$this->options['showHeader']) {
            return '';
        }

        return
            '<thead>' .
                '<tr>' .
                    '<th>' . $this->_('differences') . '</th>' .
                '</tr>' .
            '</thead>';
    }

    /**
     * Renderer the table separate block.
     */
    protected function renderTableSeparateBlock(): string
    {
        return
            '<tbody class="skipped">' .
                '<tr>' .
                    '<td></td>' .
                '</tr>' .
            '</tbody>';
    }

    /**
     * Renderer table hunks.
     *
     * @param array[][] $hunks each hunk has many blocks
     */
    protected function renderTableHunks(array $hunks): string
    {
        $ret = '';

        foreach ($hunks as $i => $hunk) {
            if ($i > 0 && $this->options['separateBlock']) {
                $ret .= $this->renderTableSeparateBlock();
            }

            foreach ($hunk as $block) {
                $ret .= $this->renderTableBlock($block);
            }
        }

        return $ret;
    }

    /**
     * Renderer the table block.
     *
     * @param array $block the block
     */
    protected function renderTableBlock(array $block): string
    {
        switch ($block['tag']) {
            case SequenceMatcher::OP_EQ:
                $content = $this->renderTableBlockEqual($block);
                break;
            case SequenceMatcher::OP_INS:
                $content = $this->renderTableBlockInsert($block);
                break;
            case SequenceMatcher::OP_DEL:
                $content = $this->renderTableBlockDelete($block);
                break;
            case SequenceMatcher::OP_REP:
                $content = $this->renderTableBlockReplace($block);
                break;
            default:
                $content = '';
        }

        return '<tbody class="change change-' . self::TAG_CLASS_MAP[$block['tag']] . '">' . $content . '</tbody>';
    }

    /**
     * Renderer the table block: equal.
     *
     * @param array $block the block
     */
    protected function renderTableBlockEqual(array $block): string
    {
        $block['new']['lines'] = $this->customFormatLines(
            $block['new']['lines'],
            SequenceMatcher::OP_EQ,
        );

        $ret = '';

        // note that although we are in a OP_EQ situation,
        // the old and the new may not be exactly the same
        // because of ignoreCase, ignoreWhitespace, etc
        foreach ($block['new']['lines'] as $newLine) {
            // we could only pick either the old or the new to show
            // here we pick the new one to let the user know what it is now
            $ret .= $this->renderTableRow('new', SequenceMatcher::OP_EQ, $newLine);
        }

        return $ret;
    }

    /**
     * Renderer the table block: insert.
     *
     * @param array $block the block
     */
    protected function renderTableBlockInsert(array $block): string
    {
        $block['new']['lines'] = $this->customFormatLines(
            $block['new']['lines'],
            SequenceMatcher::OP_INS,
        );

        $ret = '';

        foreach ($block['new']['lines'] as $newLine) {
            $ret .= $this->renderTableRow('new', SequenceMatcher::OP_INS, $newLine);
        }

        return $ret;
    }

    /**
     * Renderer the table block: delete.
     *
     * @param array $block the block
     */
    protected function renderTableBlockDelete(array $block): string
    {
        $block['old']['lines'] = $this->customFormatLines(
            $block['old']['lines'],
            SequenceMatcher::OP_DEL,
        );

        $ret = '';

        foreach ($block['old']['lines'] as $oldLine) {
            $ret .= $this->renderTableRow('old', SequenceMatcher::OP_DEL, $oldLine);
        }

        return $ret;
    }

    /**
     * Renderer the table block: replace.
     *
     * @param array $block the block
     */
    protected function renderTableBlockReplace(array $block): string
    {
        if ($this->options['detailLevel'] === 'none') {
            return
                $this->renderTableBlockDelete($block) .
                $this->renderTableBlockInsert($block);
        }

        $ret = '';

        $oldLines = $block['old']['lines'];
        $newLines = $block['new']['lines'];

        $oldLinesCount = \count($oldLines);
        $newLinesCount = \count($newLines);

        // if the line counts changes, we treat the old and the new as
        // "a line with \n in it" and then do one-line-to-one-line diff
        if ($oldLinesCount !== $newLinesCount) {
            [$oldLines, $newLines] = $this->markReplaceBlockDiff($oldLines, $newLines);
            $oldLinesCount = $newLinesCount = 1;
        }

        $oldLines = $this->customFormatLines($oldLines, SequenceMatcher::OP_DEL);
        $newLines = $this->customFormatLines($newLines, SequenceMatcher::OP_INS);

        // now $oldLines must has the same line counts with $newlines
        for ($no = 0; $no < $newLinesCount; ++$no) {
            $mergedLine = $this->mergeReplaceLines($oldLines[$no], $newLines[$no]);

            // not merge-able, we fall back to separated form
            if (!isset($mergedLine)) {
                $ret .=
                    $this->renderTableBlockDelete($block) .
                    $this->renderTableBlockInsert($block);

                break;
            }

            $ret .= $this->renderTableRow('rep', SequenceMatcher::OP_REP, $mergedLine);
        }

        return $ret;
    }

    /**
     * Renderer a content row of the output table.
     *
     * @param string $tdClass the <td> class
     * @param int    $op      the operation
     * @param string $line    the line
     */
    protected function renderTableRow(string $tdClass, int $op, string $line): string
    {
        return
            '<tr data-type="' . self::SYMBOL_MAP[$op] . '">' .
                '<td class="' . $tdClass . '">' . $line . '</td>' .
            '</tr>';
    }

    /**
     * Merge two "replace"-type lines into a single line.
     *
     * The implementation concept is that if we remove all closure parts from
     * the old and the new, the rest of them (cleaned line) should be the same.
     * And then, we add back those removed closure parts in a correct order.
     *
     * @param string $oldLine the old line
     * @param string $newLine the new line
     *
     * @return null|string string if merge-able, null otherwise
     */
    protected function mergeReplaceLines(string $oldLine, string $newLine): ?string
    {
        $delParts = $this->analyzeClosureParts(
            $oldLine,
            RendererConstant::HTML_CLOSURES_DEL,
            SequenceMatcher::OP_DEL,
        );
        $insParts = $this->analyzeClosureParts(
            $newLine,
            RendererConstant::HTML_CLOSURES_INS,
            SequenceMatcher::OP_INS,
        );

        // get the cleaned line by a non-regex way (should be faster)
        // i.e., the new line with all "<ins>...</ins>" parts removed
        $mergedLine = $newLine;
        foreach (ReverseIterator::fromArray($insParts) as $part) {
            $mergedLine = substr_replace(
                $mergedLine,
                '', // deletion
                $part['offset'],
                \strlen($part['content']),
            );
        }

        // note that $mergedLine is actually a clean line at this point
        if (!$this->isLinesMergeable($oldLine, $newLine, $mergedLine)) {
            return null;
        }

        // before building the $mergedParts, we do some adjustments
        $this->revisePartsForBoundaryNewlines($delParts, RendererConstant::HTML_CLOSURES_DEL);
        $this->revisePartsForBoundaryNewlines($insParts, RendererConstant::HTML_CLOSURES_INS);

        // create a sorted merged parts array
        $mergedParts = [...$delParts, ...$insParts];
        usort(
            $mergedParts,
            // first sort by "offsetClean", "order" then by "type"
            static fn (array $a, array $b): int => (
                $a['offsetClean'] <=> $b['offsetClean']
                    ?: $a['order'] <=> $b['order']
                    ?: ($a['type'] === SequenceMatcher::OP_DEL ? -1 : 1)
            ),
        );

        // insert merged parts into the cleaned line
        foreach (ReverseIterator::fromArray($mergedParts) as $part) {
            $mergedLine = substr_replace(
                $mergedLine,
                $part['content'],
                $part['offsetClean'],
                0, // insertion
            );
        }

        return str_replace("\n", '<br>', $mergedLine);
    }

    /**
     * Analyze and get the closure parts information of the line.
     *
     * Such as
     *     extract information for "<ins>part 1</ins>" and "<ins>part 2</ins>"
     *     from "Hello <ins>part 1</ins>SOME OTHER TEXT<ins>part 2</ins> World"
     *
     * @param string   $line     the line
     * @param string[] $closures the closures
     * @param int      $type     the type
     *
     * @return array[] the closure information
     */
    protected function analyzeClosureParts(string $line, array $closures, int $type): array
    {
        [$ld, $rd] = $closures;

        $ldLength = \strlen($ld);
        $rdLength = \strlen($rd);

        $parts = [];
        $partStart = $partEnd = 0;
        $partLengthSum = 0;

        // find the next left delimiter
        while (false !== ($partStart = strpos($line, $ld, $partEnd))) {
            // find the corresponding right delimiter
            if (false === ($partEnd = strpos($line, $rd, $partStart + $ldLength))) {
                break;
            }

            $partEnd += $rdLength;
            $partLength = $partEnd - $partStart;

            $parts[] = [
                'type' => $type,
                // the sorting order used when both "offsetClean" are the same
                'order' => 0,
                // the offset in the line
                'offset' => $partStart,
                // the offset in the cleaned line (i.e., the line with closure parts removed)
                'offsetClean' => $partStart - $partLengthSum,
                // the content of the part
                'content' => substr($line, $partStart, $partLength),
            ];

            $partLengthSum += $partLength;
        }

        return $parts;
    }

    /**
     * Mark differences between two "replace" blocks.
     *
     * Each of the returned block (lines) is always only one line.
     *
     * @param string[] $oldBlock The old block
     * @param string[] $newBlock The new block
     *
     * @return string[][] the value of [[$oldLine], [$newLine]]
     */
    protected function markReplaceBlockDiff(array $oldBlock, array $newBlock): array
    {
        static $mbOld, $mbNew, $lineRenderer;

        $mbOld ??= new MbString();
        $mbNew ??= new MbString();
        $lineRenderer ??= LineRendererFactory::make(
            $this->options['detailLevel'],
            [], /** @todo is it possible to get the differOptions here? */
            $this->options,
        );

        $mbOld->set(implode("\n", $oldBlock));
        $mbNew->set(implode("\n", $newBlock));

        $lineRenderer->render($mbOld, $mbNew);

        return [
            [$mbOld->get()], // one-line block for the old
            [$mbNew->get()], // one-line block for the new
        ];
    }

    /**
     * Determine whether the "replace"-type lines are merge-able or not.
     *
     * @param string $oldLine   the old line
     * @param string $newLine   the new line
     * @param string $cleanLine the clean line
     */
    protected function isLinesMergeable(string $oldLine, string $newLine, string $cleanLine): bool
    {
        $oldLine = str_replace(RendererConstant::HTML_CLOSURES_DEL, '', $oldLine);
        $newLine = str_replace(RendererConstant::HTML_CLOSURES_INS, '', $newLine);

        $sumLength = \strlen($oldLine) + \strlen($newLine);

        /** @var float the changed ratio, 0 <= value < 1 */
        $changedRatio = ($sumLength - (\strlen($cleanLine) << 1)) / ($sumLength + 1);

        return $changedRatio <= $this->options['mergeThreshold'];
    }

    /**
     * Extract boundary newlines from parts into new parts.
     *
     * @param array[]  $parts    the parts
     * @param string[] $closures the closures
     *
     * @see https://git.io/JvVXH
     */
    protected function revisePartsForBoundaryNewlines(array &$parts, array $closures): void
    {
        [$ld, $rd] = $closures;

        $ldRegex = preg_quote($ld, '/');
        $rdRegex = preg_quote($rd, '/');

        for ($i = \count($parts) - 1; $i >= 0; --$i) {
            $part = &$parts[$i];

            // deal with leading newlines
            $part['content'] = preg_replace_callback(
                "/(?P<closure>{$ldRegex})(?P<nl>[\r\n]++)/u",
                static function (array $matches) use (&$parts, $part, $ld, $rd): string {
                    // add a new part for the extracted newlines
                    $part['order'] = -1;
                    $part['content'] = "{$ld}{$matches['nl']}{$rd}";
                    $parts[] = $part;

                    return $matches['closure'];
                },
                $part['content'],
            );

            // deal with trailing newlines
            $part['content'] = preg_replace_callback(
                "/(?P<nl>[\r\n]++)(?P<closure>{$rdRegex})/u",
                static function (array $matches) use (&$parts, $part, $ld, $rd): string {
                    // add a new part for the extracted newlines
                    $part['order'] = 1;
                    $part['content'] = "{$ld}{$matches['nl']}{$rd}";
                    $parts[] = $part;

                    return $matches['closure'];
                },
                $part['content'],
            );
        }
    }

    /**
     * Make lines suitable for HTML output.
     *
     * @param string[] $lines the lines
     * @param int      $op    the operation
     */
    protected function customFormatLines(array $lines, int $op): array
    {
        if (!$this->changesAreRaw) {
            return $lines;
        }

        static $closureMap = [
            SequenceMatcher::OP_DEL => RendererConstant::HTML_CLOSURES_DEL,
            SequenceMatcher::OP_INS => RendererConstant::HTML_CLOSURES_INS,
        ];

        $lines = $this->formatLines($lines);

        $htmlClosures = $closureMap[$op] ?? null;

        foreach ($lines as &$line) {
            if ($htmlClosures) {
                $line = str_replace(RendererConstant::HTML_CLOSURES, $htmlClosures, $line);
            }
            // fixes https://github.com/jfcherng/php-diff/issues/34
            $line = str_replace("\r\n", "\n", $line);
        }

        return $lines;
    }
}
