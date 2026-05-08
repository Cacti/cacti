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
 * NAPTR Resource Record - RFC2915
 *
 *      0  1  2  3  4  5  6  7  8  9  0  1  2  3  4  5
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                     ORDER                     |
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   |                   PREFERENCE                  |
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   /                     FLAGS                     /
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   /                   SERVICES                    /
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   /                    REGEXP                     /
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *   /                  REPLACEMENT                  /
 *   /                                               /
 *   +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property int $order
 * @property int $preference
 * @property \NetDNS2\Data\Text $flags
 * @property \NetDNS2\Data\Text $services
 * @property \NetDNS2\Data\Text $regexp
 * @property \NetDNS2\Data\Domain $replacement
 */
final class NAPTR extends \NetDNS2\RR
{
    /**
     * the order in which the NAPTR records MUST be processed
     */
    protected int $order;

    /**
     * specifies the order in which NAPTR records with equal "order" values SHOULD be processed
     */
    protected int $preference;

    /**
     * rewrite flags
     */
    protected \NetDNS2\Data\Text $flags;

    /**
     * Specifies the service(s) available down this rewrite path
     */
    protected \NetDNS2\Data\Text $services;

    /**
     * regular expression
     */
    protected \NetDNS2\Data\Text $regexp;

    /**
     * The next NAME to query for NAPTR, SRV, or address records depending on the value of the flags field
     */
    protected \NetDNS2\Data\Domain $replacement;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->order . ' ' . $this->preference . ' ' . \NetDNS2\RR::formatString($this->flags->value()) . ' ' .
            \NetDNS2\RR::formatString($this->services->value()) . ' ' . \NetDNS2\RR::formatString($this->regexp->value()) . ' ' . $this->replacement . '.';
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->order      = intval($this->sanitize(array_shift($_rdata)));
        $this->preference = intval($this->sanitize(array_shift($_rdata)));

        $data = $this->buildString($_rdata);

        if (count($data) == 4)
        {
            $this->flags       = new \NetDNS2\Data\Text($data[0]);
            $this->services    = new \NetDNS2\Data\Text($data[1]);
            $this->regexp      = new \NetDNS2\Data\Text($data[2]);
            $this->replacement = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, $data[3]);

            return true;
        }

        return false;
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

        $val = unpack('nx/ny', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->order, 'y' => $this->preference) = (array)$val;

        $offset = $_packet->offset + 4;

        $this->flags       = new \NetDNS2\Data\Text($_packet->rdata, $offset);
        $this->services    = new \NetDNS2\Data\Text($_packet->rdata, $offset);
        $this->regexp      = new \NetDNS2\Data\Text($_packet->rdata, $offset);

        $this->replacement = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC2535, $_packet, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ( (isset($this->order) === false) || ($this->services->length() == 0) )
        {
            return '';
        }

        $data = pack('nn', $this->order, $this->preference) . $this->flags->encode() . $this->services->encode() . $this->regexp->encode();

        $_packet->offset += strlen($data);

        return $data . $this->replacement->encode($_packet->offset);
    }
}
