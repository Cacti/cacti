<?php

namespace Gettext\Utils;

/*
 * Trait to provide the functionality of read/write csv.
 */
trait CsvTrait
{
    private static $csvEscapeChar;

    /**
     * Check whether support the escape_char argument to fgetcsv/fputcsv or not
     *
     * @return bool
     */
    private static function supportsCsvEscapeChar()
    {
        if (self::$csvEscapeChar === null) {
            self::$csvEscapeChar = version_compare(PHP_VERSION, '5.5.4') >= 0;
        }

        return self::$csvEscapeChar;
    }

    /**
     * @param resource $handle
     * @param array $options
     *
     * @return array
     */
    private static function fgetcsv($handle, $options)
    {
        if (self::supportsCsvEscapeChar()) {
            return fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'], $options['escape_char']);
        }

        return fgetcsv($handle, 0, $options['delimiter'], $options['enclosure']);
    }

    /**
     * @param resource $handle
     * @param array $fields
     * @param array $options
     *
     * @return bool|int
     */
    private static function fputcsv($handle, $fields, $options)
    {
        if (self::supportsCsvEscapeChar()) {
            return fputcsv($handle, $fields, $options['delimiter'], $options['enclosure'], $options['escape_char']);
        }

        return fputcsv($handle, $fields, $options['delimiter'], $options['enclosure']);
    }
}
