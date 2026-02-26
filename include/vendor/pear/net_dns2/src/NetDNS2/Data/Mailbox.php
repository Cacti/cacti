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

namespace NetDNS2\Data;

/**
 * container to store mailbox values used by some RRs (SOA, RP)
 *
 * the mailbox value is stored as an email address (with an @ symbol), but converted to wire format
 * on the way in/out.
 *
 * this also support escaping periods in email addresses.
 *
 */
final class Mailbox extends \NetDNS2\Data
{
    /**
      * encode the stored value and return it
      *
      * @throws \NetDNS2\Exception
      */
    public function encode(int &$_offset = -1): string
    {
        //
        // format the current value
        //
        $value = $this->email_to_mbox($this->m_value);

        switch($this->m_type)
        {
            case self::DATA_TYPE_RFC1035:
            {
                return $this->encode_rfc1035($value, $_offset);
            }
            case self::DATA_TYPE_RFC2535:
            {
                return $this->encode_rfc2535($value, $_offset);
            }
        }

        throw new \NetDNS2\Exception('invalid mailbox encoding type.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
    }

    /**
      * decode the value provided and store it locally
      *
      */
    protected function decode(string $_rdata, int &$_offset): void
    {
        $this->m_value = $this->mbox_to_email(implode('.', $this->_decode($_rdata, $_offset, true)));
    }

    /**
     * format the email address to a mbox style email
     */
    public function display(): string
    {
        return $this->email_to_mbox($this->m_value);
    }

    /**
     * format the mbox style to a real email address for storage
     */
    private function mbox_to_email(string $_mailbox): string
    {
        //
        // split on the first . found, but use a negative lookbehind to skip any escaped instances
        //
        $x = preg_split('/(?<!\\\)\./', strtolower($_mailbox), 2);
        if ($x === false)
        {
            return $_mailbox;
        }

        //
        // according to RFC1035, the RNAME value in an SOA record has to be an email address, but practically, DNS servers don't
        // enforce this value, so you can set it to a value like "root." and it would be considered valid.
        //
        // this takes into account cases where there's a "." (e.g. @) in the value, and when there isn't- both cases are 
        // accepted by the DNS server.
        //
        return (count($x) == 2) ? str_replace('\.', '.', $x[0]) . '@' . $x[1] : $x[0];
    }

    /**
     * format the email address to a mbox style email
     */
    private function email_to_mbox(string $_mailbox): string
    {
        if (strpos($_mailbox, '@') === false)
        {
            return $_mailbox;
        }

        $x = explode('@', $_mailbox);

        return str_replace('.', '\.', $x[0]) . '.' . $x[1];
    }
}
