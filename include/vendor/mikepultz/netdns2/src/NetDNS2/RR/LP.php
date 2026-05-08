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
 * LP Resource Record - RFC6742 section 2.4
 *
 *   0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |          Preference           |                               /
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               /
 *  /                                                               /
 *  /                              FQDN                             /
 *  /                                                               /
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 * @property int $preference
 * @property \NetDNS2\Data\Domain $fqdn
 */
final class LP extends \NetDNS2\RR
{
    /**
     * The preference
     */
    protected int $preference;

    /**
     * The fdqn field
     */
    protected \NetDNS2\Data\Domain $fqdn;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->preference . ' ' . $this->fqdn . '.';
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->preference = intval($this->sanitize(array_shift($_rdata)));
        $this->fqdn       = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, array_shift($_rdata));

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

        $val = unpack('n', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        $this->preference = ((array)$val)[1];

        $offset = $_packet->offset + 2;

        $this->fqdn = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $_packet, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->fqdn->length() == 0)
        {
            return '';
        }

        $data = pack('n', $this->preference) . $this->fqdn->encode();

        $_packet->offset += strlen($data);

        return $data;
    }
}
