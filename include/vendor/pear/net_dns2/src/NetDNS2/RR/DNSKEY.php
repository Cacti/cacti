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
 * DNSKEY Resource Record - RFC4034 sction 2.1
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |              Flags            |    Protocol   |   Algorithm   |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   /                                                               /
 *   /                            Public Key                         /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
class DNSKEY extends \NetDNS2\RR
{
    /**
     * flags; this value should not be accessed directly; the $zone, $sep, and $revoke values are used to
     * build the flags value on the way in/out of the object.
     */
    protected int $flags;

    /**
     * flags extracted from the $flags value
     */
    protected bool $zone = false;   // 0x0100
    protected bool $sep = false;    // 0x0001
    protected bool $revoke = false; // 0x0080

    /**
     * protocol
     */
    protected int $protocol;

    /**
     * algorithm used
     */
    protected \NetDNS2\ENUM\DNSSEC\Algorithm $algorithm;

    /**
     * the public key
     */
    protected string $key;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        //
        // pack the flags
        //
        $this->flags = 0;
        $this->flags |= ($this->zone == true) ? 0x0100 : 0;
        $this->flags |= ($this->sep == true) ? 0x0001 : 0;
        $this->flags |= ($this->revoke == true) ? 0x0080 : 0;

        return $this->flags . ' ' . $this->protocol . ' ' . $this->algorithm->value . ' ' . $this->key;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->flags     = intval($this->sanitize(array_shift($_rdata)));
        $this->protocol  = intval($this->sanitize(array_shift($_rdata)));
        $this->algorithm = \NetDNS2\ENUM\DNSSEC\Algorithm::set(intval($this->sanitize(array_shift($_rdata))));
        $this->key       = implode(' ', $_rdata);

        //
        // extract the flags
        //
        $this->zone   = ($this->flags & 0x0100) ? true : false;
        $this->sep    = ($this->flags & 0x0001) ? true : false;
        $this->revoke = ($this->flags & 0x0080) ? true : false;

        //
        // RFC 4034 - 2.1.2.  The Protocol Field
        //
        // The Protocol Field MUST have value 3, and the DNSKEY RR MUST be treated as invalid during signature verification
        // if it is found to be some value other than 3.
        //
        if ($this->protocol != 3)
        {
            throw new \NetDNS2\Exception('the DNSKEY protocol value must be 3.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

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
        // unpack the flags, protocol and algorithm
        //
        $val = unpack('nx/Cy/Cz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->flags, 'y' => $this->protocol, 'z' => $algorithm) = (array)$val;

        $this->algorithm = \NetDNS2\ENUM\DNSSEC\Algorithm::set($algorithm);

        //
        // extract the flags
        //
        $this->zone   = ($this->flags & 0x0100) ? true : false;
        $this->sep    = ($this->flags & 0x0001) ? true : false;
        $this->revoke = ($this->flags & 0x0080) ? true : false;

        //
        // RFC 4034 - 2.1.2.  The Protocol Field
        //
        // The Protocol Field MUST have value 3, and the DNSKEY RR MUST be treated as invalid during signature verification
        // if it is found to be some value other than 3.
        //
        if ($this->protocol != 3)
        {
            throw new \NetDNS2\Exception('the DNSKEY protocol value must be 3.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        //
        // get the key
        //
        $this->key = base64_encode(substr($this->rdata, 4));

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (strlen($this->key) == 0)
        {
            return '';
        }

        //
        // pack the flags
        //
        $this->flags = 0;
        $this->flags |= ($this->zone == true) ? 0x0100 : 0;
        $this->flags |= ($this->sep == true) ? 0x0001 : 0;
        $this->flags |= ($this->revoke == true) ? 0x0080 : 0;

        $data = pack('nCC', $this->flags, $this->protocol, $this->algorithm->value);

        $decode = base64_decode($this->key);
        if ($decode !== false)
        {
            $data .= $decode;
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
