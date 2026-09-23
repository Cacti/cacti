<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Html;

use Jfcherng\Diff\SequenceMatcher;

/**
 * Side by Side HTML diff generator.
 */
final class SideBySide extends AbstractHtml
{
    /**
     * {@inheritdoc}
     */
    public const INFO = [
        'desc' => 'Side by side',
        'type' => 'Html',
    ];

    protected function redererChanges(array $changes): string
    {
        if (empty($changes)) {
            return $this->getResultForIdenticals();
        }

        $wrapperClasses = [
            ...$this->options['wrapperClasses'],
            'diff', 'diff-html', 'diff-side-by-side',
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

        $colspan = $this->options['lineNumbers'] ? ' colspan="2"' : '';

        return
            '<thead>' .
                '<tr>' .
                    '<th' . $colspan . '>' . $this->_('old_version') . '</th>' .
                    '<th' . $colspan . '>' . $this->_('new_version') . '</th>' .
                '</tr>' .
            '</thead>';
    }

    /**
     * Renderer the table separate block.
     */
    protected function renderTableSeparateBlock(): string
    {
        $colspan = $this->options['lineNumbers'] ? '4' : '2';

        return
            '<tbody class="skipped">' .
                '<tr>' .
                    '<td colspan="' . $colspan . '"></td>' .
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
        $ret = '';

        $rowCount = \count($block['new']['lines']);

        for ($no = 0; $no < $rowCount; ++$no) {
            $ret .= $this->renderTableRow(
                $block['old']['lines'][$no],
                $block['new']['lines'][$no],
                $block['old']['offset'] + $no + 1,
                $block['new']['offset'] + $no + 1,
            );
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
        $ret = '';

        foreach ($block['new']['lines'] as $no => $newLine) {
            $ret .= $this->renderTableRow(
                null,
                $newLine,
                null,
                $block['new']['offset'] + $no + 1,
            );
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
        $ret = '';

        foreach ($block['old']['lines'] as $no => $oldLine) {
            $ret .= $this->renderTableRow(
                $oldLine,
                null,
                $block['old']['offset'] + $no + 1,
                null,
            );
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
        $ret = '';

        $lineCountMax = max(\count($block['old']['lines']), \count($block['new']['lines']));

        for ($no = 0; $no < $lineCountMax; ++$no) {
            if (isset($block['old']['lines'][$no])) {
                $oldLineNum = $block['old']['offset'] + $no + 1;
                $oldLine = $block['old']['lines'][$no];
            } else {
                $oldLineNum = $oldLine = null;
            }

            if (isset($block['new']['lines'][$no])) {
                $newLineNum = $block['new']['offset'] + $no + 1;
                $newLine = $block['new']['lines'][$no];
            } else {
                $newLineNum = $newLine = null;
            }

            $ret .= $this->renderTableRow($oldLine, $newLine, $oldLineNum, $newLineNum);
        }

        return $ret;
    }

    /**
     * Renderer a content row of the output table.
     *
     * @param null|string $oldLine    the old line
     * @param null|string $newLine    the new line
     * @param null|int    $oldLineNum the old line number
     * @param null|int    $newLineNum the new line number
     */
    protected function renderTableRow(
        ?string $oldLine,
        ?string $newLine,
        ?int $oldLineNum,
        ?int $newLineNum
    ): string {
        return
            '<tr>' .
                (
                    $this->options['lineNumbers']
                        ? $this->renderLineNumberColumn('old', $oldLineNum)
                        : ''
                ) .
                $this->renderLineContentColumn('old', $oldLine) .
                (
                    $this->options['lineNumbers']
                        ? $this->renderLineNumberColumn('new', $newLineNum)
                        : ''
                ) .
                $this->renderLineContentColumn('new', $newLine) .
            '</tr>';
    }

    /**
     * Renderer the line number column.
     *
     * @param string   $type    the diff type
     * @param null|int $lineNum the line number
     */
    protected function renderLineNumberColumn(string $type, ?int $lineNum): string
    {
        return isset($lineNum)
            ? '<th class="n-' . $type . '">' . $lineNum . '</th>'
            : '<th></th>';
    }

    /**
     * Renderer the line content column.
     *
     * @param string      $type    the diff type
     * @param null|string $content the line content
     */
    protected function renderLineContentColumn(string $type, ?string $content): string
    {
        return
            '<td class="' . $type . (isset($content) ? '' : ' none') . '">' .
                $content .
            '</td>';
    }
}
