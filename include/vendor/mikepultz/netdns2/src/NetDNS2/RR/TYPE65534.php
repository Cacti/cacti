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
 * TYPE65534 - Private space
 *
 * Since Bind 9.8 beta, it use a private recode as documented in the Bind ARM, chapter 4, "Private-type records. Basically they store
 * signing process state.
 *
 * @property string $private_data
 */
final class TYPE65534 extends \NetDNS2\RR
{
    /**
     * The Private data field
     */
    protected string $private_data;

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return base64_encode($this->private_data);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        $this->private_data = base64_decode(implode('', $_rdata));
        if ($this->private_data === false)
        {
            $this->private_data = '';
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

        $this->private_data  = $this->rdata;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        if (strlen($this->private_data) == 0)
        {
            return '';
        }

        $_packet->offset += strlen($this->private_data);

        return $this->private_data;
    }
}
