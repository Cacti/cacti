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
 * OPT Resource Record - RFC2929 section 3.1
 *
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *    |                          OPTION-CODE                          |
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *    |                         OPTION-LENGTH                         |
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *    |                                                               |
 *    /                          OPTION-DATA                          /
 *    /                                                               /
 *    +---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+---+
 *
 * @property \NetDNS2\ENUM\EDNS\Opt $option_code
 * @property int $option_length
 * @property string $option_data
 * @property int $extended_rcode
 * @property int $version
 * @property int $z
 * @property int $do
 * @property int $co
 */
class OPT extends \NetDNS2\RR
{
    /**
     * option code - assigned by IANA
     */
    protected \NetDNS2\ENUM\EDNS\Opt $option_code = \NetDNS2\ENUM\EDNS\Opt::NONE;

    /**
     * the length of the option data
     */
    protected int $option_length = 0;

    /**
     * the option data
     */
    protected string $option_data = '';

    /**
     * the extended response code stored in the TTL
     */
    protected int $extended_rcode;

    /**
     * the implementation level
     */
    protected int $version;

    /**
     * the extended flags (z)
     */
    protected int $z;

    /**
     * the DO bit used for DNSSEC - RFC3225
     */
    protected int $do;

    /**
     * the CO bit used for compact denial of existence - RFC9824
     */
    protected int $co;

    /**
     * Constructor - builds a new \NetDNS2\RR\OPT object; normally you wouldn't call this directly, but OPT RR's are a little different
     *
     * @param \NetDNS2\Packet &$_packet a \NetDNS2\Packet packet or null to create an empty object
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function __construct(?\NetDNS2\Packet &$_packet = null)
    {
        //
        // this is for when we're manually building an OPT RR object; we aren't
        // passing in binary data to parse, we just want a clean/empty object.
        //
        $this->name           = new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_RFC1035, '');
        $this->type           = \NetDNS2\ENUM\RR\Type::set('OPT');
        $this->class          = \NetDNS2\ENUM\RR\Classes::set('NONE');
        $this->udp_length     = 4000; // default; overridden by Resolver with dnssec_payload_size
        $this->rdlength       = 0;

        $this->option_code    = \NetDNS2\ENUM\EDNS\Opt::NONE;
        $this->option_length  = 0;
        $this->extended_rcode = 0;
        $this->version        = 0;
        $this->z              = 0;
        $this->do             = 0;
        $this->co             = 0;

        //
        // if the current object is not an OPT type, then look it up in the EDNS
        //
        $class = get_class($this);
        if ($class != 'NetDNS2\RR\OPT')
        {
            $this->option_code = \NetDNS2\ENUM\EDNS\Opt::class_id($class);
        }

        //
        // everthing else gets passed through to the parent.
        //
        if (is_null($_packet) == false)
        {
            parent::__construct($_packet);
        }
    }

    /**
     * generate and return an EDNS object based on the stored option code
     */
    public function generate_edns(\NetDNS2\Packet &$_packet): \NetDNS2\RR\OPT
    {
        /**
          * @var \NetDNS2\RR\OPT $opt
          */
        $opt = new ($this->option_code->class())();

        $opt->name          = $this->name;
        $opt->type          = $this->type;
        $opt->class         = $this->class;

        $opt->option_code   = $this->option_code;
        $opt->option_length = $this->option_length;
        $opt->option_data   = $this->option_data;

        $opt->rrSet($_packet);

        return $opt;
    }

    /**
     * @see \NetDNS2\RR::rrToString()
     */
    protected function rrToString(): string
    {
        return '';
    }

    /**
     * @see \NetDNS2\RR::rrFromString()
     */
    protected function rrFromString(array $_rdata): bool
    {
        return true;
    }

    /**
     * @see \NetDNS2\RR::rrSet()
     */
    protected function rrSet(\NetDNS2\Packet &$_packet): bool
    {
        //
        // parse out the TTL value
        //
        $val = unpack('Cx/Cy/nz', pack('N', $this->ttl));
        if ($val === false)
        {
            return false;
        }

        list('x' => $this->extended_rcode, 'y' => $this->version, 'z' => $this->z) = (array)$val;

        $this->do = ($this->z >> 15) & 0x1;
        $this->co = ($this->z >> 1) & 0x1;

        //
        // parse the data, if there is any
        //
        if ($this->rdlength > 0)
        {
            //
            // unpack the code and length
            //
            $val = unpack('ny/nz', $this->rdata);
            if ($val === false)
            {
                return false;
            }

            list('y' => $option_code, 'z' => $this->option_length) = (array)$val;

            $this->option_code = \NetDNS2\ENUM\EDNS\Opt::set($option_code);

            //
            // copy out the data based on the length
            //
            $this->option_data = substr($this->rdata, 4);
        }

        return true;
    }

    /**
     * pre-builds the TTL value for this record; we needed to separate this out from the rrGet() function, as the logic in the \NetDNS2\RR packs the TTL
     * value before it builds the rdata value.
     */
    protected function pre_build(): void
    {
        $this->z = ($this->do << 15) | ($this->co << 1);

        //
        // build the TTL value based on the local values
        //
        $val = unpack('Nz', pack('CCn', $this->extended_rcode, $this->version, $this->z));
        if ($val === false)
        {
            return;
        }

        list('z' => $this->ttl) = (array)$val;
    }

    /**
     * serialises just the OPTION-CODE + OPTION-LENGTH + OPTION-DATA bytes for this option.
     * Called by \NetDNS2\EDNS::build() to merge all options into one OPT record per RFC 6891 §6.1.1.
     *
     * @throws \NetDNS2\Exception
     *
     */
    public function packOption(): string
    {
        $tmp = new \NetDNS2\Packet();

        return $this->rrGet($tmp);
    }

    /**
     * @see \NetDNS2\RR::rrGet()
     */
    protected function rrGet(\NetDNS2\Packet &$_packet): string
    {
        //
        // if this is a merged OPT carrying pre-built combined option bytes, return them directly
        //
        if ( ($this->option_code == \NetDNS2\ENUM\EDNS\Opt::NONE) && (strlen($this->option_data) > 0) )
        {
            $_packet->offset += strlen($this->option_data);

            return $this->option_data;
        }

        if ($this->option_code == \NetDNS2\ENUM\EDNS\Opt::NONE)
        {
            return '';
        }

        $_packet->offset += strlen($this->option_data) + 4;

        return pack('nn', $this->option_code->value, $this->option_length) . $this->option_data;
    }
}
