<?php declare(strict_types=1);

/**
 * This file is part of the NetDNS2 package.
 *
 * (c) Mike Pultz <mike@mikepultz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace NetDNS2\RR\OPT;

/**
 * RFC 9660 - The DNS Zone Version (ZONEVERSION) Option
 *
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 * 0: |           LABELCOUNT          |            TYPE               |
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 * 2: |                            VERSION                            |
 *    /                                                               /
 *   +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *
 * @property int $label_count
 * @property int $zone_version_type
 * @property string $zone_version
 */
final class ZONEVERSION extends \NetDNS2\RR\OPT
{
    /**
     * indicating the number of labels for the name of the zone that VERSION value refers to
     */
    protected int $label_count = 0;

    /**
     * an unsigned 1-octet type number (TYPE) distinguishing the format and meaning of VERSION
     */
    protected int $zone_version_type;

    /*
     * string conveying the zone version data (VERSION)
     */
    protected string $zone_version;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length . ' ' . $this->label_count . ' ' . $this->zone_version_type . ' ' . $this->zone_version;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->option_length == 0)
        {
            return true;
        }

        $val = unpack('Cx/Cy/H*z', $this->option_data);
        if ($val == false)
        {
            return false;
        }

        list('x' => $this->label_count, 'y' => $this->zone_version_type, 'z' => $this->zone_version) = (array)$val;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // empty option data for a request
        //
        $this->option_data   = '';
        $this->option_length = 0;

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
