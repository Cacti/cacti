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
 *  https://datatracker.ietf.org/doc/draft-ietf-dnsop-generalized-notify/09/
 *
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  | RRtype                        | Scheme        | Port
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *                  | Target ...  /
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-/
 *
 */
final class DSYNC extends \NetDNS2\RR
{
    /**
     * defined DSYNC schemes
     */
    public const DSYNC_SCHEME_NULL      = 0;
    public const DSYNC_SCHEME_NOTIFY    = 1;
                                                // 128-255 reserved for future use

    /**
     * scheme lookup tables
     *
     * @var array<int,string>
     */
    public static array $scheme_id_to_name = [

        self::DSYNC_SCHEME_NULL     => 'NULL',
        self::DSYNC_SCHEME_NOTIFY   => 'NOTIFY'
    ];

    /**
     * @var array<string,int>
     */
    public static array $scheme_name_to_id = [

        'NULL'      => self::DSYNC_SCHEME_NULL,
        'NOTIFY'    => self::DSYNC_SCHEME_NOTIFY
    ];

    /**
     * RR types supported by DSYNC
     *
     * @var array<int,\NetDNS2\ENUM\RR\Type>
     */
    public static array $supported_rr_types = [ \NetDNS2\ENUM\RR\Type::CDS, \NetDNS2\ENUM\RR\Type::CSYNC ];

    /**
     * The type of generalized NOTIFY that this DSYNC RR defines the desired target address for
     */
    protected \NetDNS2\ENUM\RR\Type $rrtype;

    /**
     * The mode used for contacting the desired notification address.
     */
    protected string $scheme;

    /**
     * The port on the target host of the notification service.
     */
    protected int $port;

    /**
     * The fully-qualified, uncompressed domain name of the target host providing the service of listening for generalized
     * notifications of the specified type.
     */
    protected \NetDNS2\Data\Domain $target;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->rrtype->label() . ' ' . $this->scheme . ' ' . $this->port . ' ' . $this->target . '.';
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        //
        // lookup and store the RR mnemonic
        //
        $this->rrtype = \NetDNS2\ENUM\RR\Type::set($this->sanitize(array_shift($_rdata), false));

        if (in_array($this->rrtype, self::$supported_rr_types) == false)
        {
            throw new \NetDNS2\Exception(sprintf('unsupported resource record type for DSYNC record: %s', $this->rrtype->label()), \NetDNS2\ENUM\Error::INT_INVALID_TYPE);
        }

        //
        // lookup and store the scheme mnemonic
        //
        $scheme = strtoupper($this->sanitize(array_shift($_rdata)));

        if (isset(self::$scheme_name_to_id[$scheme]) === true)
        {
            $this->scheme = $scheme;
        } else
        {
            throw new \NetDNS2\Exception(sprintf('unsupported scheme value for DSYNC record: %s', $scheme), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        $this->port   = intval($this->sanitize(array_shift($_rdata)));
        $this->target = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $this->sanitize(array_shift($_rdata)));

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

        $val = unpack('nx/Cy/nz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $rrtype, 'y' => $scheme, 'z' => $this->port) = (array)$val;
        $offset = $_packet->offset + 5;

        //
        // lookup the rrtype value
        //
        $this->rrtype = \NetDNS2\ENUM\RR\Type::set($rrtype);

        if (in_array($this->rrtype, self::$supported_rr_types) == false)
        {
            throw new \NetDNS2\Exception(sprintf('unsupported resource record type for DSYNC record: %s', $this->rrtype->label()), \NetDNS2\ENUM\Error::INT_INVALID_TYPE);
        }

        //
        // lookup the scheme value
        //
        if (isset(self::$scheme_id_to_name[$scheme]) === true)
        {
            $this->scheme = self::$scheme_id_to_name[$scheme];
        } else
        {
            throw new \NetDNS2\Exception(sprintf('unsupported scheme value for DSYNC record: %d', $scheme), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
        }

        $this->target = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $_packet, $offset);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $_packet->offset += 5;

        return pack('nCn', $this->rrtype->value, self::$scheme_name_to_id[$this->scheme], $this->port) . $this->target->encode();
    }
}
