<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Html;

use Jfcherng\Diff\SequenceMatcher;

/**
 * Inline HTML diff generator.
 */
final class Inline extends AbstractHtml
{
    /**
     * {@inheritdoc}
     */
    public const INFO = [
        'desc' => 'Inline',
        'type' => 'Html',
    ];

    protected function redererChanges(array $changes): string
    {
        if (empty($changes)) {
            return $this->getResultForIdenticals();
        }

        $wrapperClasses = [
            ...$this->options['wrapperClasses'],
            'diff', 'diff-html', 'diff-inline',
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

        $colspan = $this->options['lineNumbers'] ? '' : ' colspan="2"';

        return
            '<thead>' .
                '<tr>' .
                    (
                        $this->options['lineNumbers']
                        ?
                            '<th>' . $this->_('old_version') . '</th>' .
                            '<th>' . $this->_('new_version') . '</th>' .
                            '<th></th>' // diff symbol column
                        :
                            ''
                    ) .
                    '<th' . $colspan . '>' . $this->_('differences') . '</th>' .
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

        // note that although we are in a OP_EQ situation,
        // the old and the new may not be exactly the same
        // because of ignoreCase, ignoreWhitespace, etc
        foreach ($block['new']['lines'] as $no => $newLine) {
            // we could only pick either the old or the new to show
            // here we pick the new one to let the user know what it is now
            $ret .= $this->renderTableRow(
                'new',
                SequenceMatcher::OP_EQ,
                $newLine,
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
                'new',
                SequenceMatcher::OP_INS,
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
                'old',
                SequenceMatcher::OP_DEL,
                $oldLine,
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
        return
            $this->renderTableBlockDelete($block) .
            $this->renderTableBlockInsert($block);
    }

    /**
     * Renderer a content row of the output table.
     *
     * @param string   $tdClass    the <td> class
     * @param int      $op         the operation
     * @param string   $line       the line
     * @param null|int $oldLineNum the old line number
     * @param null|int $newLineNum the new line number
     */
    protected function renderTableRow(
        string $tdClass,
        int $op,
        string $line,
        ?int $oldLineNum,
        ?int $newLineNum
    ): string {
        return
            '<tr data-type="' . self::SYMBOL_MAP[$op] . '">' .
                (
                    $this->options['lineNumbers']
                        ? $this->renderLineNumberColumns($oldLineNum, $newLineNum)
                        : ''
                ) .
                '<th class="sign ' . self::TAG_CLASS_MAP[$op] . '">' . self::SYMBOL_MAP[$op] . '</th>' .
                '<td class="' . $tdClass . '">' . $line . '</td>' .
            '</tr>';
    }

    /**
     * Renderer the line number columns.
     *
     * @param null|int $oldLineNum The old line number
     * @param null|int $newLineNum The new line number
     */
    protected function renderLineNumberColumns(?int $oldLineNum, ?int $newLineNum): string
    {
        return
            (
                isset($oldLineNum)
                    ? '<th class="n-old">' . $oldLineNum . '</th>'
                    : '<th></th>'
            ) .
            (
                isset($newLineNum)
                    ? '<th class="n-new">' . $newLineNum . '</th>'
                    : '<th></th>'
            );
    }
}
