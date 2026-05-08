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
 * DS Resource Record - RFC4034 sction 5.1
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |           Key Tag             |  Algorithm    |  Digest Type  |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   /                                                               /
 *   /                            Digest                             /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 * @property int $keytag
 * @property \NetDNS2\ENUM\DNSSEC\Algorithm $algorithm
 * @property \NetDNS2\ENUM\DNSSEC\Digest $digesttype
 * @property string $digest
 */
class DS extends \NetDNS2\RR
{
    /**
     * key tag
     */
    protected int $keytag;

    /**
     * algorithm number
     */
    protected \NetDNS2\ENUM\DNSSEC\Algorithm $algorithm;

    /**
     * algorithm used to construct the digest
     */
    protected \NetDNS2\ENUM\DNSSEC\Digest $digesttype;

    /**
     * the digest data
     */
    protected string $digest;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->keytag . ' ' . $this->algorithm->value . ' ' . $this->digesttype->value . ' ' . $this->digest;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->keytag     = intval($this->sanitize(array_shift($_rdata)));
        $this->algorithm  = \NetDNS2\ENUM\DNSSEC\Algorithm::set(intval($this->sanitize(array_shift($_rdata))));
        $this->digesttype = \NetDNS2\ENUM\DNSSEC\Digest::set(intval($this->sanitize(array_shift($_rdata))));
        $this->digest     = $this->sanitize(implode('', $_rdata));

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
        // unpack the keytag, algorithm and digesttype
        //
        $val = unpack('nw/Cx/Cy/H*z', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('w' => $this->keytag, 'x' => $algorithm, 'y' => $digesttype, 'z' => $this->digest) = (array)$val;

        $this->algorithm  = \NetDNS2\ENUM\DNSSEC\Algorithm::set($algorithm);
        $this->digesttype = \NetDNS2\ENUM\DNSSEC\Digest::set($digesttype);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (strlen($this->digest) == 0)
        {
            return '';
        }

        $data = pack('nCCH*', $this->keytag, $this->algorithm->value, $this->digesttype->value, $this->digest);

        $_packet->offset += strlen($data);

        return $data;
    }
}
