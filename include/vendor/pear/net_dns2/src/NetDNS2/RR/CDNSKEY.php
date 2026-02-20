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
 * The CDNSKEY RR is implemented exactly like the DNSKEY record, so for now we just extend the DNSKEY RR and use it.
 *
 * http://www.rfc-editor.org/rfc/rfc7344.txt
 *
 */
final class CDNSKEY extends \NetDNS2\RR\DNSKEY
{
}
