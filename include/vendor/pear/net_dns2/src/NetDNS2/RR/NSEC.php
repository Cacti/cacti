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

namespace NetDNS2\RR;

/**
 * NSEC Resource Record - RFC3845 section 2.1
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   /                      Next Domain Name                         /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   /                   List of Type Bit Map(s)                     /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
final class NSEC extends \NetDNS2\RR
{
    /**
     * The next owner name
     */
    protected \NetDNS2\Data\Domain $next_domain_name;

    /**
     * identifies the RRset types that exist at the NSEC RR's owner name.
     *
     * @var array<int,string>
     */
    protected array $type_bit_maps = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->next_domain_name . '. ' . implode(' ', $this->type_bit_maps);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     * @param array<string> $_rdata
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->next_domain_name = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, array_shift($_rdata));

        //
        // validate the list of RR's
        //
        $this->type_bit_maps = \NetDNS2\BitMap::validateArray($_rdata);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->rdlength == 0)
        {
            return false;
        }

        //
        // expand the next domain name
        //
        $offset = $_packet->offset;
        $this->next_domain_name = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $_packet, $offset);

        //
        // parse out the RR's from the bitmap
        //
        $this->type_bit_maps = \NetDNS2\BitMap::bitMapToArray(substr($this->rdata, $offset - $_packet->offset));

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->next_domain_name->length() == 0)
        {
            return '';
        }

        $data = $this->next_domain_name->encode() . \NetDNS2\BitMap::arrayToBitMap($this->type_bit_maps);

        $_packet->offset += strlen($data);

        return $data;
    }
}
