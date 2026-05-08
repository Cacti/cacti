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
 * RFC 6975 - Signaling Cryptographic Algorithm Understanding in DNS Security Extensions (DNSSEC)
 *
 *  0                       8                      16
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *  |                  OPTION-CODE                  |
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *  |                  LIST-LENGTH                  |
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *  |       ALG-CODE        |        ...            /
 *  +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
final class N3U extends \NetDNS2\RR\OPT\DAU
{
}
