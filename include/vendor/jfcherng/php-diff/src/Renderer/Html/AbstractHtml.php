<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Html;

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\Factory\LineRendererFactory;
use Jfcherng\Diff\Renderer\AbstractRenderer;
use Jfcherng\Diff\Renderer\Html\LineRenderer\AbstractLineRenderer;
use Jfcherng\Diff\Renderer\RendererConstant;
use Jfcherng\Diff\SequenceMatcher;
use Jfcherng\Utility\MbString;

/**
 * Base renderer for rendering HTML-based diffs.
 */
abstract class AbstractHtml extends AbstractRenderer
{
    /**
     * @var bool is this renderer pure text?
     */
    public const IS_TEXT_RENDERER = false;

    /**
     * @var string[] array of the different opcodes and how they are mapped to HTML classes
     *
     * @todo rename to OP_CLASS_MAP in v7
     */
    public const TAG_CLASS_MAP = [
        SequenceMatcher::OP_DEL => 'del',
        SequenceMatcher::OP_EQ => 'eq',
        SequenceMatcher::OP_INS => 'ins',
        SequenceMatcher::OP_REP => 'rep',
    ];

    /**
     * Auto format the content in "changes" to be suitable for HTML output.
     *
     * This may not be a wanted behavior for some (custom) renderers
     * if they want to do this by themselves in a later stage.
     *
     * @var bool
     */
    public const AUTO_FORMAT_CHANGES = true;

    public function getResultForIdenticalsDefault(): string
    {
        return '';
    }

    /**
     * Render and return an array structure suitable for generating HTML
     * based differences. Generally called by subclasses that generate a
     * HTML based diff and return an array of the changes to show in the diff.
     *
     * @param Differ $differ the differ object
     *
     * @return array[][] generated changes, suitable for presentation in HTML
     */
    public function getChanges(Differ $differ): array
    {
        $lineRenderer = LineRendererFactory::make(
            $this->options['detailLevel'],
            $differ->getOptions(),
            $this->options,
        );

        $old = $differ->getOld();
        $new = $differ->getNew();

        $changes = [];

        foreach ($differ->getGroupedOpcodes() as $hunk) {
            $change = [];

            foreach ($hunk as [$op, $i1, $i2, $j1, $j2]) {
                $change[] = $this->getDefaultBlock($op, $i1, $j1);
                $block = &$change[\count($change) - 1];

                // if there are same amount of lines replaced
                // we can render the inner detailed changes with corresponding lines
                // @todo or use LineRenderer to do the job regardless different line counts?
                if ($op === SequenceMatcher::OP_REP && $i2 - $i1 === $j2 - $j1) {
                    for ($k = $i2 - $i1 - 1; $k >= 0; --$k) {
                        $this->renderChangedExtent($lineRenderer, $old[$i1 + $k], $new[$j1 + $k]);
                    }
                }

                $block['old']['lines'] = \array_slice($old, $i1, $i2 - $i1);
                $block['new']['lines'] = \array_slice($new, $j1, $j2 - $j1);
            }
            unset($block);

            $changes[] = $change;
        }

        if (static::AUTO_FORMAT_CHANGES) {
            $this->formatChanges($changes);
        }

        return $changes;
    }

    protected function renderWorker(Differ $differ): string
    {
        $rendered = $this->redererChanges($this->getChanges($differ));

        return $this->cleanUpDummyHtmlClosures($rendered);
    }

    protected function renderArrayWorker(array $differArray): string
    {
        $this->ensureChangesUseIntTag($differArray);

        $rendered = $this->redererChanges($differArray);

        return $this->cleanUpDummyHtmlClosures($rendered);
    }

    /**
     * Render the array of changes.
     *
     * @param array[][] $changes the changes
     *
     * @todo rename typo to renderChanges() in v7
     */
    abstract protected function redererChanges(array $changes): string;

    /**
     * Renderer the changed extent.
     *
     * @param AbstractLineRenderer $lineRenderer the line renderer
     * @param string               $old          the old line
     * @param string               $new          the new line
     */
    protected function renderChangedExtent(AbstractLineRenderer $lineRenderer, string &$old, string &$new): void
    {
        static $mbOld, $mbNew;

        $mbOld ??= new MbString();
        $mbNew ??= new MbString();

        $mbOld->set($old);
        $mbNew->set($new);

        $lineRenderer->render($mbOld, $mbNew);

        $old = $mbOld->get();
        $new = $mbNew->get();
    }

    /**
     * Get the default block.
     *
     * @param int $op the operation
     * @param int $i1 begin index of the diff of the old array
     * @param int $j1 begin index of the diff of the new array
     *
     * @return array the default block
     *
     * @todo rename tag to op in v7
     */
    protected function getDefaultBlock(int $op, int $i1, int $j1): array
    {
        return [
            'tag' => $op,
            'old' => [
                'offset' => $i1,
                'lines' => [],
            ],
            'new' => [
                'offset' => $j1,
                'lines' => [],
            ],
        ];
    }

    /**
     * Make the content in "changes" suitable for HTML output.
     *
     * @param array[][] $changes the changes
     */
    final protected function formatChanges(array &$changes): void
    {
        foreach ($changes as &$hunk) {
            foreach ($hunk as &$block) {
                $block['old']['lines'] = $this->formatLines($block['old']['lines']);
                $block['new']['lines'] = $this->formatLines($block['new']['lines']);

                /** @phan-suppress-next-line PhanTypeInvalidLeftOperandOfBitwiseOp */
                if ($block['tag'] & (SequenceMatcher::OP_REP | SequenceMatcher::OP_DEL)) {
                    $block['old']['lines'] = str_replace(
                        RendererConstant::HTML_CLOSURES,
                        RendererConstant::HTML_CLOSURES_DEL,
                        $block['old']['lines'],
                    );
                }

                /** @phan-suppress-next-line PhanTypeInvalidLeftOperandOfBitwiseOp */
                if ($block['tag'] & (SequenceMatcher::OP_REP | SequenceMatcher::OP_INS)) {
                    $block['new']['lines'] = str_replace(
                        RendererConstant::HTML_CLOSURES,
                        RendererConstant::HTML_CLOSURES_INS,
                        $block['new']['lines'],
                    );
                }
            }
        }
    }

    /**
     * Make a series of lines suitable for outputting in a HTML rendered diff.
     *
     * @param string[] $lines array of lines to format
     *
     * @return string[] array of the formatted lines
     */
    protected function formatLines(array $lines): array
    {
        /**
         * To prevent from invoking the same function calls for several times,
         * we can glue lines into a string and call functions for one time.
         * After that, we split the string back into lines.
         */
        return explode(
            RendererConstant::IMPLODE_DELIMITER,
            $this->formatStringFromLines(
                implode(
                    RendererConstant::IMPLODE_DELIMITER,
                    $lines,
                ),
            ),
        );
    }

    /**
     * Make a string suitable for outputting in a HTML rendered diff.
     *
     * This my involve replacing tab characters with spaces, making the HTML safe
     * for output, ensuring that double spaces are replaced with &nbsp; etc.
     *
     * @param string $string the string of imploded lines
     *
     * @return string the formatted string
     */
    protected function formatStringFromLines(string $string): string
    {
        if (!$this->options['spaceToHtmlTag']) {
            $string = $this->expandTabs($string, $this->options['tabSize']);
        }

        $string = $this->htmlSafe($string);

        if ($this->options['spacesToNbsp']) {
            $string = $this->htmlFixSpaces($string);
        }

        if ($this->options['spaceToHtmlTag']) {
            $string = $this->htmlReplaceSpacesToHtmlTag($string);
        }

        return $string;
    }

    /**
     * Replace tabs in a string with a number of spaces.
     *
     * @param string $string          the input string which may contain tabs
     * @param int    $tabSize         one tab = how many spaces, a negative does nothing
     * @param bool   $onlyLeadingTabs only expand leading tabs
     *
     * @return string the string with the tabs converted to spaces
     */
    protected function expandTabs(string $string, int $tabSize = 4, bool $onlyLeadingTabs = false): string
    {
        if ($tabSize < 0) {
            return $string;
        }

        if ($onlyLeadingTabs) {
            return preg_replace_callback(
                "/^[ \t]{1,}/mS", // tabs and spaces may be mixed
                static fn (array $matches): string => str_replace("\t", str_repeat(' ', $tabSize), $matches[0]),
                $string,
            );
        }

        return str_replace("\t", str_repeat(' ', $tabSize), $string);
    }

    /**
     * Make a string containing HTML safe for output on a page.
     *
     * @param string $string the string
     *
     * @return string the string with the HTML characters replaced by entities
     */
    protected function htmlSafe(string $string): string
    {
        return htmlspecialchars($string, \ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * Replace a string containing spaces with a HTML representation having "&nbsp;".
     *
     * @param string $string the string of spaces
     *
     * @return string the HTML representation of the string
     */
    protected function htmlFixSpaces(string $string): string
    {
        return str_replace(' ', '&nbsp;', $string);
    }

    /**
     * Replace spaces/tabs with HTML tags, which may be styled in frontend with CSS.
     *
     * @param string $string the string of spaces
     *
     * @return string the HTML representation of the string
     */
    protected function htmlReplaceSpacesToHtmlTag(string $string): string
    {
        return strtr($string, [
            ' ' => '<span class="ch sp"> </span>',
            "\t" => "<span class=\"ch tab\">\t</span>",
        ]);
    }

    /**
     * Make sure the "changes" array uses int "tag".
     *
     * Internally, we would like always int form for better performance.
     *
     * @param array[][] $changes the changes
     */
    protected function ensureChangesUseIntTag(array &$changes): void
    {
        // check if the tag is already int type
        if (\is_int($changes[0][0]['tag'] ?? null)) {
            return;
        }

        foreach ($changes as &$hunks) {
            foreach ($hunks as &$block) {
                $block['tag'] = SequenceMatcher::opStrToInt($block['tag']);
            }
        }
    }

    /**
     * Clean up empty HTML closures in the given string.
     *
     * @param string $string the string
     */
    protected function cleanUpDummyHtmlClosures(string $string): string
    {
        return str_replace(
            [
                RendererConstant::HTML_CLOSURES_DEL[0] . RendererConstant::HTML_CLOSURES_DEL[1],
                RendererConstant::HTML_CLOSURES_INS[0] . RendererConstant::HTML_CLOSURES_INS[1],
            ],
            '',
            $string,
        );
    }
}
