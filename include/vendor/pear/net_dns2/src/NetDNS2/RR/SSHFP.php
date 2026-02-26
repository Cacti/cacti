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
 * SSHFP Resource Record - RFC4255 section 3.1
 *
 *       0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *      +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *      |   algorithm   |    fp type    |                               /
 *      +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               /
 *      /                                                               /
 *      /                          fingerprint                          /
 *      /                                                               /
 *      +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
final class SSHFP extends \NetDNS2\RR
{
    /**
     * Algorithms
     */
    public const SSHFP_ALGORITHM_RES       = 0;
    public const SSHFP_ALGORITHM_RSA       = 1;
    public const SSHFP_ALGORITHM_DSS       = 2;
    public const SSHFP_ALGORITHM_ECDSA     = 3;
    public const SSHFP_ALGORITHM_ED25519   = 4;
    public const SSHFP_ALGORITHM_ED448     = 6;

    /**
     * Fingerprint Types
     */
    public const SSHFP_FPTYPE_RES      = 0;
    public const SSHFP_FPTYPE_SHA1     = 1;
    public const SSHFP_FPTYPE_SHA256   = 2;

    /**
     * the algorithm used
     */
    protected int $algorithm;

    /**
     * The finger print type
     */
    protected int $fp_type;

    /**
     * the finger print data
     */
    protected string $fingerprint;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->algorithm . ' ' . $this->fp_type . ' ' . $this->fingerprint;
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        //
        // "The use of mnemonics instead of numbers is not allowed."
        //
        // RFC4255 section 3.2
        //
        $this->algorithm   = intval($this->sanitize(array_shift($_rdata)));
        $this->fp_type     = intval($this->sanitize(array_shift($_rdata)));
        $this->fingerprint = strtolower($this->sanitize(implode('', $_rdata)));

        //
        // validate the algorithm
        //
        switch($this->algorithm)
        {
            case self::SSHFP_ALGORITHM_RSA:
            case self::SSHFP_ALGORITHM_DSS:
            case self::SSHFP_ALGORITHM_ECDSA:
            case self::SSHFP_ALGORITHM_ED25519:
            case self::SSHFP_ALGORITHM_ED448:
            {
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid algorithm value provided: %d', $this->algorithm), \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM);
            }
        }

        //
        // there are only two fingerprints defined
        //
        switch($this->fp_type)
        {
            case self::SSHFP_FPTYPE_SHA1:
            case self::SSHFP_FPTYPE_SHA256:
            {
            }
            break;
            default:
            {
                throw new \NetDNS2\Exception(sprintf('invalid fingerprint type value provided: %d', $this->fp_type), \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
            }
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

        $val = unpack('Cx/Cy/H*z', $this->rdata);
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->algorithm, 'y' => $this->fp_type, 'z' => $this->fingerprint) = (array)$val;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (strlen($this->fingerprint) == 0)
        {
            return '';
        }

        $data = pack('CCH*', $this->algorithm, $this->fp_type, $this->fingerprint);

        $_packet->offset += strlen($data);

        return $data;
    }
}
