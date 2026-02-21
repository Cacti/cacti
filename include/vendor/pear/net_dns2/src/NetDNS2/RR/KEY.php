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
 * the KEY RR is implemented the same as the DNSKEY RR, the only difference is how the flags data is parsed.
 *
 *     0   1   2   3   4   5   6   7   8   9   0   1   2   3   4   5
 *   +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *   |  A/C  | Z | XT| Z | Z | NAMTYP| Z | Z | Z | Z |      SIG      |
 *   +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *
 * DNSKEY only uses bits 7 and 15
 *
 * We're not doing anything with these flags right now, so duplicating the class like this is fine.
 *
 */
final class KEY extends \NetDNS2\RR\DNSKEY
{
}
