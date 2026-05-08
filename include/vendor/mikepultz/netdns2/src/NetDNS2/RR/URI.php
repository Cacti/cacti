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
 * URI Resource Record - http://tools.ietf.org/html/draft-faltstrom-uri-06
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |          Priority             |          Weight               |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   /                                                               /
 *   /                             Target                            /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 * @property int $priority
 * @property int $weight
 * @property \NetDNS2\Data\Text $target
 */
final class URI extends \NetDNS2\RR
{
    /**
     * The priority of this target host.
     */
    protected int $priority;

    /**
     * a relative weight for entries with the same priority
     */
    protected int $weight;

    /**
      * The domain name of the target host
     */
    protected \NetDNS2\Data\Text $target;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->priority . ' ' . $this->weight . ' ' . \NetDNS2\RR::formatString($this->target->value());
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->priority = intval($this->sanitize(array_shift($_rdata)));
        $this->weight   = intval($this->sanitize(array_shift($_rdata)));
        $this->target   = new \NetDNS2\Data\Text($this->sanitize(array_shift($_rdata)));

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

        $val = unpack('nx/ny/a*z', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->priority, 'y' => $this->weight, 'z' => $target) = (array)$val;

        $this->target = new \NetDNS2\Data\Text($target);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if ($this->target->length() == 0)
        {
            return '';
        }

        $_packet->offset += $this->target->length() + 4;

        return pack('nna*', $this->priority, $this->weight, $this->target);
    }
}
