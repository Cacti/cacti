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
 * Offline tests for \NetDNS2\Updater packet construction.
 *
 * These tests verify that the Updater correctly populates the DNS UPDATE packet
 * sections (prerequisite → answer, update → authority) without sending any
 * network traffic.  The internal m_packet is accessed via ReflectionProperty.
 *
 */
class UpdaterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Retrieve the private m_packet from the Updater via reflection.
     *
     */
    private function getPacket(\NetDNS2\Updater $_updater): \NetDNS2\Packet\Request
    {
        $ref = new \ReflectionProperty(\NetDNS2\Updater::class, 'm_packet');
        $ref->setAccessible(true);

        /** @var \NetDNS2\Packet\Request $packet */
        $packet = $ref->getValue($_updater);

        return $packet;
    }

    /**
     * The Updater packet carries opcode=UPDATE after construction.
     *
     */
    public function testOpcodeIsUpdate(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $packet = $this->getPacket($u);

        $this->assertSame(\NetDNS2\ENUM\OpCode::UPDATE, $packet->header->opcode);
    }

    /**
     * The zone section (question[0]) holds the zone SOA name.
     *
     */
    public function testZoneSection(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->question);
        $this->assertSame('example.com', (string)$packet->question[0]->qname);
        $this->assertSame('SOA', $packet->question[0]->qtype->label());
    }

    /**
     * add() places the RR in the update (authority) section with the zone class.
     *
     */
    public function testAddPutsRRInAuthority(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        /** @var \NetDNS2\RR\A $rr */
        $rr = \NetDNS2\RR::fromString('host.example.com. 300 IN A 192.0.2.1');

        $u->add($rr);

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->authority);
        $this->assertSame('A', $packet->authority[0]->type->label());
        $this->assertSame('IN', $packet->authority[0]->class->label());
        $this->assertSame(300, $packet->authority[0]->ttl);
    }

    /**
     * delete() places the RR in the authority section with TTL=0 and class=NONE.
     *
     */
    public function testDeleteSetsNoneClass(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        /** @var \NetDNS2\RR\A $rr */
        $rr = \NetDNS2\RR::fromString('host.example.com. 300 IN A 192.0.2.1');

        $u->delete($rr);

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->authority);
        $this->assertSame('NONE', $packet->authority[0]->class->label());
        $this->assertSame(0, $packet->authority[0]->ttl);
    }

    /**
     * deleteAny() places a synthetic RR with class=ANY in the authority section.
     *
     */
    public function testDeleteAnySetsAnyClass(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->deleteAny('host.example.com', 'A');

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->authority);
        $this->assertSame('A', $packet->authority[0]->type->label());
        $this->assertSame('ANY', $packet->authority[0]->class->label());
        $this->assertSame(0, $packet->authority[0]->ttl);
    }

    /**
     * deleteAll() places a synthetic ANY/ANY RR in the authority section.
     *
     */
    public function testDeleteAllSetsAnyType(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->deleteAll('host.example.com');

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->authority);
        $this->assertSame('ANY', $packet->authority[0]->type->label());
        $this->assertSame('ANY', $packet->authority[0]->class->label());
    }

    /**
     * checkExists() places the prerequisite RR in the answer section with class=ANY.
     *
     */
    public function testCheckExistsPutsRRInAnswer(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->checkExists('host.example.com', 'A');

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->answer);
        $this->assertSame('A', $packet->answer[0]->type->label());
        $this->assertSame('ANY', $packet->answer[0]->class->label());
        $this->assertSame(0, $packet->answer[0]->ttl);
    }

    /**
     * checkNotExists() places the prerequisite RR in the answer section with class=NONE.
     *
     */
    public function testCheckNotExistsSetsNoneClass(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->checkNotExists('host.example.com', 'A');

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->answer);
        $this->assertSame('NONE', $packet->answer[0]->class->label());
        $this->assertSame(0, $packet->answer[0]->ttl);
    }

    /**
     * checkNameInUse() places an ANY/ANY prerequisite in the answer section.
     *
     */
    public function testCheckNameInUseSetsAnyType(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->checkNameInUse('host.example.com');

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->answer);
        $this->assertSame('ANY', $packet->answer[0]->type->label());
        $this->assertSame('ANY', $packet->answer[0]->class->label());
    }

    /**
     * checkNameNotInUse() places an ANY/NONE prerequisite in the answer section.
     *
     */
    public function testCheckNameNotInUseSetsNoneClass(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->checkNameNotInUse('host.example.com');

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->answer);
        $this->assertSame('ANY', $packet->answer[0]->type->label());
        $this->assertSame('NONE', $packet->answer[0]->class->label());
    }

    /**
     * checkValueExists() places the full RR in the answer section with TTL=0 and zone class.
     *
     */
    public function testCheckValueExistsPutsRRInAnswer(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        /** @var \NetDNS2\RR\A $rr */
        $rr = \NetDNS2\RR::fromString('host.example.com. 300 IN A 192.0.2.1');

        $u->checkValueExists($rr);

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->answer);
        $this->assertSame('A', $packet->answer[0]->type->label());
        $this->assertSame(0, $packet->answer[0]->ttl);
    }

    /**
     * add() with a name that does not belong to the zone throws \NetDNS2\Exception.
     *
     */
    public function testAddRejectsNameOutsideZone(): void
    {
        $this->expectException(\NetDNS2\Exception::class);

        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        /** @var \NetDNS2\RR\A $rr */
        $rr = \NetDNS2\RR::fromString('host.other.org. 300 IN A 192.0.2.1');

        $u->add($rr);
    }

    /**
     * delete() with a name that does not belong to the zone throws \NetDNS2\Exception.
     *
     */
    public function testDeleteRejectsNameOutsideZone(): void
    {
        $this->expectException(\NetDNS2\Exception::class);

        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        /** @var \NetDNS2\RR\A $rr */
        $rr = \NetDNS2\RR::fromString('host.other.org. 300 IN A 192.0.2.1');

        $u->delete($rr);
    }

    /**
     * deleteAny() with a name that does not belong to the zone throws \NetDNS2\Exception.
     *
     */
    public function testDeleteAnyRejectsNameOutsideZone(): void
    {
        $this->expectException(\NetDNS2\Exception::class);

        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->deleteAny('host.other.org', 'A');
    }

    /**
     * checkExists() with a name that does not belong to the zone throws \NetDNS2\Exception.
     *
     */
    public function testCheckExistsRejectsNameOutsideZone(): void
    {
        $this->expectException(\NetDNS2\Exception::class);

        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        $u->checkExists('host.other.org', 'A');
    }

    /**
     * Calling add() twice with the same RR object does not add a duplicate.
     *
     */
    public function testAddDeduplicates(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        /** @var \NetDNS2\RR\A $rr */
        $rr = \NetDNS2\RR::fromString('host.example.com. 300 IN A 192.0.2.1');

        $u->add($rr);
        $u->add($rr);

        $packet = $this->getPacket($u);

        $this->assertCount(1, $packet->authority, 'duplicate add() calls must not produce duplicate entries in the authority section');
    }

    /**
     * Multiple independent RRs can be added to the update section.
     *
     */
    public function testAddMultipleRRs(): void
    {
        $u = new \NetDNS2\Updater('example.com', [ 'nameservers' => [ '127.0.0.1' ] ]);

        /** @var \NetDNS2\RR\A $rr1 */
        $rr1 = \NetDNS2\RR::fromString('a.example.com. 300 IN A 192.0.2.1');

        /** @var \NetDNS2\RR\A $rr2 */
        $rr2 = \NetDNS2\RR::fromString('b.example.com. 300 IN A 192.0.2.2');

        $u->add($rr1);
        $u->add($rr2);

        $packet = $this->getPacket($u);

        $this->assertCount(2, $packet->authority);
    }
}
