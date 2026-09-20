<?php

/**
 * DNS Library for handling lookups and updates.
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   Net_DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 1.0.0
 *
 */

require_once 'Net/DNS2.php';

/**
 * Test class to test the parsing code
 *
 */
class Tests_Net_DNS2_ParserTest extends PHPUnit\Framework\TestCase
{
    /**
     * function to test the TSIG logic
     *
     * @return void
     * @access public
     *
     */
    public function testTSIG()
    {
        //
        // create a new packet
        //
        $request = new Net_DNS2_Packet_Request('example.com', 'SOA', 'IN');

        //
        // add a A record to the authority section, like an update request
        //
        $request->authority[] = Net_DNS2_RR::fromString('test.example.com A 10.10.10.10');
        $request->header->nscount = 1;

        //
        // add the TSIG as additional
        //
        $request->additional[] = Net_DNS2_RR::fromString('mykey TSIG Zm9vYmFy');
        $request->header->arcount = 1;

        $line = $request->additional[0]->name . '. ' . $request->additional[0]->ttl . ' ' .
        $request->additional[0]->class . ' ' . $request->additional[0]->type . ' ' .
        $request->additional[0]->algorithm . '. ' . $request->additional[0]->time_signed  . ' '.
        $request->additional[0]->fudge;

        //
        // get the binary packet data
        //
        $data = $request->get();

        //
        // parse the binary
        //
        $response = new Net_DNS2_Packet_Response($data, strlen($data));

        //
        // the answer data in the response, should match our initial line exactly
        //
        $this->assertSame($line, substr($response->additional[0]->__toString(), 0, 58));
    }

    /**
     * function to test parsing the individual RR's
     *
     * @return void
     * @access public
     *
     */
    public function testParser()
    {
        $rrs = [

            'AAAA'          => 'example.com. 300 IN AAAA 1080:0:0:0:8:800:200c:417a',
            'AFSDB'         => 'example.com. 300 IN AFSDB 3 afsdb.example.com.',
            'AMTRELAY'      => [
                                    'example.com. 300 IN AMTRELAY 10 0 0 .',
                                    'example.com. 300 IN AMTRELAY 10 0 1 203.0.113.15',
                                    'example.com. 300 IN AMTRELAY 10 0 2 2600:1f16:17c:3950:47ac:cb79:62ba:702e',
                                    'example.com. 300 IN AMTRELAY 10 0 3 test.google.com.'
                                ],
            'A'             => 'example.com. 300 IN A 172.168.0.50',
            'APL'           => 'example.com. 300 IN APL 1:224.0.0.0/4 2:a0:0:0:0:0:0:0:0/8 !1:192.168.38.0/28',
            'ATMA'          => 'example.com. 300 IN ATMA 39246f00e7c9c0312000100100001234567800',
            'AVC'           => 'example.com. 300 IN AVC "first record" "another records" "a third"',
            'CAA'           => 'example.com. 300 IN CAA 0 issue "ca.example.net; policy=ev"',
            'CDNSKEY'       => 'example.com. 300 IN CDNSKEY 256 3 7 AwEAAYCXh/ZABi8kiJIDXYmyUlHzC0CHeBzqcpyZAIjC7dK1wkRYVcUvIlpTOpnOVVfcC3Py9Ui/x45qKb0LytvK7WYAe3WyOOwk5klwIqRC/0p4luafbd2yhRMF7quOBVqYrLoHwv8i9LrV+r8dhB7rXv/lkTSI6mEZsg5rDfee8Yy1',
            'CDS'           => 'example.com. 300 IN CDS 21366 7 2 96eeb2ffd9b00cd4694e78278b5efdab0a80446567b69f634da078f0d90f01ba',
            'CERT'          => 'example.com. 300 IN CERT 3 0 0 TUlJQ1hnSUJBQUtCZ1FDcXlqbzNFMTU0dFU1Um43ajlKTFZsOGIwcUlCSVpGWENFelZvanVJT1BsMTM0by9zcHkxSE1hQytiUGh3Wk1UYVd4QlJpZHBFbUprNlEwNFJNTXdqdkFyLzFKWjhnWThtTzdCdTh1RUROVkNWeG5rQkUzMHhDSjhHRTNzL3EyN2VWSXBCUGFtU1lkNDVKZjNIeVBRRE4yaU45RjVHdGlIa2E2OXNhcmtKUnJ3SURBUUFCQW9HQkFJaUtDQ1NEM2FFUEFjQUx1MjdWN0JmR1BYN3lDTVg0OSsyVDVwNXNJdkduQjcrQ0NZZ09QaVQybmlpMGJPNVBBOTlnZnhPQXl1WCs5Z3llclVQbUFSc1ViUzcvUndkNGorRUlOVW1DanJSK2R6dGVXT0syeGxHamFOdGNPZU5jMkVtelQyMFRsekxVeUxTWGpzMzVlU2NQK0loeVptM2xJd21vbWtNb2d1QkFrRUE0a1FsOVBxaTJ2MVBDeGJCelU4Nnphblo2b0hsV0IzMUh4MllCNmFLYXhjNkVOZHhVejFzNjU2VncrRDhSVGpoSllyeDdMVkxzZDBRaVZJM0liSjVvUUpCQU1FN3k0aHg0SCtnQU40MEdrYjNjTFZGNHNpSEZrNnA2QVZRdlpzREwvVnh3bVlOdE4rM0txT3NVcG11WXZ3a3h0ajhIQnZtckxUYStXb3NmRDQwS1U4Q1FRQ1dvNmhob1R3cmI5bmdHQmFQQ2VDc2JCaVkrRUlvbUVsSm5mcEpuYWNxQlJ5emVid0pIeXdVOGsvalNUYXJIMk5HQzJ0bG5JMzRyS1VGeDZiTTJIWUJBa0VBbXBYSWZPNkZKL1NMM1RlWGNnQ1A5U1RraVlHd2NkdnhGeGVCcDlvRDZ2cElCN2FkWlgrMko5dzY5R0VUSlI0U3loSGVOdC95ZUhqWm9YdlhKVGc3ZHdKQVpEamxwL25wNEFZV3JYaGFrMVAvNGZlaDVNSU5WVHNXQkhTNlRZNW0xRmZMUEpybklHNW1FSHNidWkvdnhuQ1JmRUR4ZlU1V1E0cS9HUkZuaVl3SHB3PT0=',
            'CNAME'         => 'example.com. 300 IN CNAME www.example.com.',
            'CSYNC'         => 'example.com. 300 IN CSYNC 1278700841 3 A NS AAAA',
            'DHCID'         => 'example.com. 300 IN DHCID AAIBY2/AuCccgoJbsaxcQc9TUapptP69lOjxfNuVAA2kjEA=',
            'DLV'           => 'example.com. 300 IN DLV 21366 7 2 96eeb2ffd9b00cd4694e78278b5efdab0a80446567b69f634da078f0d90f01ba',
            'DNAME'         => 'example.com. 300 IN DNAME frobozz-division.acme.example.',
            'DNSKEY'        => 'example.com. 300 IN DNSKEY 256 3 7 AwEAAYCXh/ZABi8kiJIDXYmyUlHzC0CHeBzqcpyZAIjC7dK1wkRYVcUvIlpTOpnOVVfcC3Py9Ui/x45qKb0LytvK7WYAe3WyOOwk5klwIqRC/0p4luafbd2yhRMF7quOBVqYrLoHwv8i9LrV+r8dhB7rXv/lkTSI6mEZsg5rDfee8Yy1',
            'DS'            => 'example.com. 300 IN DS 21366 7 2 96eeb2ffd9b00cd4694e78278b5efdab0a80446567b69f634da078f0d90f01ba',
            'EUI48'         => 'example.com. 300 IN EUI48 00-00-5e-00-53-2a',
            'EUI64'         => 'example.com. 300 IN EUI64 00-00-5e-ef-10-00-00-2a',
            'HINFO'         => 'example.com. 300 IN HINFO "PC-Intel-700mhz" "Redhat \"Linux\" 7.1"',
            'HIP'           => 'example.com. 300 IN HIP 2 200100107B1A74DF365639CC39F1D578 AwEAAbdxyhNuSutc5EMzxTs9LBPCIkOFH8cIvM4p9+LrV4e19WzK00+CI6zBCQTdtWsuxKbWIy87UOoJTwkUs7lBu+Upr1gsNrut79ryra+bSRGQb1slImA8YVJyuIDsj7kwzG7jnERNqnWxZ48AWkskmdHaVDP4BcelrTI3rMXdXF5D rvs.example.com. another.example.com. test.domain.org.',
            'IPSECKEY'      => 'example.com. 300 IN IPSECKEY 10 2 2 2001:db8:0:8002:0:0:2000:1 AQNRU3mG7TVTO2BkR47usntb102uFJtugbo6BSGvgqt4AQ==',
            'ISDN'          => 'example.com. 300 IN ISDN "150 862 028 003 217" "42"',
            'KEY'           => 'example.com. 300 IN KEY 256 3 7 AwEAAYCXh/ZABi8kiJIDXYmyUlHzC0CHeBzqcpyZAIjC7dK1wkRYVcUvIlpTOpnOVVfcC3Py9Ui/x45qKb0LytvK7WYAe3WyOOwk5klwIqRC/0p4luafbd2yhRMF7quOBVqYrLoHwv8i9LrV+r8dhB7rXv/lkTSI6mEZsg5rDfee8Yy1',
            'KX'            => 'example.com. 300 IN KX 10 mx1.mrhost.ca.',
            'L32'           => 'example.com. 300 IN L32 10 10.1.2.0',
            'L64'           => 'example.com. 300 IN L64 10 2001:db8:1140:1000',
            'LOC'           => 'example.com. 300 IN LOC 42 21 54.675 N 71 06 18.343 W 24.12m 30.00m 40.00m 5.00m',
            'LP'            => 'example.com. 300 IN LP 10 l64-subnet1.example.com.',
            'MX'            => 'example.com. 300 IN MX 10 mx1.mrhost.ca.',
            'NAPTR'         => 'example.com. 300 IN NAPTR 100 10 "S" "SIP+D2U" "!^.*$!sip:customer-service@example.com!" _sip._udp.example.com.',
            'NID'           => 'example.com. 300 IN NID 10 14:4fff:ff20:ee64',
            'NSAP'          => 'example.com. 300 IN NSAP 0x47.0005.80.005a00.0000.0001.e133.aaaaaa000151.00',
            'NSEC3PARAM'    => 'example.com. 300 IN NSEC3PARAM 1 0 1 D399EAAB',
            'NSEC3'         => 'example.com. 300 IN NSEC3 1 1 12 AABBCCDD b4um86eghhds6nea196smvmlo4ors995 NS DS RRSIG',
            'NSEC'          => 'example.com. 300 IN NSEC test.host.com. A MX RRSIG NSEC TYPE1234',
            'NS'            => 'example.com. 300 IN NS ns1.mrdns.com.',
            'OPENPGPKEY'    => '8d5730bd8d76d417bf974c03f59eedb7af98cb5c3dc73ea8ebbd54b7._openpgpkey.example.com. 300 IN OPENPGPKEY AwEAAYCXh/ZABi8kiJIDXYmyUlHzC0CHeBzqcpyZAIjC7dK1wkRYVcUvIlpTOpnOVVfcC3Py9Ui/x45qKb0LytvK7WYAe3WyOOwk5klwIqRC/0p4luafbd2yhRMF7quOBVqYrLoHwv8i9LrV+r8dhB7rXv/lkTSI6mEZsg5rDfee8Yy1',
            'PTR'           => [
                                    '1.0.0.127.in-addr.arpa. 300 IN PTR localhost.',
                                    '4.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.3.0.0.0.3.0.0.a.8.0.8.0.2.0.6.2.ip6.arpa. 300 IN PTR web1.us4.fonolo.net.'
                                ],
            'PX'            => 'example.com. 300 IN PX 10 ab.net2.it. o-ab.prmd-net2.admdb.c-it.',
            'RP'            => 'example.com. 300 IN RP louie\.trantor.umd.edu. lam1.people.test.com.',
            'RRSIG'         => 'example.com. 300 IN RRSIG DNSKEY 7 1 86400 20100827211706 20100822211706 57970 gov. KoWPhMtLHp8sWYZSgsMiYJKB9P71CQmh9CnxJCs5GutKfo7Jpw+nNnDLiNnsd6U1JSkf99rYRWCyOTAPC47xkHr+2Uh7n6HDJznfdCzRa/v9uwEcbXIxCZ7KfzNJewW3EvYAxDIrW6sY/4MAsjS5XM/O9LaWzw6pf7TX5obBbLI+zRECbPNTdY+RF6Fl9K0GVaEZJNYi2PRXnATwvwca2CNRWxeMT/dF5STUram3cWjH0Pkm19Gc1jbdzlZVDbUudDauWoHcc0mfH7PV1sMpe80NqK7yQ24AzAkXSiknO13itHsCe4LECUu0/OtnhHg2swwXaVTf5hqHYpzi3bQenw==',
            'RT'            => 'example.com. 300 IN RT 2 relay.prime.com.',
            'SIG'           => 'example.com. 300 IN SIG DNSKEY 7 1 86400 20100827211706 20100822211706 57970 gov. KoWPhMtLHp8sWYZSgsMiYJKB9P71CQmh9CnxJCs5GutKfo7Jpw+nNnDLiNnsd6U1JSkf99rYRWCyOTAPC47xkHr+2Uh7n6HDJznfdCzRa/v9uwEcbXIxCZ7KfzNJewW3EvYAxDIrW6sY/4MAsjS5XM/O9LaWzw6pf7TX5obBbLI+zRECbPNTdY+RF6Fl9K0GVaEZJNYi2PRXnATwvwca2CNRWxeMT/dF5STUram3cWjH0Pkm19Gc1jbdzlZVDbUudDauWoHcc0mfH7PV1sMpe80NqK7yQ24AzAkXSiknO13itHsCe4LECUu0/OtnhHg2swwXaVTf5hqHYpzi3bQenw==',
            'SMIMEA'        => 'c93f1e400f26708f98cb19d936620da35eec8f72e57f9eec01c1afd6._smimecert.example.com. 300 IN SMIMEA 1 1 2 92003ba34942dc74152e2f2c408d29eca5a520e7f2e06bb944f4dca346baf63c1b177615d466f6c4b71c216a50292bd58c9ebdd2f74e38fe51ffd48c43326cbc',
            'SOA'           => 'example.com. 300 IN SOA ns1.mrdns.com. help\.team.mrhost.ca. 1278700841 900 1800 86400 21400',
            'SPF'           => 'example.com. 300 IN SPF "v=spf1 ip4:192.168.0.1/24 mx ?all"',
            'SRV'           => 'example.com. 300 IN SRV 20 0 5269 xmpp-server2.l.google.com.',
            'SSHFP'         => 'example.com. 300 IN SSHFP 2 1 123456789abcdef67890123456789abcdef67890',
            'TALINK'        => 'example.com. 300 IN TALINK c1.example.com. c3.example.com.',
            'TA'            => 'example.com. 300 IN TA 21366 7 2 96eeb2ffd9b00cd4694e78278b5efdab0a80446567b69f634da078f0d90f01ba',
            'TKEY'          => 'example.com. 300 IN TKEY gss.microsoft.com. 3 123456.',
            'TLSA'          => '_443._tcp.www.example.com. 300 IN TLSA 1 1 2 92003ba34942dc74152e2f2c408d29eca5a520e7f2e06bb944f4dca346baf63c1b177615d466f6c4b71c216a50292bd58c9ebdd2f74e38fe51ffd48c43326cbc',
            'TXT'           => 'example.com. 300 IN TXT "first record" "another records" "a third"',
            'URI'           => 'example.com. 300 IN URI 10 1 "https://netdns2.com/about"',
            'WKS'           => 'example.com. 300 IN WKS 128.8.1.14 6 21 25',
            'X25'           => 'example.com. 300 IN X25 "311 06 17 0 09 56"',
            'ZONEMD'        => 'example.com. 300 IN ZONEMD 2018031500 1 1 febe3d4ce2ec2ffa4ba99d46cd69d6d29711e55217057bee7eb1a7b641a47ba7fed2dd5b97ae499fafa4f22c6bd647de'
        ];

        foreach ($rrs as $rr => $checks)
        {
            $lines = [];

            if (is_array($checks) == true)
            {
                $lines = $checks;
            } else
            {
                $lines[] = $checks;
            }

            foreach($lines as $line)
            {
                $class_name = 'Net_DNS2_RR_' . $rr;

                //
                // create a new packet
                //
                if ($rr == 'PTR') {
                    $request = new Net_DNS2_Packet_Request('1.0.0.127.in-addr.arpa', $rr, 'IN');
                } else {
                    $request = new Net_DNS2_Packet_Request('example.com', $rr, 'IN');
                }

                //
                // parse the line
                //
                $a = Net_DNS2_RR::fromString($line);

                //
                // check that the object is right
                //
                $this->assertTrue($a instanceof $class_name);

                //
                // set it on the packet
                //
                $request->answer[] = $a;
                $request->header->ancount = 1;

                //
                // get the binary packet data
                //
                $data = $request->get();

                //
                // parse the binary
                //
                $response = new Net_DNS2_Packet_Response($data, strlen($data));

                //
                // the answer data in the response, should match our initial line exactly
                //
                $this->assertSame($line, $response->answer[0]->__toString());
            }
        }
    }

    /**
     * function to test the compression logic
     *
     * @return void
     * @access public
     *
     */
    public function testCompression()
    {
        //
        // this list of RR's uses name compression
        //
        $rrs = [

            'AFSDB'         => 'example.com. 300 IN AFSDB 3 afsdb.example.com.',
            'CNAME'         => 'example.com. 300 IN CNAME www.example.com.',
            'DNAME'         => 'example.com. 300 IN DNAME frobozz-division.acme.example.',
            'HIP'           => 'example.com. 300 IN HIP 2 200100107B1A74DF365639CC39F1D578 AwEAAbdxyhNuSutc5EMzxTs9LBPCIkOFH8cIvM4p9+LrV4e19WzK00+CI6zBCQTdtWsuxKbWIy87UOoJTwkUs7lBu+Upr1gsNrut79ryra+bSRGQb1slImA8YVJyuIDsj7kwzG7jnERNqnWxZ48AWkskmdHaVDP4BcelrTI3rMXdXF5D rvs.example.com. another.example.com. test.domain.org.',
            'MX'            => 'example.com. 300 IN MX 10 mx1.mrhost.ca.',
            'NAPTR'         => 'example.com. 300 IN NAPTR 100 10 S SIP+D2U !^.*$!sip:customer-service@example.com! _sip._udp.example.com.',
            'NS'            => 'example.com. 300 IN NS ns1.mrdns.com.',
            'PX'            => 'example.com. 300 IN PX 10 ab.net2.it. o-ab.prmd-net2.admdb.c-it.',
            'RP'            => 'example.com. 300 IN RP louie\.trantor.umd.edu. lam1.people.test.com.',
            'RT'            => 'example.com. 300 IN RT 2 relay.prime.com.',
            'SOA'           => 'example.com. 300 IN SOA ns1.mrdns.com. help\.desk.mrhost.ca. 1278700841 900 1800 86400 21400',
            'SRV'           => 'example.com. 300 IN SRV 20 0 5269 xmpp-server2.l.google.com.',
        ];

        //
        // create a new updater object
        //
        $u = new Net_DNS2_Updater("example.com", [ 'nameservers' => [ '10.10.0.1' ] ]);

        //
        // add each RR to the same object, so we can build a build compressed name list
        //
        foreach ($rrs as $rr => $line) {

            $class_name = 'Net_DNS2_RR_' . $rr;

            //
            // parse the line
            //
            $a = Net_DNS2_RR::fromString($line);

            //
            // check that the object is right
            //
            $this->assertTrue($a instanceof $class_name);

            //
            // set it on the packet
            //
            $u->add($a);
        }

        //
        // get the request packet
        //
        $request = $u->packet();

        //
        // get the authority section of the request
        //
        $request_authority = $request->authority;

        //
        // parse the binary
        //
        $data = $request->get();
        $response = new Net_DNS2_Packet_Response($data, strlen($data));

        //
        // get the authority section of the response, and clean up the
        // rdata so everything will match.
        //
        // the request packet doesn't have the rdlength and rdata fields
        // built yet, so it will throw off the hash
        //
        $response_authority = $response->authority;

        foreach ($response_authority as $id => $object) {

            $response_authority[$id]->rdlength = '';
            $response_authority[$id]->rdata = '';

            $a = print_r($request_authority[$id], true);
            $b = print_r($object, true);

            $this->assertSame($a, $b);
        }

        //
        // build the hashes
        //
        $a = md5(print_r($request_authority, true));
        $b = md5(print_r($response_authority, true));

        //
        // the new hashes should match.
        //
        $this->assertSame($a, $b);
    }
}
