<?php

/**
 * This file is part of the NetDNS2 package.
 *
 * (c) Mike Pultz <mike@mikepultz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace NetDNS2\Tests;

/**
 * test class to exercise NetDNS2\BitMap helper functions
 *
 */
class BitMapTest extends \PHPUnit\Framework\TestCase
{
    /**
     * function to test that validateArray() returns an empty array for empty input
     *
     * @return void
     * @access public
     *
     */
    public function testValidateArrayEmpty()
    {
        $this->assertSame([], \NetDNS2\BitMap::validateArray([]));
    }

    /**
     * function to test that validateArray() throws on a negative integer
     *
     * @return void
     * @access public
     *
     */
    public function testValidateArrayNegativeThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\BitMap::validateArray([-1]);
    }

    /**
     * function to test that validateArray() throws on an integer greater than 255
     *
     * @return void
     * @access public
     *
     */
    public function testValidateArrayOutOfRangeThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\BitMap::validateArray([256]);
    }

    /**
     * function to test that validateArray() throws on an unknown mnemonic that is not a TYPE prefix
     *
     * @return void
     * @access public
     *
     */
    public function testValidateArrayUnknownMnemonicThrows()
    {
        $this->expectException(\NetDNS2\Exception::class);

        \NetDNS2\BitMap::validateArray(['BADTYPE']);
    }

    /**
     * function to test that bitMapToArray() returns an empty array for an empty bitmap string
     *
     * @return void
     * @access public
     *
     */
    public function testBitMapToArrayEmpty()
    {
        $this->assertSame([], \NetDNS2\BitMap::bitMapToArray(''));
    }

    /**
     * function to test that arrayToBitMap() silently omits meta/qtype RR types
     *
     * ANY (255) is a meta type; it must not appear in the resulting bitmap.
     *
     * @return void
     * @access public
     *
     */
    public function testMetaTypeOmitted()
    {
        $bitmap = \NetDNS2\BitMap::arrayToBitMap(['A', 'ANY']);
        $result = \NetDNS2\BitMap::bitMapToArray($bitmap);

        $this->assertEqualsCanonicalizing(['A'], $result, 'BitMapTest: meta type ANY should be omitted from the bitmap');
    }

    /**
     * function to test a round-trip with types spanning two bitmap windows
     *
     * A=1 lives in window 0; CAA=257 lives in window 1 (257 / 256 = 1, bit 1).
     * The round-trip must preserve both types regardless of ordering.
     *
     * @return void
     * @access public
     *
     */
    public function testMultiWindowRoundTrip()
    {
        $input  = ['A', 'CAA'];
        $bitmap = \NetDNS2\BitMap::arrayToBitMap($input);
        $result = \NetDNS2\BitMap::bitMapToArray($bitmap);

        $this->assertEqualsCanonicalizing($input, $result, 'BitMapTest: multi-window round-trip failed');
    }
}
