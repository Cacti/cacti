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
 * LOC Resource Record - RFC1876 section 2
 *
 *      +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *      |        VERSION        |         SIZE          |
 *      +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *      |       HORIZ PRE       |       VERT PRE        |
 *      +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *      |                   LATITUDE                    |
 *      |                                               |
 *      +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *      |                   LONGITUDE                   |
 *      |                                               |
 *      +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *      |                   ALTITUDE                    |
 *      |                                               |
 *      +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 * @property int $version
 * @property string $size
 * @property string $horiz_pre
 * @property string $vert_pre
 * @property float $latitude
 * @property float $longitude
 * @property float $altitude
 */
final class LOC extends \NetDNS2\RR
{
    /**
     * some conversion values
     */
    public const CONV_SEC         = 1000;
    public const CONV_MIN         = 60000;
    public const CONV_DEG         = 3600000;
    public const REFERENCE_ALT    = 10000000;
    public const REFERENCE_LATLON = 2147483648;

    /**
     * the LOC version- should only ever be 0
     */
    protected int $version = 0;

    /**
     * The diameter of a sphere enclosing the described entity
     */
    protected string $size;

    /**
     * The horizontal precision of the data
     */
    protected string $horiz_pre;

    /**
     * The vertical precision of the data
     */
    protected string $vert_pre;

    /**
     * The latitude - stored in decimal degrees
     */
    protected float $latitude;

    /*
     * The longitude - stored in decimal degrees
     */
    protected float $longitude;

    /**
     * The altitude - stored in decimal
     */
    protected float $altitude;

    /**
     * used for quick power-of-ten lookups
     *
     * @var array<float>
     */
    private array $m_power_of_ten = [ 0.01, 0.1, 1, 1e1, 1e2, 1e3, 1e4, 1e5, 1e6, 1e7, 1e8, 0, 0, 0, 0, 0 ];

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return $this->d2Dms($this->latitude, 'LAT') . ' ' . $this->d2Dms($this->longitude, 'LNG') . ' ' . sprintf('%.2fm', $this->altitude) . ' ' .
            sprintf('%.2fm', $this->size) . ' ' . sprintf('%.2fm', $this->horiz_pre) . ' ' . sprintf('%.2fm', $this->vert_pre);
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        //
        // format as defined by RFC1876 section 3
        //
        // d1 [m1 [s1]] {"N"|"S"} d2 [m2 [s2]] {"E"|"W"} alt["m"]
        //      [siz["m"] [hp["m"] [vp["m"]]]]
        //
        if (preg_match('/^(\d+) \s+((\d+) \s+)?(([\d.]+) \s+)?(N|S) \s+(\d+) ' .
            '\s+((\d+) \s+)?(([\d.]+) \s+)?(E|W) \s+(-?[\d.]+) m?(\s+ ([\d.]+) m?)?(\s+ ([\d.]+) m?)?(\s+ ([\d.]+) m?)?/ix', implode(' ', $_rdata), $x) == 1)
        {
            //
            // latitude
            //
            $latdeg     = floatval($x[1]);
            $latmin     = floatval((strlen($x[3]) == 0) ? 0 : $x[3]);
            $latsec     = floatval((strlen($x[5]) == 0) ? 0 : $x[5]);
            $lathem     = strtoupper($x[6]);

            $this->latitude = $this->dms2d($latdeg, $latmin, $latsec, $lathem);

            //
            // longitude
            //
            $londeg     = floatval($x[7]);
            $lonmin     = floatval((strlen($x[9]) == 0) ? 0 : $x[9]);
            $lonsec     = floatval((strlen($x[11]) == 0) ? 0 : $x[11]);
            $lonhem     = strtoupper($x[12]);

            $this->longitude = $this->dms2d($londeg, $lonmin, $lonsec, $lonhem);

            //
            // the rest of teh values
            //
            $version            = 0;

            $this->size         = strval($x[15] ?? 1);
            $this->horiz_pre    = strval($x[17] ?? 10000);
            $this->vert_pre     = strval($x[19] ?? 10);
            $this->altitude     = floatval($x[13]);

            return true;
        }

        return false;
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
        // unpack all the values
        //
        $x = unpack('Cver/Csize/Choriz_pre/Cvert_pre/Nlatitude/Nlongitude/Naltitude', $this->rdata);
        if ($x === false)
        {
            return false;
        }

        //
        // version must be 0 per RFC 1876 section 2
        //
        if (intval($x['ver']) != 0)
        {
            return false;
        }

        $this->version   = intval($x['ver']);
        $this->size      = $this->precision_n_to_a($x['size']);
        $this->horiz_pre = $this->precision_n_to_a($x['horiz_pre']);
        $this->vert_pre  = $this->precision_n_to_a($x['vert_pre']);

        //
        // convert the latitude and longitude to degress in decimal
        //
        if ($x['latitude'] < 0)
        {
            $this->latitude = ($x['latitude'] + self::REFERENCE_LATLON) / self::CONV_DEG;
        } else
        {
            $this->latitude = ($x['latitude'] - self::REFERENCE_LATLON) / self::CONV_DEG;
        }

        if ($x['longitude'] < 0)
        {
            $this->longitude = ($x['longitude'] + self::REFERENCE_LATLON) / self::CONV_DEG;
        } else
        {
            $this->longitude = ($x['longitude'] - self::REFERENCE_LATLON) / self::CONV_DEG;
        }

        //
        // convert down the altitude
        //
        $this->altitude = ($x['altitude'] - self::REFERENCE_ALT) / 100;

        return true;
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        $lat = 0;
        $lng = 0;

        if ($this->latitude < 0)
        {
            $lat = ($this->latitude * self::CONV_DEG) - self::REFERENCE_LATLON;
        } else
        {
            $lat = ($this->latitude * self::CONV_DEG) + self::REFERENCE_LATLON;
        }
        if ($this->longitude < 0)
        {
            $lng = ($this->longitude * self::CONV_DEG) - self::REFERENCE_LATLON;
        } else
        {
            $lng = ($this->longitude * self::CONV_DEG) + self::REFERENCE_LATLON;
        }

        $_packet->offset += 16;

        return pack('CCCCNNN',
            $this->version,
            $this->precision_a_to_n($this->size),
            $this->precision_a_to_n($this->horiz_pre),
            $this->precision_a_to_n($this->vert_pre),
            $lat, $lng,
            ($this->altitude * 100) + self::REFERENCE_ALT
        );
    }

    /**
     * takes an XeY precision/size value, returns a string representation. shamlessly stolen from RFC1876 Appendix A
     *
     * @param integer $_precision the value to convert
     *
     */
    private function precision_n_to_a(int $_precision): string
    {
        $mantissa = $_precision >> 4;

        return strval($mantissa * $this->m_power_of_ten[$_precision & 0x0F]);
    }

    /**
     * converts ascii size/precision X * 10**Y(cm) to 0xXY. shamlessly stolen from RFC1876 Appendix A
     *
     * @param string $_precision the value to convert
     *
     */
    private function precision_a_to_n(string $_precision): int
    {
        $exponent = 0;

        while(intval($_precision) > $this->m_power_of_ten[1 + $exponent])
        {
            $exponent++;
        }

        $mantissa = intval(0.5 + (intval($_precision) / $this->m_power_of_ten[$exponent]));

        return ($mantissa & 0xF) << 4 | $exponent;
    }

    /**
     * convert lat/lng in deg/min/sec/hem to decimal value
     *
     * @param float  $_deg the degree value
     * @param float  $_min the minutes value
     * @param float  $_sec the seconds value
     * @param string $_hem the hemisphere (N/E/S/W)
     *
     */
    private function dms2d(float $_deg, float $_min, float $_sec, string $_hem): float
    {
        $_deg = $_deg - 0;
        $_min = $_min - 0;

        $sign = ($_hem == 'W' || $_hem == 'S') ? -1 : 1;

        return ((($_sec / 60 + $_min) / 60) + $_deg) * $sign;
    }

    /**
     * convert lat/lng in decimal to deg/min/sec/hem
     *
     * @param float  $_data   the decimal value
     * @param string $_latlng either LAT or LNG so we can determine the HEM value
     *
     */
    private function d2Dms(float $_data, string $_latlng): string
    {
        $deg = 0;
        $min = 0;
        $sec = 0;
        $msec = 0;
        $hem = '';

        if ($_latlng == 'LAT')
        {
            $hem = ($_data > 0) ? 'N' : 'S';
        } else
        {
            $hem = ($_data > 0) ? 'E' : 'W';
        }

        $_data = abs($_data);

        $deg = (int)$_data;
        $min = (int)(($_data - $deg) * 60);
        $sec = (int)(((($_data - $deg) * 60) - $min) * 60);
        $msec = round((((((($_data - $deg) * 60) - $min) * 60) - $sec) * 1000));

        return sprintf('%d %02d %02d.%03d %s', $deg, $min, $sec, round($msec), $hem);
    }
}
