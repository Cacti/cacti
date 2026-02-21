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
 * The SMIMEA RR is implemented exactly like the TLSA record, so for now we just extend the TLSA RR and use it.
 *
 */
final class SMIMEA extends \NetDNS2\RR\TLSA
{
}
