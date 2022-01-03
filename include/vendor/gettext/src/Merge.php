<?php
declare(strict_types = 1);

namespace Gettext;

/**
 * Merge contants.
 */
final class Merge
{
    public const TRANSLATIONS_OURS = 1 << 0;
    public const TRANSLATIONS_THEIRS = 1 << 1;
    public const TRANSLATIONS_OVERRIDE = 1 << 2;

    public const HEADERS_OURS = 1 << 3;
    public const HEADERS_THEIRS = 1 << 4;
    public const HEADERS_OVERRIDE = 1 << 5;

    public const COMMENTS_OURS = 1 << 6;
    public const COMMENTS_THEIRS = 1 << 7;

    public const EXTRACTED_COMMENTS_OURS = 1 << 8;
    public const EXTRACTED_COMMENTS_THEIRS = 1 << 9;

    public const FLAGS_OURS = 1 << 10;
    public const FLAGS_THEIRS = 1 << 11;

    public const REFERENCES_OURS = 1 << 12;
    public const REFERENCES_THEIRS = 1 << 13;

    //Merge strategies
    public const SCAN_AND_LOAD =
          Merge::HEADERS_OVERRIDE
        | Merge::TRANSLATIONS_OURS
        | Merge::TRANSLATIONS_OVERRIDE
        | Merge::EXTRACTED_COMMENTS_OURS
        | Merge::REFERENCES_OURS
        | Merge::FLAGS_THEIRS
        | Merge::COMMENTS_THEIRS;
}
