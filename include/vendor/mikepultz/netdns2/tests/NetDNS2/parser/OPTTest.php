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

namespace NetDNS2\Tests;

/**
 * Wire round-trip tests for all OPT subclasses and EDNS setter methods.
 *
 * Each OPT round-trip test:
 *   1. Creates an OPT subclass with known field values.
 *   2. Encodes it into a DNS packet via Packet\Request::get().
 *   3. Decodes the packet via Packet\Response, recovering a base OPT.
 *   4. Calls generate_edns() to obtain the typed OPT subclass.
 *   5. Asserts that fields survived the binary round-trip unchanged.
 *
 */
class OPTTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Encode a single OPT subclass in the additional section of a dummy request
     * packet, then decode the response and return the typed OPT subclass.
     *
     * @throws \NetDNS2\Exception
     *
     */
    private function roundTrip(\NetDNS2\RR\OPT $_opt): \NetDNS2\RR\OPT
    {
        $req = new \NetDNS2\Packet\Request('example.com.', 'A', 'IN');

        $req->additional[]    = $_opt;
        $req->header->arcount = 1;

        $data = $req->get();
        $res  = new \NetDNS2\Packet\Response($data, strlen($data));

        $this->assertCount(1, $res->additional, 'OPTTest: exactly one additional record expected after round-trip');
        $this->assertInstanceOf(\NetDNS2\RR\OPT::class, $res->additional[0]);

        $dummy = new \NetDNS2\Packet();

        return $res->additional[0]->generate_edns($dummy);
    }

    // -------------------------------------------------------------------------
    // ECS (RFC 7871) — Client Subnet
    // -------------------------------------------------------------------------

    /**
     * ECS round-trip with an IPv4 subnet.
     *
     */
    public function testECSIPv4RoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\ECS();
        $opt->parse_subnet('192.168.1.0/24');

        /** @var \NetDNS2\RR\OPT\ECS $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\ECS::class, $parsed);
        $this->assertSame(1, $parsed->family);
        $this->assertSame(24, $parsed->source_prefix);
        $this->assertSame(0, $parsed->scope_prefix);
        $this->assertSame('192.168.1.0', (string)$parsed->address);
    }

    /**
     * ECS round-trip with an IPv6 subnet.
     *
     */
    public function testECSIPv6RoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\ECS();
        $opt->parse_subnet('2001:db8::/32');

        /** @var \NetDNS2\RR\OPT\ECS $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\ECS::class, $parsed);
        $this->assertSame(2, $parsed->family);
        $this->assertSame(32, $parsed->source_prefix);
        $this->assertSame(0, $parsed->scope_prefix);
        $this->assertStringContainsString('2001:db8', (string)$parsed->address);
    }

    // -------------------------------------------------------------------------
    // COOKIE (RFC 7873)
    // -------------------------------------------------------------------------

    /**
     * COOKIE round-trip with a client cookie only.
     *
     */
    public function testCOOKIEClientOnlyRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\COOKIE();

        $opt->client_cookie = 'aabbccdd11223344';
        $opt->server_cookie = '';

        /** @var \NetDNS2\RR\OPT\COOKIE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\COOKIE::class, $parsed);
        $this->assertSame('aabbccdd11223344', $parsed->client_cookie);
        $this->assertSame('', $parsed->server_cookie);
    }

    /**
     * COOKIE round-trip with both client and server cookies.
     *
     */
    public function testCOOKIEClientServerRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\COOKIE();

        $opt->client_cookie = 'aabbccdd11223344';
        $opt->server_cookie = 'deadbeefcafe0102';

        /** @var \NetDNS2\RR\OPT\COOKIE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\COOKIE::class, $parsed);
        $this->assertSame('aabbccdd11223344', $parsed->client_cookie);
        $this->assertSame('deadbeefcafe0102', $parsed->server_cookie);
    }

    // -------------------------------------------------------------------------
    // EDE (RFC 8914) — Extended DNS Errors
    // -------------------------------------------------------------------------

    /**
     * EDE round-trip with info_code only (no extra text).
     *
     */
    public function testEDENoTextRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\EDE();

        $opt->info_code  = \NetDNS2\RR\OPT\EDE::BLOCKED;
        $opt->extra_text = new \NetDNS2\Data\Text('');

        /** @var \NetDNS2\RR\OPT\EDE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\EDE::class, $parsed);
        $this->assertSame(\NetDNS2\RR\OPT\EDE::BLOCKED, $parsed->info_code);
        $this->assertSame('', (string)$parsed->extra_text);
    }

    /**
     * EDE round-trip with info_code and extra text.
     *
     */
    public function testEDEWithTextRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\EDE();

        $opt->info_code  = \NetDNS2\RR\OPT\EDE::DNSSEC_BOGUS;
        $opt->extra_text = new \NetDNS2\Data\Text('DNSSEC bogus');

        /** @var \NetDNS2\RR\OPT\EDE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\EDE::class, $parsed);
        $this->assertSame(\NetDNS2\RR\OPT\EDE::DNSSEC_BOGUS, $parsed->info_code);
        $this->assertSame('DNSSEC bogus', (string)$parsed->extra_text);
    }

    // -------------------------------------------------------------------------
    // CHAIN (RFC 7901)
    // -------------------------------------------------------------------------

    /**
     * CHAIN round-trip with a domain name closest trust point.
     *
     */
    public function testCHAINRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\CHAIN();

        $opt->closest_trust_point = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, 'example.com');

        /** @var \NetDNS2\RR\OPT\CHAIN $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\CHAIN::class, $parsed);
        $this->assertSame('example.com', (string)$parsed->closest_trust_point);
    }

    // -------------------------------------------------------------------------
    // KEEPALIVE (RFC 7828)
    // -------------------------------------------------------------------------

    /**
     * KEEPALIVE round-trip with timeout=0 (empty option data, signal only).
     *
     */
    public function testKEEPALIVEZeroRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\KEEPALIVE();

        $opt->timeout = 0;

        /** @var \NetDNS2\RR\OPT\KEEPALIVE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\KEEPALIVE::class, $parsed);
        $this->assertSame(0, $parsed->timeout);
    }

    /**
     * KEEPALIVE round-trip with a non-zero timeout value.
     *
     */
    public function testKEEPALIVENonZeroRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\KEEPALIVE();

        $opt->timeout = 500;

        /** @var \NetDNS2\RR\OPT\KEEPALIVE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\KEEPALIVE::class, $parsed);
        $this->assertSame(500, $parsed->timeout);
    }

    // -------------------------------------------------------------------------
    // KEYTAG (RFC 8145)
    // -------------------------------------------------------------------------

    /**
     * KEYTAG round-trip with a list of key tag values.
     *
     */
    public function testKEYTAGRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\KEYTAG();

        $opt->key_tag = [20326, 38696];

        /** @var \NetDNS2\RR\OPT\KEYTAG $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\KEYTAG::class, $parsed);
        $this->assertSame([20326, 38696], $parsed->key_tag);
    }

    // -------------------------------------------------------------------------
    // NSID (RFC 5001)
    // -------------------------------------------------------------------------

    /**
     * NSID round-trip — the option carries no data in a request.
     *
     */
    public function testNSIDRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\NSID();

        /** @var \NetDNS2\RR\OPT\NSID $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\NSID::class, $parsed);
        $this->assertSame('NSID', $parsed->option_code->label());
    }

    // -------------------------------------------------------------------------
    // PADDING (RFC 7830)
    // -------------------------------------------------------------------------

    /**
     * PADDING round-trip with no padding bytes (signal only).
     *
     */
    public function testPADDINGEmptyRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\PADDING();

        $opt->padding = '';

        /** @var \NetDNS2\RR\OPT\PADDING $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\PADDING::class, $parsed);
        $this->assertSame(0, $parsed->option_length);
    }

    /**
     * PADDING round-trip with a non-empty padding block.
     *
     */
    public function testPADDINGNonEmptyRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\PADDING();

        $opt->padding = str_repeat("\x00", 8);

        /** @var \NetDNS2\RR\OPT\PADDING $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\PADDING::class, $parsed);
        $this->assertSame(8, $parsed->option_length);
        $this->assertSame(str_repeat("\x00", 8), $parsed->padding);
    }

    // -------------------------------------------------------------------------
    // DAU / DHU / N3U (RFC 6975)
    // -------------------------------------------------------------------------

    /**
     * DAU round-trip with a list of algorithm codes.
     *
     */
    public function testDAURoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\DAU();

        $opt->alg_code = [8, 13, 14];

        /** @var \NetDNS2\RR\OPT\DAU $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\DAU::class, $parsed);
        $this->assertSame([8, 13, 14], $parsed->alg_code);
    }

    /**
     * DHU round-trip with a list of digest hash algorithm codes.
     *
     */
    public function testDHURoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\DHU();

        $opt->alg_code = [1, 2];

        /** @var \NetDNS2\RR\OPT\DHU $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\DHU::class, $parsed);
        $this->assertSame([1, 2], $parsed->alg_code);
    }

    /**
     * N3U round-trip with a list of NSEC3 hash algorithm codes.
     *
     */
    public function testN3URoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\N3U();

        $opt->alg_code = [1];

        /** @var \NetDNS2\RR\OPT\N3U $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\N3U::class, $parsed);
        $this->assertSame([1], $parsed->alg_code);
    }

    // -------------------------------------------------------------------------
    // EXPIRE (RFC 7314)
    // -------------------------------------------------------------------------

    /**
     * EXPIRE round-trip with expire=0 (empty option data, request mode).
     *
     */
    public function testEXPIREZeroRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\EXPIRE();

        $opt->expire = 0;

        /** @var \NetDNS2\RR\OPT\EXPIRE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\EXPIRE::class, $parsed);
        $this->assertSame(0, $parsed->expire);
    }

    /**
     * EXPIRE round-trip with a non-zero expire value.
     *
     */
    public function testEXPIRENonZeroRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\EXPIRE();

        $opt->expire = 3600;

        /** @var \NetDNS2\RR\OPT\EXPIRE $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\EXPIRE::class, $parsed);
        $this->assertSame(3600, $parsed->expire);
    }

    // -------------------------------------------------------------------------
    // UL (RFC 9664) — Update Lease
    // -------------------------------------------------------------------------

    /**
     * UL round-trip with a lease-only value (no key_lease).
     *
     */
    public function testULLeaseOnlyRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\UL();

        $opt->lease     = 3600;
        $opt->key_lease = 0;

        /** @var \NetDNS2\RR\OPT\UL $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\UL::class, $parsed);
        $this->assertSame(3600, $parsed->lease);
        $this->assertSame(0, $parsed->key_lease);
    }

    /**
     * UL round-trip with both lease and key_lease values.
     *
     */
    public function testULLeaseAndKeyLeaseRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\UL();

        $opt->lease     = 3600;
        $opt->key_lease = 600;

        /** @var \NetDNS2\RR\OPT\UL $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\UL::class, $parsed);
        $this->assertSame(3600, $parsed->lease);
        $this->assertSame(600, $parsed->key_lease);
    }

    // -------------------------------------------------------------------------
    // ZONEVERSION (RFC 9660)
    // -------------------------------------------------------------------------

    /**
     * ZONEVERSION round-trip in request mode (empty option data).
     *
     */
    public function testZONEVERSIONRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\ZONEVERSION();

        /** @var \NetDNS2\RR\OPT\ZONEVERSION $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\ZONEVERSION::class, $parsed);
        $this->assertSame('ZONEVERSION', $parsed->option_code->label());
        $this->assertSame(0, $parsed->label_count);
    }

    // -------------------------------------------------------------------------
    // RCHANNEL (RFC 9567) — DNS Error Reporting
    // -------------------------------------------------------------------------

    /**
     * RCHANNEL round-trip with an agent domain.
     *
     */
    public function testRCHANNELRoundTrip(): void
    {
        $opt = new \NetDNS2\RR\OPT\RCHANNEL();

        $opt->agent_domain = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, 'errors.example.com');

        /** @var \NetDNS2\RR\OPT\RCHANNEL $parsed */
        $parsed = $this->roundTrip($opt);

        $this->assertInstanceOf(\NetDNS2\RR\OPT\RCHANNEL::class, $parsed);
        $this->assertSame('errors.example.com', (string)$parsed->agent_domain);
    }

    // =========================================================================
    // EDNS setter tests
    // =========================================================================

    /**
     * dnssec() setter: sets the DO bit in the merged OPT record.
     *
     */
    public function testEDNSDnssecSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->dnssec(true);

        $opt = $edns->build(4096);

        $this->assertNotNull($opt);
        $this->assertSame(1, $opt->do);
        $this->assertSame(0, $opt->co);
    }

    /**
     * compact_ok() setter: sets the CO bit in the merged OPT record.
     *
     */
    public function testEDNSCompactOkSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->compact_ok(true);

        $opt = $edns->build(4096);

        $this->assertNotNull($opt);
        $this->assertSame(1, $opt->co);
    }

    /**
     * nsid() setter: adds an NSID option to the EDNS opts list.
     *
     */
    public function testEDNSNsidSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->nsid(true);

        $this->assertArrayHasKey('nsid', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\NSID::class, $edns->opts['nsid']);
    }

    /**
     * dau() setter: adds a DAU option with the specified algorithm codes.
     *
     */
    public function testEDNSDauSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->dau(true, [8, 13, 14]);

        $this->assertArrayHasKey('dau', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\DAU::class, $edns->opts['dau']);
        $this->assertSame([8, 13, 14], $edns->opts['dau']->alg_code);
    }

    /**
     * dhu() setter: adds a DHU option with the specified hash algorithm codes.
     *
     */
    public function testEDNSDhuSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->dhu(true, [1, 2]);

        $this->assertArrayHasKey('dhu', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\DHU::class, $edns->opts['dhu']);
        $this->assertSame([1, 2], $edns->opts['dhu']->alg_code);
    }

    /**
     * n3u() setter: adds an N3U option with the specified NSEC3 hash algorithm codes.
     *
     */
    public function testEDNSN3uSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->n3u(true, [1]);

        $this->assertArrayHasKey('n3u', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\N3U::class, $edns->opts['n3u']);
        $this->assertSame([1], $edns->opts['n3u']->alg_code);
    }

    /**
     * client_subnet() setter: adds an ECS option parsed from the given subnet.
     *
     */
    public function testEDNSClientSubnetSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->client_subnet(true, '10.0.0.0/8');

        $this->assertArrayHasKey('client_subnet', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\ECS::class, $edns->opts['client_subnet']);
        $this->assertSame(1, $edns->opts['client_subnet']->family);
        $this->assertSame(8, $edns->opts['client_subnet']->source_prefix);
    }

    /**
     * expire() setter: adds an EXPIRE option.
     *
     */
    public function testEDNSExpireSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->expire(true);

        $this->assertArrayHasKey('expire', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\EXPIRE::class, $edns->opts['expire']);
    }

    /**
     * cookie() setter: adds a COOKIE option with the specified cookies.
     *
     */
    public function testEDNSCookieSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->cookie(true, 'aabbccdd11223344');

        $this->assertArrayHasKey('cookie', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\COOKIE::class, $edns->opts['cookie']);
        $this->assertSame('aabbccdd11223344', $edns->opts['cookie']->client_cookie);
    }

    /**
     * tcp_keepalive() setter: adds a KEEPALIVE option with the specified timeout.
     *
     */
    public function testEDNSTcpKeepaliveSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->tcp_keepalive(true, 300);

        $this->assertArrayHasKey('tcp_keepalive', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\KEEPALIVE::class, $edns->opts['tcp_keepalive']);
        $this->assertSame(300, $edns->opts['tcp_keepalive']->timeout);
    }

    /**
     * padding() setter: adds a PADDING option with the specified length.
     *
     */
    public function testEDNSPaddingSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->padding(true, 16);

        $this->assertArrayHasKey('padding', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\PADDING::class, $edns->opts['padding']);
        $this->assertSame(16, strlen($edns->opts['padding']->padding));
    }

    /**
     * chain() setter: adds a CHAIN option with the specified closest trust point.
     *
     */
    public function testEDNSChainSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->chain(true, 'example.com');

        $this->assertArrayHasKey('chain', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\CHAIN::class, $edns->opts['chain']);
        $this->assertSame('example.com', (string)$edns->opts['chain']->closest_trust_point);
    }

    /**
     * key_tag() setter: adds a KEYTAG option with the specified key tags.
     *
     */
    public function testEDNSKeyTagSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->key_tag(true, [20326, 38696]);

        $this->assertArrayHasKey('key_tag', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\KEYTAG::class, $edns->opts['key_tag']);
        $this->assertSame([20326, 38696], $edns->opts['key_tag']->key_tag);
    }

    /**
     * extended_error() setter: adds an EDE option with an empty extra text.
     *
     */
    public function testEDNSExtendedErrorSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->extended_error(true);

        $this->assertArrayHasKey('extended_error', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\EDE::class, $edns->opts['extended_error']);
    }

    /**
     * report_channel() setter: adds an RCHANNEL option with the specified agent domain.
     *
     */
    public function testEDNSReportChannelSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->report_channel(true, 'errors.example.com');

        $this->assertArrayHasKey('report_channel', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\RCHANNEL::class, $edns->opts['report_channel']);
        $this->assertSame('errors.example.com', (string)$edns->opts['report_channel']->agent_domain);
    }

    /**
     * zone_version() setter: adds a ZONEVERSION option.
     *
     */
    public function testEDNSZoneVersionSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->zone_version(true);

        $this->assertArrayHasKey('zone_version', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\ZONEVERSION::class, $edns->opts['zone_version']);
    }

    /**
     * update_lease() setter: adds a UL option with the specified lease values.
     *
     */
    public function testEDNSUpdateLeaseSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->update_lease(true, 3600, 600);

        $this->assertArrayHasKey('update_lease', $edns->opts);
        $this->assertInstanceOf(\NetDNS2\RR\OPT\UL::class, $edns->opts['update_lease']);
        $this->assertSame(3600, $edns->opts['update_lease']->lease);
        $this->assertSame(600, $edns->opts['update_lease']->key_lease);
    }

    /**
     * Calling a setter twice while enabled keeps the first value (idempotent read).
     *
     * The check() method returns true early when the key already exists.
     *
     */
    public function testEDNSSetterIdempotency(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->dau(true, [8, 13]);
        $edns->dau(true, [5]);   // second call must be a no-op

        /** @var \NetDNS2\RR\OPT\DAU $dau_opt */
        $dau_opt = $edns->opts['dau'];

        $this->assertSame([8, 13], $dau_opt->alg_code, 'second setter call must not overwrite the first value');
    }

    /**
     * Calling a setter with $_enable=false removes the option from the list.
     *
     */
    public function testEDNSDisableSetter(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->dau(true, [8]);
        $edns->dau(false);

        $this->assertArrayNotHasKey('dau', $edns->opts);

        //
        // with no opts, build() must return null
        //
        $this->assertNull($edns->build(4096));
    }

    /**
     * build() returns null when no EDNS options have been configured.
     *
     */
    public function testEDNSBuildReturnsNullWhenEmpty(): void
    {
        $edns = new \NetDNS2\EDNS();

        $this->assertNull($edns->build(4096));
    }

    /**
     * build() propagates both DO and CO flags when multiple flag-only options are combined.
     *
     */
    public function testEDNSBuildMergesBothFlags(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->dnssec(true);
        $edns->compact_ok(true);

        $opt = $edns->build(4096);

        $this->assertNotNull($opt);
        $this->assertSame(1, $opt->do);
        $this->assertSame(1, $opt->co);
    }

    /**
     * build() respects the udp_length argument passed to it.
     *
     */
    public function testEDNSBuildRespectsUdpLength(): void
    {
        $edns = new \NetDNS2\EDNS();
        $edns->dnssec(true);

        $opt = $edns->build(1232);

        $this->assertNotNull($opt);
        $this->assertSame(1232, $opt->udp_length);
    }
}
