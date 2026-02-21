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

namespace NetDNS2\RR\OPT;

/**
 * RFC 8914 - Extended DNS Errors
 *
 *     0   1   2   3   4   5   6   7   8   9   0   1   2   3   4   5
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 * 0: |                            OPTION-CODE                        |
 *   +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 * 2: |                           OPTION-LENGTH                       |
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 * 4: | INFO-CODE                                                     |
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 * 6: / EXTRA-TEXT ...                                                /
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *
 */
final class EDE extends \NetDNS2\RR\OPT
{
    public const OTHER_ERROR                        = 0;   // [RFC8914 Section 4.1]
    public const UNSUPPORTED_DNSKEY_ALGORITHM       = 1;   // [RFC8914 Section 4.2]
    public const UNSUPPORTED_DS_DIGEST_TYPE         = 2;   // [RFC8914 Section 4.3]
    public const STALE_ANSWER                       = 3;   // [RFC8914 Section 4.4][RFC8767]
    public const FORGED_ANSWER                      = 4;   // [RFC8914 Section 4.5]
    public const DNSSEC_INDETERMINATE               = 5;   // [RFC8914 Section 4.6]
    public const DNSSEC_BOGUS                       = 6;   // [RFC8914 Section 4.7]
    public const SIGNATURE_EXPIRED                  = 7;   // [RFC8914 Section 4.8]
    public const SIGNATURE_NOT_YET_VALID            = 8;   // [RFC8914 Section 4.9]
    public const DNSKEY_MISSING                     = 9;   // [RFC8914 Section 4.10]
    public const RRSIGS_MISSING                     = 10;  // [RFC8914 Section 4.11]
    public const NO_ZONE_KEY_BIT_SET                = 11;  // [RFC8914 Section 4.12]
    public const NSEC_MISSING                       = 12;  // [RFC8914 Section 4.13]
    public const CACHED_ERROR                       = 13;  // [RFC8914 Section 4.14]
    public const NOT_READY                          = 14;  // [RFC8914 Section 4.15]
    public const BLOCKED                            = 15;  // [RFC8914 Section 4.16]
    public const CENSORED                           = 16;  // [RFC8914 Section 4.17]
    public const FILTERED                           = 17;  // [RFC8914 Section 4.18]
    public const PROHIBITED                         = 18;  // [RFC8914 Section 4.19]
    public const STALE_NXDOMAIN_ANSWER              = 19;  // [RFC8914 Section 4.20]
    public const NOT_AUTHORITATIVE                  = 20;  // [RFC8914 Section 4.21]
    public const NOT_SUPPORTED                      = 21;  // [RFC8914 Section 4.22]
    public const NO_REACHABLE_AUTHORITY             = 22;  // [RFC8914 Section 4.23]
    public const NETWORK_ERROR                      = 23;  // [RFC8914 Section 4.24]
    public const INVALID_DATA                       = 24;  // [RFC8914 Section 4.25]
    public const SIGNATURE_EXPIRED_BEFORE_VALID     = 25;  // [https://github.com/NLnetLabs/unbound/pull/604#discussion_r802678343][Willem_Toorop]
    public const TOO_EARLY                          = 26;  // [RFC9250]
    public const UNSUPPORTED_NSEC3_ITERATIONS_VALUE = 27;  // [RFC9276]
    public const UNABLE_TO_CONFORM_TO_POLICY        = 28;  // [draft-homburg-dnsop-codcp-00]
    public const SYNTHESIZED                        = 29;  // [https://github.com/PowerDNS/pdns/pull/12334][Otto_Moerbeek]
    public const INVALID_QUERY_TYPE                 = 30;  // [RFC-ietf-dnsop-compact-denial-of-existence-07]

    /**
     * extended error code
     */
    protected int $info_code = 0;

    /**
     * extended error message
     */
    protected \NetDNS2\Data\Text $extra_text;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->option_code->label() . ' ' . $this->option_length . ' ' . $this->info_code . ' ' . $this->extra_text;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        if ($this->option_length == 0)
        {
            return true;
        }

        $val = unpack('nx', $this->option_data);
        if ($val == false)
        {
            return false;
        }

        list('x' => $this->info_code) = (array)$val;

        if ($this->option_length > 2)
        {
            $this->extra_text = new \NetDNS2\Data\Text(substr($this->option_data, 2));
        } else
        {
            $this->extra_text = new \NetDNS2\Data\Text('');
        }

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $this->option_data   = pack('na*', $this->info_code, $this->extra_text->value());
        $this->option_length = strlen($this->option_data);

        //
        // build the parent OPT data
        //
        return parent::rrGet($_packet);
    }
}
