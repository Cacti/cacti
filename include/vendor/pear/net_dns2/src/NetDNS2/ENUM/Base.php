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

namespace NetDNS2\ENUM;

trait Base
{
    public static function set(string|int $_id): self
    {
        foreach(self::cases() as $entry)
        {
            if (((is_numeric($_id) == true) ? $entry->value : $entry->label()) == $_id)
            {
                return $entry;
            }
        }

        throw new \NetDNS2\Exception(sprintf('invalid enum value %s specified.', $_id), \NetDNS2\ENUM\Error::INT_INVALID_ENUM);
    }
    public static function exists(string|int $_id): bool
    {
        try
        {
            $res = self::set($_id);
            return true;

        } catch(\NetDNS2\Exception)
        {
            return false;
        }
    }
}
