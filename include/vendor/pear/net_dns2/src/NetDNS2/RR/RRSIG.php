<?php declare(strict_types=1);

/**
 * This file is part of the NetDNS2 package.
 *
 * (c) Mike Pultz <mike@mikepultz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This file contains code based off the Net::DNS::SEC Perl module by Olaf M. Kolkman
 *
 * This is the copyright notice from the PERL Net::DNS::SEC module:
 *
 * Copyright (c) 2001 - 2005  RIPE NCC.  Author Olaf M. Kolkman
 * Copyright (c) 2007 - 2008  NLnet Labs.  Author Olaf M. Kolkman
 * <olaf@net-dns.org>
 *
 * All Rights Reserved
 *
 * Permission to use, copy, modify, and distribute this software and its
 * documentation for any purpose and without fee is hereby granted,
 * provided that the above copyright notice appear in all copies and that
 * both that copyright notice and this permission notice appear in
 * supporting documentation, and that the name of the author not be
 * used in advertising or publicity pertaining to distribution of the
 * software without specific, written prior permission.
 *
 * THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE, INCLUDING
 * ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS; IN NO EVENT SHALL
 * AUTHOR BE LIABLE FOR ANY SPECIAL, INDIRECT OR CONSEQUENTIAL DAMAGES OR ANY
 * DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN
 * AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 *
 */

namespace NetDNS2\RR;

/**
 * RRSIG Resource Record - RFC4034 sction 3.1
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |        Type Covered           |  Algorithm    |     Labels    |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                         Original TTL                          |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                      Signature Expiration                     |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                      Signature Inception                      |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |            Key Tag            |                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+         Signer's Name         /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   /                                                               /
 *   /                            Signature                          /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
final class RRSIG extends \NetDNS2\RR
{
    /**
     * the RR type covered by this signature; the string value is provided here;
     *
     * TODO: we can't use the RR\Type ENUM here, since this RR supports undefined types (e.g. TYPE123)
     */
    protected string $typecovered;

    /**
     * the algorithm used for the signature
     */
    protected \NetDNS2\ENUM\DNSSEC\Algorithm $algorithm;

    /**
     * the number of labels in the name
     */
    protected int $labels;

    /**
     * the original TTL
     */
    protected int $origttl;

    /**
     * the signature expiration
     */
    protected string $sigexp;

    /**
     * the inception of the signature
     */
    protected string $sigincep;

    /**
     * the keytag used
     */
    protected int $keytag;

    /**
     * the signer's name
     */
    protected \NetDNS2\Data\Domain $signname;

    /**
     * the signature
     */
    protected string $signature = '';

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->typecovered . ' ' . $this->algorithm->value . ' ' . $this->labels . ' ' . $this->origttl . ' ' . $this->sigexp . ' ' .
            $this->sigincep . ' ' . $this->keytag . ' ' . $this->signname . '. ' . $this->signature;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->typecovered  = strtoupper($this->sanitize(array_shift($_rdata)));
        $this->algorithm    = \NetDNS2\ENUM\DNSSEC\Algorithm::set(intval($this->sanitize(array_shift($_rdata))));
        $this->labels       = intval($this->sanitize(array_shift($_rdata)));
        $this->origttl      = intval($this->sanitize(array_shift($_rdata)));
        $this->sigexp       = $this->sanitize(array_shift($_rdata));
        $this->sigincep     = $this->sanitize(array_shift($_rdata));
        $this->keytag       = intval($this->sanitize(array_shift($_rdata)));
        $this->signname     = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, array_shift($_rdata));

        foreach($_rdata as $line)
        {
            $this->signature .= $line;
        }

        $this->signature = trim($this->signature);

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

        //
        // unpack
        //
        $val = unpack('na/Cb/Cc/Nd/Ne/Nf/ng', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('a' => $typecovered, 'b' => $algorithm, 'c' => $this->labels, 'd' => $this->origttl, 'e' => $e, 'f' => $f, 'g' => $this->keytag) = (array)$val;

        if (\NetDNS2\ENUM\RR\Type::exists($typecovered) == true)
        {
            $this->typecovered = \NetDNS2\ENUM\RR\Type::set($typecovered)->label();
        } else
        {
            $this->typecovered = 'TYPE' . $typecovered;
        }

        $this->algorithm = \NetDNS2\ENUM\DNSSEC\Algorithm::set($algorithm);

        //
        // the dates are in GM time
        //
        $this->sigexp      = gmdate('YmdHis', $e);
        $this->sigincep    = gmdate('YmdHis', $f);

        //
        // get teh signers name and signature
        //
        $offset            = $_packet->offset + 18;
        $sigoffset         = $offset;

        $this->signname    = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $_packet, $sigoffset);
        $this->signature   = base64_encode(substr($this->rdata, 18 + ($sigoffset - $offset)));

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // parse the values out of the dates
        //
        preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigexp, $e);
        preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigincep, $i);

        //
        // pack the value
        //
        $data = pack('nCCNNNn',
            \NetDNS2\ENUM\RR\Type::set($this->typecovered)->value,
            $this->algorithm->value,
            $this->labels,
            $this->origttl,
            gmmktime(intval($e[4]), intval($e[5]), intval($e[6]), intval($e[2]), intval($e[3]), intval($e[1])),
            gmmktime(intval($i[4]), intval($i[5]), intval($i[6]), intval($i[2]), intval($i[3]), intval($i[1])),
            $this->keytag
        );

        $data .= $this->signname->encode($_packet->offset);

        //
        // add the signature
        //
        $decode = base64_decode($this->signature);
        if ($decode !== false)
        {
            $data .= $decode;
        }

        $_packet->offset += strlen($data);

        return $data;
    }
}
