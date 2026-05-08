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
 * DHCID Resource Record - RFC4701 section 3.1
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                  ID Type Code                 |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |       Digest Type     |                       /
 *    +--+--+--+--+--+--+--+--+                       /
 *    /                                               /
 *    /                    Digest                     /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property int $id_type
 * @property int $digest_type
 * @property string $digest
 */
final class DHCID extends \NetDNS2\RR
{
    /**
     * Identifier type
     */
    protected int $id_type;

    /**
     * Digest Type
     */
    protected int $digest_type;

    /**
     * The digest
     */
    protected string $digest;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = pack('nC', $this->id_type, $this->digest_type);

        //
        // decode and validate
        //
        $decode = base64_decode($this->digest);
        if ($decode !== false)
        {
            $out .= $decode;
        }

        //
        // the output display is one base64 encoded string
        //
        return base64_encode($out);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $data = base64_decode(array_shift($_rdata) ?? '');

        if ( ($data !== false) && (strlen($data) > 0) )
        {
            //
            // unpack the id type and digest type
            //
            $val = unpack('nx/Cy', $data);
            if ($val === false)
            {
                return false;
            }

            list('x' => $this->id_type, 'y' => $this->digest_type) = (array)$val;

            //
            // copy out the digest
            //
            $this->digest = base64_encode(substr($data, 3, strlen($data) - 3));

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

        //
        // unpack the id type and digest type
        //
        $val = unpack('nx/Cy', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->id_type, 'y' => $this->digest_type) = (array)$val;

        //
        // copy out the digest
        //
        $this->digest = base64_encode(substr($this->rdata, 3, $this->rdlength - 3));

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

        $data = pack('nC', $this->id_type, $this->digest_type);

        $decode = base64_decode($this->digest);
        if ($decode !== false)
        {
            $data .= $decode;
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
