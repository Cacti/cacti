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

namespace NetDNS2;

/**
 * manage the various EDNS options that can be passed to the resolver
 *
 */
final class EDNS
{
    /**
     * an internal list of \NetDNS2\RR\OPT objects, for passing additional EDNS options in a request.
     *
     * @var array<string,\NetDNS2\RR\OPT>
     */
    public array $opts = [];

    private function check(string $_type, bool $_enable): bool
    {
        if ( ($_enable == true) && (isset($this->opts[$_type]) === true) )
        {
            return true;

        } elseif ($_enable == false)
        {
            unset($this->opts[$_type]);
            return true;
        }

        return false;
    }

    /**
     * turn on DNSSEC checks
     */
    public function dnssec(bool $_enable): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT();

        $opt->do = 1;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 9824 - signal Compact Denial of Existence support (CO bit)
     */
    public function compact_ok(bool $_enable): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT();

        $opt->co = 1;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 9664 - EDNS(0) option to negotiate Leases on DNS Updates
     */
    public function update_lease(bool $_enable, int $_lease, int $_key_lease = 0): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\UL();

        $opt->lease     = $_lease;
        $opt->key_lease = $_key_lease;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 5001 - DNS Name Server Identifier (NSID) Option
     */
    public function nsid(bool $_enable): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\NSID();

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 6975 - used for DAU, DHU, and N3U
     *
     * @param array<int> $_alg_code
     */
    public function dau(bool $_enable, array $_alg_code = []): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\DAU();

        $opt->alg_code = $_alg_code;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 6975 - used for DAU, DHU, and N3U
     *
     * @param array<int> $_alg_code
     */
    public function dhu(bool $_enable, array $_alg_code = []): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\DHU();

        $opt->alg_code = $_alg_code;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 6975 - used for DAU, DHU, and N3U
     *
     * @param array<int> $_alg_code
     */
    public function n3u(bool $_enable, array $_alg_code = []): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\N3U();

        $opt->alg_code = $_alg_code;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 7871 - Client Subnet in DNS Queries
     */
    public function client_subnet(bool $_enable, string $_address): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\ECS();

        $opt->parse_subnet($_address);

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 7314 - Extension Mechanisms for DNS (EDNS) EXPIRE Option
     */
    public function expire(bool $_enable): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\EXPIRE();

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 7872 - Domain Name System (DNS) Cookies
     */
    public function cookie(bool $_enable, string $_client_cookie, string $_server_cookie = ''): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\COOKIE();

        $opt->client_cookie = $_client_cookie;
        $opt->server_cookie = $_server_cookie;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 7828 - The edns-tcp-keepalive EDNS Option
     */
    public function tcp_keepalive(bool $_enable, int $_timeout = 0): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\KEEPALIVE();

        $opt->timeout = $_timeout;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 7830 - The EDNS(0) Padding Option
     *
     * @param int $_length number of zero-padding bytes to add (0 = signal support only, no padding bytes)
     */
    public function padding(bool $_enable, int $_length = 0): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\PADDING();

        $opt->padding = str_repeat("\x00", $_length);

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 7901 - CHAIN Query Requests in DNS
     */
    public function chain(bool $_enable, string $_closest_trust_point): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\CHAIN();

        $opt->closest_trust_point = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $_closest_trust_point);

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 8145 - Signaling Trust Anchor Knowledge in DNS Security Extensions (DNSSEC)
     *
     * @param array<int> $_key_tag a list of key tag values to include
     */
    public function key_tag(bool $_enable, array $_key_tag): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\KEYTAG();

        $opt->key_tag = $_key_tag;

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 8914 - Extended DNS Errors
     */
    public function extended_error(bool $_enable): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\EDE();

        $opt->extra_text = new \NetDNS2\Data\Text('');

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 9567 - DNS Error Reporting
     */
    public function report_channel(bool $_enable, string $_agent_domain): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\RCHANNEL();

        $opt->agent_domain = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $_agent_domain);

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * RFC 9660 - The DNS Zone Version (ZONEVERSION) Option
     */
    public function zone_version(bool $_enable): void
    {
        if ($this->check(__FUNCTION__, $_enable) == true)
        {
            return;
        }

        $opt = new \NetDNS2\RR\OPT\ZONEVERSION();

        $this->opts[__FUNCTION__] = clone $opt;
    }

    /**
     * Merges all configured EDNS options into a single \NetDNS2\RR\OPT record as required by RFC 6891 §6.1.1
     * ("A DNS message carries at most one OPT RR in its additional data section").
     *
     * Flag-only options (DO, CO) are ORed into the merged record's flag fields.
     * Options with RDATA are concatenated into a single RDATA block.
     *
     * @param int $_udp_length the UDP payload size to advertise in the OPT class field
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function build(int $_udp_length): ?\NetDNS2\RR\OPT
    {
        if (count($this->opts) == 0)
        {
            return null;
        }

        $opt = new \NetDNS2\RR\OPT();

        $opt->udp_length = $_udp_length;

        $rdata = '';

        foreach($this->opts as $o)
        {
            if ($o->do)
            {
                $opt->do = 1;
            }
            if ($o->co)
            {
                $opt->co = 1;
            }

            $bytes = $o->packOption();
            if (strlen($bytes) > 0)
            {
                $rdata .= $bytes;
            }
        }

        if (strlen($rdata) > 0)
        {
            $opt->option_data = $rdata;
        }

        return $opt;
    }
}
