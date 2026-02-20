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
 * TKEY Resource Record - RFC 2930 section 2
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                   ALGORITHM                   /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   INCEPTION                   |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   EXPIRATION                  |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   MODE                        |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   ERROR                       |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   KEY SIZE                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                   KEY DATA                    /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   OTHER SIZE                  |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                   OTHER DATA                  /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class TKEY extends \NetDNS2\RR
{
    /*
     * algorithm name
     */
    protected \NetDNS2\Data\Domain $algorithm;

    /*
     * The inception time and expiration times are in number of seconds since the beginning of 1 January 1970 GMT
     * ignoring leap seconds
     */
    protected int $inception;
    protected int $expiration;

    /*
     * The mode field specifies the general scheme for key agreement or the purpose of the TKEY DNS message.
     */
    protected \NetDNS2\ENUM\TKEYMode $mode;

    /*
     * The error code field is an extended RCODE.
     */
    protected \NetDNS2\ENUM\RR\Code $error;

    /*
     * The key data size field is an unsigned 16 bit integer in network order which specifies the size of the key
     * exchange data field in octets.
     */
    protected int $key_size;
    protected string $key_data;

    /*
     * The Other Size and Other Data fields are not used in this specification but may be used in future extensions.
     */
    protected int $other_size;
    protected string $other_data;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->algorithm . '. ' . $this->mode->value;

        if ($this->key_size > 0)
        {
            $out .= ' ' . trim($this->key_data, '.') . '.';
        } else
        {
            $out .= ' .';
        }

        return $out;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->algorithm = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, array_shift($_rdata));
        $this->mode      = \NetDNS2\ENUM\TKEYMode::set(intval($this->sanitize(array_shift($_rdata))));
        $this->key_data  = $this->sanitize(array_shift($_rdata));

        //
        // the rest of the data is set manually
        //
        $this->inception  = time();
        $this->expiration = time() + 86400; // 1 day
        $this->error      = \NetDNS2\ENUM\RR\Code::NOERROR;
        $this->key_size   = strlen($this->key_data);
        $this->other_size = 0;
        $this->other_data = '';

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

        $offset = $_packet->offset;

        $this->algorithm = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $_packet->rdata, $offset);

        //
        // unpack inception, expiration, mode, error and key size
        //
        $val = unpack('Na/Nb/nc/nd/ne', $_packet->rdata, $offset);
        if ($val === false)
        {
            return false;
        }

        list('a' => $this->inception, 'b' => $this->expiration, 'c' => $mode, 'd' => $error, 'e' => $this->key_size) = (array)$val;
        $offset += 14;

        //
        // store the ENUM
        //
        $this->mode  = \NetDNS2\ENUM\TKEYMode::set($mode);
        $this->error = \NetDNS2\ENUM\RR\Code::set($error);

        //
        // if key_size > 0, then copy out the key
        //
        if ($this->key_size > 0)
        {
            $this->key_data = substr($_packet->rdata, $offset, $this->key_size);
            $offset += $this->key_size;
        }

        //
        // unpack the other length
        //
        $val = unpack('nx', $_packet->rdata, $offset);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->other_size) = (array)$val;
        $offset += 2;

        //
        // if other_size > 0, then copy out the data
        //
        if ($this->other_size > 0)
        {
            $this->other_data = substr($_packet->rdata, $offset, $this->other_size);
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->algorithm->length() == 0)
        {
            return '';
        }

        //
        // make sure the size values are correct
        //
        $this->key_size   = strlen($this->key_data);
        $this->other_size = strlen($this->other_data);

        //
        // pack in the inception, expiration, mode, error and key size
        //
        $data = $this->algorithm->encode($_packet->offset) . pack('NNnnn', $this->inception, $this->expiration, $this->mode->value, 0, $this->key_size);

        //
        // if the key_size > 0, then add the key
        //
        if ($this->key_size > 0)
        {
            $data .= $this->key_data;
        }

        //
        // pack in the other size
        //
        $data .= pack('n', $this->other_size);

        if ($this->other_size > 0)
        {
            $data .= $this->other_data;
        }

        $_packet->offset += 16 + $this->key_size + $this->other_size;

        return $data;
    }
}
