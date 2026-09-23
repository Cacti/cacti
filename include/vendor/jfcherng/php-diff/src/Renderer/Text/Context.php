<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Text;

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\SequenceMatcher;

/**
 * Context diff generator.
 *
 * @see https://en.wikipedia.org/wiki/Diff#Context_format
 */
final class Context extends AbstractText
{
    /**
     * {@inheritdoc}
     */
    public const INFO = [
        'desc' => 'Context',
        'type' => 'Text',
    ];

    /**
     * @var int the union of OPs that indicate there is a change
     */
    public const OP_BLOCK_CHANGED =
        SequenceMatcher::OP_DEL |
        SequenceMatcher::OP_INS |
        SequenceMatcher::OP_REP;

    protected function renderWorker(Differ $differ): string
    {
        $ret = '';

        foreach ($differ->getGroupedOpcodesGnu() as $hunk) {
            $lastBlockIdx = \count($hunk) - 1;

            // note that these line number variables are 0-based
            $i1 = $hunk[0][1];
            $i2 = $hunk[$lastBlockIdx][2];
            $j1 = $hunk[0][3];
            $j2 = $hunk[$lastBlockIdx][4];

            $ret .=
                $this->cliColoredString("***************\n", '@') .
                $this->renderHunkHeader('*', $i1, $i2) .
                $this->renderHunkOld($differ, $hunk) .
                $this->renderHunkHeader('-', $j1, $j2) .
                $this->renderHunkNew($differ, $hunk);
        }

        return $ret;
    }

    /**
     * Render the hunk header.
     *
     * @param string $symbol the symbol
     * @param int    $a1     the begin index
     * @param int    $a2     the end index
     */
    protected function renderHunkHeader(string $symbol, int $a1, int $a2): string
    {
        $a1x = $a1 + 1; // 1-based begin line number

        return $this->cliColoredString(
            "{$symbol}{$symbol}{$symbol} " .
            ($a1x < $a2 ? "{$a1x},{$a2}" : $a2) .
            " {$symbol}{$symbol}{$symbol}{$symbol}\n",
            '@', // symbol
        );
    }

    /**
     * Render the old hunk.
     *
     * @param Differ  $differ the differ object
     * @param int[][] $hunk   the hunk
     */
    protected function renderHunkOld(Differ $differ, array $hunk): string
    {
        $ret = '';
        $hunkOps = 0;
        $noEolAtEofIdx = $differ->getOldNoEolAtEofIdx();

        foreach ($hunk as [$op, $i1, $i2, $j1, $j2]) {
            // OP_INS does not belongs to an old hunk
            if ($op === SequenceMatcher::OP_INS) {
                continue;
            }

            $hunkOps |= $op;

            $ret .= $this->renderContext(
                self::SYMBOL_MAP[$op],
                $differ->getOld($i1, $i2),
                $i2 === $noEolAtEofIdx,
            );
        }

        // if there is no content changed, the hunk context should be omitted
        return $hunkOps & self::OP_BLOCK_CHANGED ? $ret : '';
    }

    /**
     * Render the new hunk.
     *
     * @param Differ  $differ the differ object
     * @param int[][] $hunk   the hunk
     */
    protected function renderHunkNew(Differ $differ, array $hunk): string
    {
        $ret = '';
        $hunkOps = 0;
        $noEolAtEofIdx = $differ->getNewNoEolAtEofIdx();

        foreach ($hunk as [$op, $i1, $i2, $j1, $j2]) {
            // OP_DEL does not belongs to a new hunk
            if ($op === SequenceMatcher::OP_DEL) {
                continue;
            }

            $hunkOps |= $op;

            $ret .= $this->renderContext(
                self::SYMBOL_MAP[$op],
                $differ->getNew($j1, $j2),
                $j2 === $noEolAtEofIdx,
            );
        }

        // if there is no content changed, the hunk context should be omitted
        return $hunkOps & self::OP_BLOCK_CHANGED ? $ret : '';
    }

    /**
     * Render the context array with the symbol.
     *
     * @param string   $symbol     the symbol
     * @param string[] $context    the context
     * @param bool     $noEolAtEof there is no EOL at EOF in this block
     */
    protected function renderContext(string $symbol, array $context, bool $noEolAtEof = false): string
    {
        if (empty($context)) {
            return '';
        }

        $ret = "{$symbol} " . implode("\n{$symbol} ", $context) . "\n";
        $ret = $this->cliColoredString($ret, $symbol);

        if ($noEolAtEof) {
            $ret .= self::GNU_OUTPUT_NO_EOL_AT_EOF . "\n";
        }

        return $ret;
    }
}
