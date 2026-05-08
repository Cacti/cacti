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

namespace NetDNS2;

/**
 * a class to handle converting RR bitmaps to arrays and back; used on NSEC and NSEC3 RR's
 *
 */
final class BitMap
{
    /**
     * validate a list of RR's for NSEC and NSEC3 bitmap fields
     *
     * @param array<int,int|string> $_data an array of RR id's or mnemonics to validate
     *
     * @return array<int,string>
     *
     */
    public static function validateArray(array $_data): array
    {
        if (count($_data) == 0)
        {
            return [];
        }

        $out = [];

        //
        // loop through the RR's provided
        //
        foreach($_data as $rr)
        {
            //
            // RR's can be provided as integer values
            //
            if (is_numeric($rr) == true)
            {
                //
                // per RFC 4034 4.1.2 the RR field is a single octect
                //
                if ( ($rr < 0) || ($rr > 255) )
                {
                    throw new \NetDNS2\Exception('NSEC resource records must be between 0-255.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                }

                //
                // TODO: we don't currently store the \NetDNS2\ENUM\RR\Type ENUM directly, since this object supports undefined RR types
                //       (e.g. TYPE123), while the ENUM enforces a predefined list.
                //
                if (\NetDNS2\ENUM\RR\Type::exists($rr) == true)
                {
                    $out[] = \NetDNS2\ENUM\RR\Type::set($rr)->label();
                } else
                {
                    $out[] = 'TYPE' . $rr;
                }

            //
            // or as mnemonics
            //
            } else
            {
                $mnemonic = strtoupper($rr);

                if (\NetDNS2\ENUM\RR\Type::exists($mnemonic) == true)
                {
                    $out[] = $mnemonic;

                } elseif (strncmp($mnemonic, 'TYPE', 4) == 0)
                {
                    $value = intval(str_replace('TYPE', '', $mnemonic));

                    if ( ($value < 0) || ($value > 255) )
                    {
                        throw new \NetDNS2\Exception('NSEC resource records must be between 0-255.', \NetDNS2\ENUM\Error::INT_PARSE_ERROR);
                    } else
                    {
                        $out[] = $mnemonic;
                    }

                } else
                {
                    throw new \NetDNS2\Exception(sprintf('unknown or un-supported resource record type: %s', $mnemonic), \NetDNS2\ENUM\Error::INT_INVALID_TYPE);
                }
            }
        }

        return $out;
    }

    /**
     * parses a RR bitmap field defined in RFC3845, into an array of RR names.
     *
     * Type Bit Map(s) Field = ( Window Block # | Bitmap Length | Bitmap ) +
     *
     * @param string $_data a bitmap stringto parse
     *
     * @return array<string>
     *
     */
    public static function bitMapToArray(string $_data): array
    {
        if (strlen($_data) == 0)
        {
            return [];
        }

        $output = [];
        $offset = 0;
        $total  = strlen($_data);

        while($offset < $total)
        {
            //
            // unpack the window and length values
            //
            $val = unpack('Cx/Cy', $_data, $offset);
            if ($val === false)
            {
                return [];
            }

            list('x' => $window, 'y' => $bm_length) = (array)$val;
            $offset += 2;

            //
            // copy out the bitmap value
            //
            $bitmap = unpack('C*', substr($_data, $offset, $bm_length));
            if ($bitmap === false)
            {
                return [];
            }

            $offset += $bm_length;

            //
            // I'm not sure if there's a better way of doing this, but PHP doesn't
            // have a 'B' flag for unpack()
            //
            $bitstr = '';

            foreach((array)$bitmap as $r)
            {
                $bitstr .= sprintf('%08b', $r);
            }

            $blen = strlen($bitstr);

            for($i=0; $i<$blen; $i++)
            {
                if ($bitstr[$i] == '1')
                {
                    $type = $window * 256 + $i;

                    if (\NetDNS2\ENUM\RR\Type::exists($type) == true)
                    {
                        $output[] = \NetDNS2\ENUM\RR\Type::set($type)->label();
                    } else
                    {
                        $output[] = 'TYPE' . $type;
                    }
                }
            }
        }

        return $output;
    }

    /**
     * convert a list of RR type names (e.g., ["A","AAAA","TYPE123"]) to an NSEC/NSEC3 bitmap.
     *
     * @param  array<string> $_data a list of RR names
     * @return string
     * 
     */
    public static function arrayToBitMap(array $_data): string
    {
        if (count($_data) === 0)
        {
            return '';
        }

        /** @phpstan-var array<int, array{length:int, bits: array<int, true>}> $bm */
        $bm = [];

        foreach($_data as $rr)
        {
            $rr = strtoupper($rr);

            //
            // get the type id for the RR
            //
            if (\NetDNS2\ENUM\RR\Type::exists($rr) == true)
            {
                //
                // skip meta types or qtypes
                //
                if (\NetDNS2\ENUM\RR\Type::set($rr)->meta() == true)
                {
                    continue;
                }
            
                $type = \NetDNS2\ENUM\RR\Type::set($rr)->value;

            } else
            {
                //
                // if it's not found, then it must be defined as TYPE<id>, per RFC3845 section 2.2, if it's not, we ignore it.
                //
                if (preg_match('/^TYPE(\d+)$/', $rr, $m) !== 1)
                {
                    continue;
                }

                $type = intval($m[1]);
                if ($type <= 0)
                {
                    continue;
                }
            }

            //
            // window and bit index (pure integer math)
            //
            $window = intdiv($type, 256);
            $val    = $type % 256; // 0..255

            if (isset($bm[$window]) == false)
            {
                $bm[$window] = ['length' => 0, 'bits' => []];
            }

            //
            // mark bit present
            //
            $bm[$window]['bits'][$val] = true;

            //
            // update per-window byte length: ceil((maxBitIndex+1)/8) via integer math
            //
            $needed_len = intdiv(($val + 1) + 7, 8);
            if ($needed_len > $bm[$window]['length'])
            {
                $bm[$window]['length'] = $needed_len;
            }
        }

        $output = '';

        foreach($bm as $window => $bitdata)
        {
            /** @phpstan-var array{length:int, bits: array<int, true>} $bitdata */
            $bitstr = '';
            $n = $bitdata['length'] * 8;

            for ($i=0; $i<$n; $i++)
            {
                $bitstr .= (isset($bitdata['bits'][$i]) == true) ? '1' : '0';
            }

            $output .= pack('CC', intval($window), intval($bitdata['length']));
            $output .= pack('H*', self::bigBaseConvert($bitstr));
        }

        return $output;
    }

    /**
     * a base_convert that handles large numbers; forced to 2/16
     *
     * @param string $_number a bit string
     *
     */
    public static function bigBaseConvert(string $_number): string
    {
        $result = '';

        $bin    = substr(chunk_split(strrev($_number), 4, '-'), 0, -1);
        $temp   = (array)preg_split('[-]', $bin, -1, PREG_SPLIT_DELIM_CAPTURE);

        for($i = count($temp) - 1; $i >= 0; $i--)
        {
            $result = $result . base_convert(strrev(strval($temp[$i])), 2, 16);
        }

        return strtoupper($result);
    }
}
