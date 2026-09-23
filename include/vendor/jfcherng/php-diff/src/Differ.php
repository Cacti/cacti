<?php

declare(strict_types=1);

namespace Jfcherng\Diff;

use Jfcherng\Diff\Utility\Arr;

/**
 * A comprehensive library for generating differences between two strings
 * in multiple formats (unified, side by side HTML etc).
 *
 * @author Jack Cherng <jfcherng@gmail.com>
 * @author Chris Boulton <chris.boulton@interspire.com>
 *
 * @see http://github.com/chrisboulton/php-diff
 */
final class Differ
{
    /**
     * @var int a safe number for indicating showing all contexts
     */
    public const CONTEXT_ALL = \PHP_INT_MAX >> 3;

    /**
     * @var string used to indicate a line has no EOL
     *
     * Arbitrary chars from the 15-16th Unicode reserved areas
     * and hopefully, they won't appear in source texts
     */
    public const LINE_NO_EOL = "\u{fcf28}\u{fc231}";

    /**
     * @var array cached properties and their default values
     */
    private const CACHED_PROPERTIES = [
        'groupedOpcodes' => [],
        'groupedOpcodesGnu' => [],
        'oldNoEolAtEofIdx' => -1,
        'newNoEolAtEofIdx' => -1,
        'oldNewComparison' => 0,
    ];

    /**
     * @var array array of the options that have been applied for generating the diff
     */
    public array $options = [];

    /**
     * @var string[] the old sequence
     */
    private array $old = [];

    /**
     * @var string[] the new sequence
     */
    private array $new = [];

    /**
     * @var bool is any of cached properties dirty?
     */
    private bool $isCacheDirty = true;

    /**
     * @var SequenceMatcher the sequence matcher
     */
    private SequenceMatcher $sequenceMatcher;

    private int $oldSrcLength = 0;

    private int $newSrcLength = 0;

    /**
     * @var int the end index for the old if the old has no EOL at EOF
     *          `-1` means the old has an EOL at EOF
     */
    private int $oldNoEolAtEofIdx = -1;

    /**
     * @var int the end index for the new if the new has no EOL at EOF
     *          `-1` means the new has an EOL at EOF
     */
    private int $newNoEolAtEofIdx = -1;

    /**
     * @var int the result of comparing the old and the new with the spaceship operator
     *          `-1` means `old < new`, `0` means `old == new`, `1` means `old > new`
     */
    private int $oldNewComparison = 0;

    /**
     * @var int[][][] array containing the generated opcodes for the differences between the two items
     */
    private array $groupedOpcodes = [];

    /**
     * @var int[][][] array containing the generated opcodes for the differences between the two items (GNU version)
     */
    private array $groupedOpcodesGnu = [];

    /**
     * @var array associative array of the default options available for the Differ class and their default value
     */
    private static array $defaultOptions = [
        // show how many neighbor lines
        // Differ::CONTEXT_ALL can be used to show the whole file
        'context' => 3,
        // ignore case difference
        'ignoreCase' => false,
        // ignore line ending difference
        'ignoreLineEnding' => false,
        // ignore whitespace difference
        'ignoreWhitespace' => false,
        // if the input sequence is too long, it will just gives up (especially for char-level diff)
        'lengthLimit' => 2000,
        // if truthy, when inputs are identical, the whole inputs will be rendered in the output
        'fullContextIfIdentical' => false,
    ];

    /**
     * The constructor.
     *
     * @param string[] $old     array containing the lines of the old string to compare
     * @param string[] $new     array containing the lines for the new string to compare
     * @param array    $options the options
     */
    public function __construct(array $old, array $new, array $options = [])
    {
        $this->sequenceMatcher = new SequenceMatcher([], []);

        $this->setOldNew($old, $new)->setOptions($options);
    }

    /**
     * Set old and new.
     *
     * @param string[] $old the old
     * @param string[] $new the new
     */
    public function setOldNew(array $old, array $new): self
    {
        return $this->setOld($old)->setNew($new);
    }

    /**
     * Set old.
     *
     * @param string[] $old the old
     */
    public function setOld(array $old): self
    {
        if ($this->old !== $old) {
            $this->old = $old;
            $this->isCacheDirty = true;
        }

        return $this;
    }

    /**
     * Set new.
     *
     * @param string[] $new the new
     */
    public function setNew(array $new): self
    {
        if ($this->new !== $new) {
            $this->new = $new;
            $this->isCacheDirty = true;
        }

        return $this;
    }

    /**
     * Set the options.
     *
     * @param array $options the options
     */
    public function setOptions(array $options): self
    {
        $mergedOptions = $options + self::$defaultOptions;

        if ($this->options !== $mergedOptions) {
            $this->options = $mergedOptions;
            $this->isCacheDirty = true;
        }

        return $this;
    }

    /**
     * Get a range of lines from $start to $end from the old.
     *
     * @param int      $start the starting index (negative = count from backward)
     * @param null|int $end   the ending index (negative = count from backward)
     *                        if is null, it returns a slice from $start to the end
     *
     * @return string[] array of all of the lines between the specified range
     */
    public function getOld(int $start = 0, ?int $end = null): array
    {
        return Arr::getPartialByIndex($this->old, $start, $end);
    }

    /**
     * Get a range of lines from $start to $end from the new.
     *
     * @param int      $start the starting index (negative = count from backward)
     * @param null|int $end   the ending index (negative = count from backward)
     *                        if is null, it returns a slice from $start to the end
     *
     * @return string[] array of all of the lines between the specified range
     */
    public function getNew(int $start = 0, ?int $end = null): array
    {
        return Arr::getPartialByIndex($this->new, $start, $end);
    }

    /**
     * Get the options.
     *
     * @return array the options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the old no EOL at EOF index.
     *
     * @return int the old no EOL at EOF index
     */
    public function getOldNoEolAtEofIdx(): int
    {
        return $this->finalize()->oldNoEolAtEofIdx;
    }

    /**
     * Get the new no EOL at EOF index.
     *
     * @return int the new no EOL at EOF index
     */
    public function getNewNoEolAtEofIdx(): int
    {
        return $this->finalize()->newNoEolAtEofIdx;
    }

    /**
     * Compare the old and the new with the spaceship operator.
     */
    public function getOldNewComparison(): int
    {
        return $this->finalize()->oldNewComparison;
    }

    /**
     * Get the singleton.
     */
    public static function getInstance(): self
    {
        static $singleton;

        return $singleton ??= new self([], []);
    }

    /**
     * Gets the diff statistics such as inserted and deleted etc...
     *
     * @return array<string,float> the statistics
     */
    public function getStatistics(): array
    {
        $ret = [
            'inserted' => 0,
            'deleted' => 0,
            'unmodified' => 0,
            'changedRatio' => 0.0,
        ];

        foreach ($this->getGroupedOpcodes() as $hunk) {
            foreach ($hunk as [$op, $i1, $i2, $j1, $j2]) {
                if ($op & (SequenceMatcher::OP_INS | SequenceMatcher::OP_REP)) {
                    $ret['inserted'] += $j2 - $j1;
                }
                if ($op & (SequenceMatcher::OP_DEL | SequenceMatcher::OP_REP)) {
                    $ret['deleted'] += $i2 - $i1;
                }
            }
        }

        $ret['unmodified'] = $this->oldSrcLength - $ret['deleted'];
        $ret['changedRatio'] = 1 - ($ret['unmodified'] / $this->oldSrcLength);

        return $ret;
    }

    /**
     * Generate a list of the compiled and grouped opcodes for the differences between the
     * two strings. Generally called by the renderer, this class instantiates the sequence
     * matcher and performs the actual diff generation and return an array of the opcodes
     * for it. Once generated, the results are cached in the Differ class instance.
     *
     * @return int[][][] array of the grouped opcodes for the generated diff
     */
    public function getGroupedOpcodes(): array
    {
        $this->finalize();

        if (!empty($this->groupedOpcodes)) {
            return $this->groupedOpcodes;
        }

        $old = $this->old;
        $new = $this->new;

        if ($this->oldNewComparison === 0 && $this->options['fullContextIfIdentical']) {
            return [
                [
                    [SequenceMatcher::OP_EQ, 0, \count($old), 0, \count($new)],
                ],
            ];
        }

        $this->getGroupedOpcodesPre($old, $new);

        $opcodes = $this->sequenceMatcher
            ->setSequences($old, $new)
            ->getGroupedOpcodes($this->options['context'])
        ;

        $this->getGroupedOpcodesPost($opcodes);

        return $this->groupedOpcodes = $opcodes;
    }

    /**
     * A EOL-at-EOF-sensitive version of getGroupedOpcodes().
     *
     * @return int[][][] array of the grouped opcodes for the generated diff (GNU version)
     */
    public function getGroupedOpcodesGnu(): array
    {
        $this->finalize();

        if (!empty($this->groupedOpcodesGnu)) {
            return $this->groupedOpcodesGnu;
        }

        $old = $this->old;
        $new = $this->new;

        if ($this->oldNewComparison === 0 && $this->options['fullContextIfIdentical']) {
            return [
                [
                    [SequenceMatcher::OP_EQ, 0, \count($old), 0, \count($new)],
                ],
            ];
        }

        $this->getGroupedOpcodesGnuPre($old, $new);

        $opcodes = $this->sequenceMatcher
            ->setSequences($old, $new)
            ->getGroupedOpcodes($this->options['context'])
        ;

        $this->getGroupedOpcodesGnuPost($opcodes);

        return $this->groupedOpcodesGnu = $opcodes;
    }

    /**
     * Triggered before getGroupedOpcodes(). May modify the $old and $new.
     *
     * @param string[] $old the old
     * @param string[] $new the new
     */
    private function getGroupedOpcodesPre(array &$old, array &$new): void
    {
        // append these lines to make sure the last block of the diff result is OP_EQ
        static $eolAtEofHelperLines = [
            SequenceMatcher::APPENDED_HELPER_LINE,
            SequenceMatcher::APPENDED_HELPER_LINE,
            SequenceMatcher::APPENDED_HELPER_LINE,
            SequenceMatcher::APPENDED_HELPER_LINE,
        ];

        $this->oldSrcLength = \count($old);
        array_push($old, ...$eolAtEofHelperLines);

        $this->newSrcLength = \count($new);
        array_push($new, ...$eolAtEofHelperLines);
    }

    /**
     * Triggered after getGroupedOpcodes(). May modify the $opcodes.
     *
     * @param int[][][] $opcodes the opcodes
     */
    private function getGroupedOpcodesPost(array &$opcodes): void
    {
        // remove those extra lines cause by adding extra SequenceMatcher::APPENDED_HELPER_LINE lines
        foreach ($opcodes as $hunkIdx => &$hunk) {
            foreach ($hunk as $blockIdx => &$block) {
                // range overflow
                if ($block[1] > $this->oldSrcLength) {
                    $block[1] = $this->oldSrcLength;
                }
                if ($block[2] > $this->oldSrcLength) {
                    $block[2] = $this->oldSrcLength;
                }
                if ($block[3] > $this->newSrcLength) {
                    $block[3] = $this->newSrcLength;
                }
                if ($block[4] > $this->newSrcLength) {
                    $block[4] = $this->newSrcLength;
                }

                // useless extra block?
                /** @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset */
                if ($block[1] === $block[2] && $block[3] === $block[4]) {
                    unset($hunk[$blockIdx]);
                }
            }

            if (empty($hunk)) {
                unset($opcodes[$hunkIdx]);
            }
        }
    }

    /**
     * Triggered before getGroupedOpcodesGnu(). May modify the $old and $new.
     *
     * @param string[] $old the old
     * @param string[] $new the new
     */
    private function getGroupedOpcodesGnuPre(array &$old, array &$new): void
    {
        /**
         * Make the lines to be prepared for GNU-style diff.
         *
         * This method checks whether $lines has no EOL at EOF and append a special
         * indicator to the last line.
         *
         * @param string[] $lines the lines created by simply explode("\n", $string)
         */
        $createGnuCompatibleLines = static function (array $lines): array {
            // note that the $lines should not be empty at this point
            // they have at least one element "" in the array because explode("\n", "") === [""]
            $lastLineIdx = \count($lines) - 1;
            $lastLine = &$lines[$lastLineIdx];

            if ($lastLine === '') {
                // remove the last plain "" line since we don't need it anymore
                // use array_slice() to also reset the array index
                $lines = \array_slice($lines, 0, -1);
            } else {
                // this means the original source has no EOL at EOF
                // we append a special indicator to that line so it no longer matches
                $lastLine .= self::LINE_NO_EOL;
            }

            return $lines;
        };

        $old = $createGnuCompatibleLines($old);
        $new = $createGnuCompatibleLines($new);

        $this->getGroupedOpcodesPre($old, $new);
    }

    /**
     * Triggered after getGroupedOpcodesGnu(). May modify the $opcodes.
     *
     * @param int[][][] $opcodes the opcodes
     */
    private function getGroupedOpcodesGnuPost(array &$opcodes): void
    {
        $this->getGroupedOpcodesPost($opcodes);
    }

    /**
     * Claim this class has settled down and we could calculate cached
     * properties by current properties.
     *
     * This method must be called before accessing cached properties to
     * make suer that you will not get a outdated cached value.
     *
     * @internal
     */
    private function finalize(): self
    {
        if ($this->isCacheDirty) {
            $this->resetCachedResults();

            $this->oldNoEolAtEofIdx = $this->getOld(-1) === [''] ? -1 : \count($this->old);
            $this->newNoEolAtEofIdx = $this->getNew(-1) === [''] ? -1 : \count($this->new);
            $this->oldNewComparison = $this->old <=> $this->new;

            $this->sequenceMatcher->setOptions($this->options);
        }

        return $this;
    }

    /**
     * Reset cached results.
     */
    private function resetCachedResults(): self
    {
        foreach (self::CACHED_PROPERTIES as $property => $value) {
            $this->{$property} = $value;
        }

        $this->isCacheDirty = false;

        return $this;
    }
}
