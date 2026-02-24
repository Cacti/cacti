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
 * The DLV RR is implemented exactly like the DS RR; so we just extend that class, and use all of it's methods
 *
 */
final class DLV extends \NetDNS2\RR\DS
{
}
