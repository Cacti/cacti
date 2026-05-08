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
 * HIP Resource Record - RFC5205 section 5
 *
 *   0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |  HIT length   | PK algorithm  |          PK length            |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |                                                               |
 *  ~                           HIT                                 ~
 *  |                                                               |
 *  +                     +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |                     |                                         |
 *  +-+-+-+-+-+-+-+-+-+-+-+                                         +
 *  |                           Public Key                          |
 *  ~                                                               ~
 *  |                                                               |
 *  +                               +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |                               |                               |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               +
 *  |                                                               |
 *  ~                       Rendezvous Servers                      ~
 *  |                                                               |
 *  +             +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |             |
 *  +-+-+-+-+-+-+-+
 *
 * @property int $hit_length
 * @property int $pk_algorithm
 * @property int $pk_length
 * @property string $hit
 * @property string $public_key
 * @property array<int,\NetDNS2\Data\Domain> $rendezvous_servers
 */
final class HIP extends \NetDNS2\RR
{
    /**
     * supported algorithms
     */
    public const ALGORITHM_NONE     = 0;
    public const ALGORITHM_DSA      = 1;
    public const ALGORITHM_RSA      = 2;

    /**
     * The length of the HIT field
     */
    protected int $hit_length;

    /**
     * the public key cryptographic algorithm
     */
    protected int $pk_algorithm;

    /**
     * the length of the public key field
     */
    protected int $pk_length;

    /**
     * The HIT is stored as a binary value in network byte order.
     */
    protected string $hit;

    /**
     * The public key
     */
    protected string $public_key;

    /**
     * a list of rendezvous servers
     *
     * @var array<int,\NetDNS2\Data\Domain>
     */
    protected array $rendezvous_servers = [];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        $out = $this->pk_algorithm . ' ' . $this->hit . ' ' . $this->public_key . ' ';

        foreach($this->rendezvous_servers as $index => $server)
        {
            $out .= $server . '. ';
        }

        return trim($out);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->pk_algorithm = intval($this->sanitize(array_shift($_rdata)));
        $this->hit          = strtoupper($this->sanitize(array_shift($_rdata)));
        $this->public_key   = array_shift($_rdata) ?? '';

        //
        // anything left on the array, must be one or more rendezevous servers. add them and strip off the trailing dot
        //
        foreach($_rdata as $data)
        {
            $this->rendezvous_servers[] = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $data);
        }

        //
        // base64 deocde the public key
        //
        $decode = base64_decode($this->public_key);
        if ($decode === false)
        {
            $decode = '';
        }

        //
        // store the lengths
        //
        $this->hit_length = strlen(pack('H*', $this->hit));
        $this->pk_length  = strlen($decode);

        //
        // check the algorithm and key
        //
        switch($this->pk_algorithm)
        {
            case self::ALGORITHM_NONE:
            {
                $this->public_key = '';
            }
            break;
            case self::ALGORITHM_DSA:
            case self::ALGORITHM_RSA:
            {
                // do nothing
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid algorithm value provided: %d', $this->pk_algorithm), \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM);
            }
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
        // unpack the algorithm and length values
        //
        $val = unpack('Cx/Cy/nz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->hit_length, 'y' => $this->pk_algorithm, 'z' => $this->pk_length) = (array)$val;
        $offset = 4;

        //
        // copy out the HIT value
        //
        $val = unpack('H*', substr($this->rdata, $offset, $this->hit_length));
        if ($val === false)
        {
            return false;
        }
        $this->hit = strtoupper(((array)$val)[1]);
        $offset += $this->hit_length;

        //
        // copy out the public key
        //
        $this->public_key = base64_encode(substr($this->rdata, $offset, $this->pk_length));
        $offset += $this->pk_length;

        //
        // copy out any possible rendezvous servers
        //
        while($offset < $this->rdlength)
        {
            $this->rendezvous_servers[] = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->rdata, $offset);
        }

        //
        // check the algorithm and key
        //
        switch($this->pk_algorithm)
        {
            case self::ALGORITHM_NONE:
            {
                $this->public_key = '';
            }
            break;
            case self::ALGORITHM_DSA:
            case self::ALGORITHM_RSA:
            {
                // do nothing
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid algorithm value provided: %d', $this->pk_algorithm), \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM);
            }
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (strlen($this->hit) == 0)
        {
            return '';
        }

        if ( ($this->pk_algorithm != self::ALGORITHM_NONE) && (strlen($this->public_key) == 0) )
        {
            return '';
        }

        //
        // check the algorithm and key
        //
        switch($this->pk_algorithm)
        {
            case self::ALGORITHM_NONE:
            {
                $this->public_key = '';
                $this->pk_length = 0;
            }
            break;
            case self::ALGORITHM_DSA:
            case self::ALGORITHM_RSA:
            {
                // do nothing
            }
            break;
        }

        //
        // pack the length, algorithm and HIT values
        //
        $data = pack('CCnH*', $this->hit_length, $this->pk_algorithm, $this->pk_length, $this->hit);

        //
        // add the public key
        //
        $decode = base64_decode($this->public_key);
        if ($decode !== false)
        {
            $data .= $decode;
        }

        //
        // add each rendezvous server
        //
        foreach($this->rendezvous_servers as $index => $server)
        {
            $data .= $server->encode();
        }

        //
        // add the offset
        //
        $_packet->offset += strlen($data);

        return $data;
    }
}
