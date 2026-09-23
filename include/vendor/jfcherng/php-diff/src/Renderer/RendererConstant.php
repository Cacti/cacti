<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer;

final class RendererConstant
{
    /**
     * The base namespace of renderers.
     *
     * @var string
     */
    public const RENDERER_NAMESPACE = __NAMESPACE__;

    /**
     * Available renderer types.
     *
     * @var string[]
     */
    public const RENDERER_TYPES = ['Html', 'Text'];

    /**
     * Closures that are used to enclose different parts in string.
     *
     * Arbitrary chars from the 15-16th Unicode reserved areas
     * and hopefully, they won't appear in source texts.
     *
     * @var string[]
     */
    public const HTML_CLOSURES = ["\u{fcffc}\u{ff2fb}", "\u{fff41}\u{fcffc}"];

    /**
     * Closures that are used to enclose deleted chars in output HTML.
     *
     * @var string[]
     */
    public const HTML_CLOSURES_DEL = ['<del>', '</del>'];

    /**
     * Closures that are used to enclose inserted chars in output HTML.
     *
     * @var string[]
     */
    public const HTML_CLOSURES_INS = ['<ins>', '</ins>'];

    /**
     * The delimiter to be used as the glue in string/array functions.
     *
     * Arbitrary chars from the 15-16th Unicode reserved areas
     * and hopefully, it won't appear in source texts.
     *
     * @var string
     */
    public const IMPLODE_DELIMITER = "\u{ff2fa}\u{fcffc}\u{fff42}";

    /**
     * Regex range for punctuations.
     *
     * Presuming the regex delimiter is "/".
     *
     * @var string
     */
    public const PUNCTUATIONS_RANGE = (
        // Latin-1 Supplement
        // @see https://unicode-table.com/en/blocks/latin-1-supplement/
        "\u{0080}-\u{00BB}" .
        // Spacing Modifier Letters
        // @see https://unicode-table.com/en/blocks/spacing-modifier-letters/
        "\u{02B0}-\u{02FF}" .
        // Combining Diacritical Marks
        // @see https://unicode-table.com/en/blocks/combining-diacritical-marks/
        "\u{0300}-\u{036F}" .
        // Small Form Variants
        // @see https://unicode-table.com/en/blocks/small-form-variants/
        "\u{FE50}-\u{FE6F}" .
        // General Punctuation
        // @see https://unicode-table.com/en/blocks/general-punctuation/
        "\u{2000}-\u{206F}" .
        // Supplemental Punctuation
        // @see https://unicode-table.com/en/blocks/supplemental-punctuation/
        "\u{2E00}-\u{2E7F}" .
        // CJK Symbols and Punctuation
        // @see https://unicode-table.com/en/blocks/cjk-symbols-and-punctuation/
        "\u{3000}-\u{303F}" .
        // Ideographic Symbols and Punctuation
        // @see https://unicode-table.com/en/blocks/ideographic-symbols-and-punctuation/
        "\u{16FE0}-\u{16FFF}" .
        // hmm... these seem to be no rule
        " \t\r\n$,.:;!?'\"()\\[\\]{}%@<=>_+\\-*\\/~\\\\|" .
        '　＄，．：；！？’＂（）［］｛｝％＠＜＝＞＿＋－＊／～＼｜' .
        '「」『』〈〉《》【】()（）‘’“”' .
        '．‧・･•·¿'
    );

    /**
     * Colorize the CLI output if possible.
     *
     * @var int
     */
    public const CLI_COLOR_AUTO = -1;

    /**
     * Force not to colorize the CLI output.
     *
     * @var int
     */
    public const CLI_COLOR_DISABLE = 0;

    /**
     * Force to colorize the CLI output if possible.
     *
     * @var int
     */
    public const CLI_COLOR_ENABLE = 1;
}
