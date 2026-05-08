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
 * NSEC3 Resource Record - RFC5155 section 3.2
 *
 *   0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |   Hash Alg.   |     Flags     |          Iterations           |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |  Salt Length  |                     Salt                      /
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |  Hash Length  |             Next Hashed Owner Name            /
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  /                         Type Bit Maps                         /
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 * @property int $algorithm
 * @property int $flags
 * @property int $iterations
 * @property int $salt_length
 * @property string $salt
 * @property int $hash_length
 * @property string $hashed_owner_name
 * @property array<int,string> $type_bit_maps
 */
final class NSEC3 extends \NetDNS2\RR
{
    /**
     * Algorithm to use
     *
     * TODO: NSEC3 uses a limit set of the DNSSEC algorithms, per RFC 5514 section 11
     */
    protected int $algorithm;

    /**
     * flags
     */
    protected int $flags;

    /**
     *  defines the number of additional times the hash is performed.
     */
    protected int $iterations;

    /**
     * the length of the salt- not displayed
     */
    protected int $salt_length;

    /**
     * the salt
     */
    protected string $salt;

    /**
     * the length of the hash value
     */
    protected int $hash_length;

    /**
     * the hashed value of the owner name
     */
    protected string $hashed_owner_name;

    /**
     * array of RR type names
     *
     * @var array<int,string>
     */
    protected array $type_bit_maps = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->algorithm . ' ' . $this->flags . ' ' . $this->iterations . ' ';

        //
        // per RFC5155, the salt_length value isn't displayed, and if the salt is empty, the salt is displayed as '-'
        //
        if ($this->salt_length > 0)
        {
            $out .= $this->salt;
        } else
        {
            $out .= '-';
        }

        $out .= ' ' . $this->hashed_owner_name . ' ' . implode(' ', $this->type_bit_maps);

        return $out;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->algorithm  = intval($this->sanitize(array_shift($_rdata)));
        $this->flags      = intval($this->sanitize(array_shift($_rdata)));
        $this->iterations = intval($this->sanitize(array_shift($_rdata)));

        //
        // an empty salt is represented as '-' per RFC5155 section 3.3
        //
        $salt = $this->sanitize(array_shift($_rdata));

        if ($salt == '-')
        {
            $this->salt_length = 0;
            $this->salt = '';
        } else
        {
            $this->salt_length = strlen(pack('H*', $salt));
            $this->salt = strtoupper($salt);
        }

        $this->hashed_owner_name = $this->sanitize(array_shift($_rdata));

        //
        // base64 decode the hash
        //
        $decode = base64_decode($this->hashed_owner_name);
        if ($decode === false)
        {
            $decode = '';
        }

        $this->hash_length   = strlen($decode);

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
        // unpack the first values
        //
        $val = unpack('Cw/Cx/ny/Cz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('w' => $this->algorithm, 'x' => $this->flags, 'y' => $this->iterations, 'z' => $this->salt_length) = (array)$val;

        $offset = 5;

        if ($this->salt_length > 0)
        {
            $val = unpack('H*', substr($this->rdata, $offset, $this->salt_length));
            if ($val === false)
            {
                return false;
            }

            $this->salt = strtoupper(((array)$val)[1]);
            $offset += $this->salt_length;
        }

        //
        // unpack the hash length
        //
        $val = unpack('Cx', $this->rdata, $offset);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->hash_length) = (array)$val;
        $offset++;

        if ($this->hash_length > 0)
        {
            $this->hashed_owner_name = base64_encode(substr($this->rdata, $offset, $this->hash_length));
            $offset += $this->hash_length;
        }

        //
        // parse out the RR bitmap
        //
        $this->type_bit_maps = \NetDNS2\BitMap::bitMapToArray(substr($this->rdata, $offset));

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // pull the salt and build the length
        //
        $salt = pack('H*', $this->salt);
        $this->salt_length = strlen($salt);

        //
        // pack the algorithm, flags, iterations and salt length
        //
        $data = pack('CCnC', $this->algorithm, $this->flags, $this->iterations, $this->salt_length);
        $data .= $salt;

        //
        // add the hash length and hash
        //
        $data .= chr($this->hash_length);
        if ($this->hash_length > 0)
        {
            $decode = base64_decode($this->hashed_owner_name);
            if ($decode !== false)
            {
                $data .= $decode;
            }
        }

        //
        // conver the array of RR names to a type bitmap
        //
        $data .= \NetDNS2\BitMap::arrayToBitMap($this->type_bit_maps);

        $_packet->offset += strlen($data);

        return $data;
    }
}
