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
 * EID Resource Record - undefined; the rdata is simply used as-is in it's binary format, so not process has to be done.
 *
 */
final class EID extends \NetDNS2\RR\RR_NULL
{
}
