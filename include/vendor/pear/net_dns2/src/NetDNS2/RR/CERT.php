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
 * CERT Resource Record - RFC4398 section 2
 *
 *  0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |            format             |             key tag           |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |   algorithm   |                                               /
 *  +---------------+            certificate or CRL                 /
 *  /                                                               /
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-|
 *
 */
final class CERT extends \NetDNS2\RR
{
    /**
     * certificate format
     */
    protected \NetDNS2\ENUM\CertFormat $format;

    /**
     * key tag
     */
    protected int $keytag;

    /**
     * The algorithm used for the cert
     */
    protected \NetDNS2\ENUM\DNSSEC\Algorithm $algorithm;

    /**
     * certificate
     */
    protected string $certificate = '';

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->format->value . ' ' . $this->keytag . ' ' . $this->algorithm->value . ' ' . base64_encode($this->certificate);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->format       = \NetDNS2\ENUM\CertFormat::set($this->sanitize(array_shift($_rdata), false));
        $this->keytag       = intval($this->sanitize(array_shift($_rdata)));
        $this->algorithm    = \NetDNS2\ENUM\DNSSEC\Algorithm::set($this->sanitize(array_shift($_rdata)));

        //
        // parse and base64 decode the certificate
        //
        // certificates MUST be provided base64 encoded, if not, everything will be broken after this point, as we assume it's base64 encoded.
        //
        $this->certificate = base64_decode(implode(' ', $_rdata));

        if ($this->certificate === false)
        {
            throw new \NetDNS2\Exception(sprintf('invalid certificate value provided: %s', $this->certificate), \NetDNS2\ENUM\Error::INT_INVALID_CERTIFICATE);
        }

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
        // unpack the format, keytag and algorithm
        //
        $val = unpack('nx/ny/Cz', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $format, 'y' => $this->keytag, 'z' => $algorithm) = (array)$val;

        $this->format    = \NetDNS2\ENUM\CertFormat::set($format);
        $this->algorithm = \NetDNS2\ENUM\DNSSEC\Algorithm::set($algorithm);

        //
        // copy the certificate
        //
        $this->certificate = substr($this->rdata, 5, $this->rdlength - 5);

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (strlen($this->certificate) == 0)
        {
            return '';
        }

        $_packet->offset += strlen($this->certificate) + 5;

        return pack('nnC', $this->format->value, $this->keytag, $this->algorithm->value) . $this->certificate;
    }
}
