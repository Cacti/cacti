<?php

declare(strict_types=1);

namespace Jfcherng\Utility;

/**
 * An internal UTF-32 multi-bytes string class.
 *
 * Because UTF-8 is varied-width, mb_*() is kinda O(n) when doing decoding.
 * Using iconv() to make it UTF-32 and work with str*() can be possibly faster.
 *
 * UTF-32 is a fix-width encoding (1 char = 4 bytes).
 * Note that the first 4 bytes in a UTF-32 string is the header (endian bytes).
 *
 * @author Jack Cherng <jfcherng@gmail.com>
 */
class MbString extends \ArrayObject implements \Stringable
{
    public const MBSTRING_CONVMETHOD_ICONV = 1;
    public const MBSTRING_CONVMETHOD_MBSTRING = 2;

    /**
     * The way to convert text encoding.
     *
     * @var int
     */
    public static $convMethod;

    /**
     * UTF-32 string without endian bytes.
     *
     * @var string
     */
    protected $str;

    /**
     * The original encoding.
     *
     * @var string
     */
    protected $encoding;

    /**
     * The endian bytes for UTF-32.
     *
     * @var string
     */
    protected static $utf32Header;

    /**
     * The constructor.
     *
     * @param string $str      the string
     * @param string $encoding the encoding
     */
    public function __construct(string $str = '', string $encoding = 'UTF-8')
    {
        static::$convMethod ??= static::detectConvEncoding();
        static::$utf32Header ??= static::getUtf32Header();

        $this->encoding = $encoding;
        $this->set($str);
    }

    /**
     * Returns a string representation of the object.
     *
     * @return string string representation of the object
     */
    public function __toString(): string
    {
        return $this->get();
    }

    /**
     * The string setter.
     *
     * @param string $str the string
     */
    public function set(string $str): self
    {
        $this->str = $this->inputConv($str);

        return $this;
    }

    public function setAt(int $idx, string $char): self
    {
        $char = $this->inputConv($char);
        if (\strlen($char) > 4) {
            $char = substr($char, 0, 4);
        }

        $spacesPrepend = $idx - $this->strlen();
        // set index (out of bound)
        if ($spacesPrepend > 0) {
            $this->str .= $this->inputConv(str_repeat(' ', $spacesPrepend)) . $char;
        }
        // set index (in bound)
        else {
            $this->str = substr_replace($this->str, $char, $idx << 2, 4);
        }

        return $this;
    }

    /**
     * The string getter.
     */
    public function get(): string
    {
        return $this->outputConv($this->str);
    }

    /**
     * The raw string getter.
     *
     * @return string the UTF-32-encoded raw string
     */
    public function getRaw(): string
    {
        return $this->str;
    }

    public function getAt(int $idx): string
    {
        return $this->outputConv(substr($this->str, $idx << 2, 4));
    }

    public function getAtRaw(int $idx): string
    {
        return substr($this->str, $idx << 2, 4);
    }

    public function toArray(): array
    {
        return self::strToChars($this->get());
    }

    public function toArraySplit(string $regex, int $limit = -1, $flags = 0): array
    {
        if ($this->str === '') {
            return [];
        }

        return preg_split($regex, $this->get(), $limit, $flags);
    }

    public function toArrayRaw(): array
    {
        if ($this->str === '') {
            return [];
        }

        return str_split($this->str, 4);
    }

    public static function strToChars(string $str): array
    {
        return preg_split('//uS', $str, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    // /////////////////////////////////
    // string manipulation functions //
    // /////////////////////////////////

    public function stripos(string $needle, int $offset = 0)
    {
        $needle = $this->inputConv($needle);
        $pos = stripos($this->str, $needle, $offset << 2);

        return \is_bool($pos) ? $pos : $pos >> 2;
    }

    public function strlen(): int
    {
        return \strlen($this->str) >> 2;
    }

    public function strpos(string $needle, int $offset = 0)
    {
        $needle = $this->inputConv($needle);
        $pos = strpos($this->str, $needle, $offset << 2);

        return \is_bool($pos) ? $pos : $pos >> 2;
    }

    public function substr(int $start = 0, ?int $length = null): string
    {
        return $this->outputConv(
            isset($length)
                ? substr($this->str, $start << 2, $length << 2)
                : substr($this->str, $start << 2),
        );
    }

    public function substr_replace(string $replacement, int $start = 0, ?int $length = null): string
    {
        $replacement = $this->inputConv($replacement);

        return $this->outputConv(
            isset($length)
                ? substr_replace($this->str, $replacement, $start << 2, $length << 2)
                : substr_replace($this->str, $replacement, $start << 2),
        );
    }

    public function strtolower(): string
    {
        return strtolower($this->get());
    }

    public function strtoupper(): string
    {
        return strtoupper($this->get());
    }

    // //////////////////////////////
    // non-manipulative functions //
    // //////////////////////////////

    public function has(string $needle): bool
    {
        $needle = $this->inputConv($needle);

        return str_contains($this->str, $needle);
    }

    public function startsWith(string $needle): bool
    {
        $needle = $this->inputConv($needle);

        return $needle === substr($this->str, 0, \strlen($needle));
    }

    public function endsWith(string $needle): bool
    {
        $needle = $this->inputConv($needle);
        $length = \strlen($needle);

        return $length === 0 ? true : $needle === substr($this->str, -$length);
    }

    // ///////////////////////////////////////////
    // those functions will not return a value //
    // ///////////////////////////////////////////

    public function str_insert_i(string $insert, int $position): self
    {
        $insert = $this->inputConv($insert);
        $this->str = substr_replace($this->str, $insert, $position << 2, 0);

        return $this;
    }

    public function str_enclose_i(array $closures, int $start = 0, ?int $length = null): self
    {
        // ex: $closures = array('{', '}');
        foreach ($closures as &$closure) {
            $closure = $this->inputConv($closure);
        }
        unset($closure);

        if (\count($closures) < 2) {
            $closures[0] = $closures[1] = reset($closures);
        }

        if (isset($length)) {
            $replacement = $closures[0] . substr($this->str, $start << 2, $length << 2) . $closures[1];
            $this->str = substr_replace($this->str, $replacement, $start << 2, $length << 2);
        } else {
            $replacement = $closures[0] . substr($this->str, $start << 2) . $closures[1];
            $this->str = substr_replace($this->str, $replacement, $start << 2);
        }

        return $this;
    }

    public function str_replace_i(string $search, string $replace): self
    {
        $search = $this->inputConv($search);
        $replace = $this->inputConv($replace);
        $this->str = str_replace($search, $replace, $this->str);

        return $this;
    }

    public function substr_replace_i(string $replacement, int $start = 0, ?int $length = null): self
    {
        $replacement = $this->inputConv($replacement);
        $this->str = (
            isset($length)
                ? substr_replace($this->str, $replacement, $start << 2, $length << 2)
                : substr_replace($this->str, $replacement, $start << 2)
        );

        return $this;
    }

    // ///////////////
    // ArrayObject //
    // ///////////////

    public function offsetSet(mixed $idx, mixed $char): void
    {
        $this->setAt($idx, $char);
    }

    public function offsetGet(mixed $idx): string
    {
        return $this->getAt($idx);
    }

    public function offsetExists(mixed $idx): bool
    {
        return \is_int($idx) ? $this->strlen() > $idx : false;
    }

    public function append(mixed $str): void
    {
        $this->str .= $this->inputConv($str);
    }

    public function count(): int
    {
        return $this->strlen();
    }

    // //////////////////
    // misc functions //
    // //////////////////

    /**
     * Gets the utf 32 header.
     *
     * @return string the UTF-32 header or empty string
     */
    protected static function getUtf32Header(): string
    {
        // just use any string to get the endian header, here we use "A"
        $tmp = self::convEncoding('A', 'UTF-8', 'UTF-32');
        // some distributions like "php alpine" docker image won't generate the header
        return $tmp && \strlen($tmp) > 4 ? substr($tmp, 0, 4) : '';
    }

    protected static function detectConvEncoding(): int
    {
        if (\function_exists('iconv') && iconv('UTF-8', 'UTF-32', 'A') !== false) {
            return static::MBSTRING_CONVMETHOD_ICONV;
        }

        if (\function_exists('mb_convert_encoding') && mb_convert_encoding('A', 'UTF-32', 'UTF-8') !== false) {
            return static::MBSTRING_CONVMETHOD_MBSTRING;
        }

        throw new \RuntimeException('Either "iconv" or "mbstring" extension is required.');
    }

    protected static function convEncoding(string $str, string $from, string $to): string
    {
        if (static::$convMethod === static::MBSTRING_CONVMETHOD_ICONV) {
            return iconv($from, $to, $str);
        }

        if (static::$convMethod === static::MBSTRING_CONVMETHOD_MBSTRING) {
            return mb_convert_encoding($str, $to, $from);
        }

        throw new \RuntimeException('Unknown conversion method.');
    }

    /**
     * Convert the output string to its original encoding.
     *
     * @param string $str The string
     */
    protected function outputConv(string $str): string
    {
        if ($str === '') {
            return '';
        }

        return static::convEncoding(static::$utf32Header . $str, 'UTF-32', $this->encoding);
    }

    /**
     * Convert the input string to UTF-32 without header.
     *
     * @param string $str The string
     */
    protected function inputConv(string $str): string
    {
        if ($str === '') {
            return '';
        }

        return substr(static::convEncoding($str, $this->encoding, 'UTF-32'), \strlen(static::$utf32Header));
    }
}
