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

namespace NetDNS2\DNSSEC;

/**
 * DNSSEC signature validator.
 *
 * Validates that the RRsets in a DNS response are cryptographically signed
 * by a key chain that leads back to a configured trust anchor.
 *
 * Usage:
 *
 *   $resolver = new \NetDNS2\Resolver(['nameservers' => ['1.1.1.1']]);
 *   $resolver->dnssec = true;
 *
 *   $validator = new \NetDNS2\DNSSEC\Validator($resolver);
 *   $validator->useRootTrustAnchor();
 *
 *   $response = $resolver->query('example.com', 'A');
 *   $validator->validate($response);   // throws \NetDNS2\Exception on failure
 *
 * Phase 1 supports RSA (1/5/7/8/10) and ECDSA (13/14) algorithms.
 * ED25519 (15) is supported via ext-sodium or OpenSSL 1.1.1+.
 * ED448 (16) and GOST algorithms are not supported.
 *
 */
final class Validator
{
    /**
     * resolver used for DNSKEY/DS lookups during chain validation
     */
    private \NetDNS2\Resolver $m_resolver;

    /**
     * configured trust anchors, keyed by key tag
     *
     * @var array<int,\NetDNS2\RR\DS>
     */
    private array $m_trust_anchors = [];

    /**
     * DNSKEY cache to avoid duplicate lookups during chain walks, keyed by zone name
     *
     * @var array<string,list<\NetDNS2\RR\DNSKEY>>
     */
    private array $m_key_cache = [];

    /**
     * Full DNSKEY response cache (answer + RRSIGs); used by validateChain to avoid
     * re-fetching when walking the ZSK → KSK path. Keyed by lowercase zone name.
     *
     * @var array<string,\NetDNS2\Packet\Response>
     */
    private array $m_dnskey_response_cache = [];

    /**
     * @param \NetDNS2\Resolver $_resolver resolver used for DNSKEY and DS queries
     */
    public function __construct(\NetDNS2\Resolver $_resolver)
    {
        $this->m_resolver = $_resolver;
    }

    /**
     * Install the built-in IANA root-zone trust anchors.
     *
     * KSK-2017 (key tag 20326) and KSK-2024 (key tag 38696) are both loaded.
     * Source: https://data.iana.org/root-anchors/root-anchors.xml
     *
     * @throws \NetDNS2\Exception
     */
    public function useRootTrustAnchor(): void
    {
        //
        // KSK-2017 — RSASHA256 / SHA-256
        // Source: IANA root-anchors / /etc/trusted-key.key keytag=20326
        //
        /** @var \NetDNS2\RR\DS $ds */
        $ds = \NetDNS2\RR::fromString(
            '. 0 IN DS 20326 8 2 E06D44B80B8F1D39A95C0B0D7C65D08458E880409BBC683457104237C7F8EC8D'
        );
        $this->m_trust_anchors[20326] = $ds;

        //
        // KSK-2024 — RSASHA256 / SHA-256
        //
        /** @var \NetDNS2\RR\DS $ds */
        $ds = \NetDNS2\RR::fromString(
            '. 0 IN DS 38696 8 2 683D2D0ACB8C9B712A1948B27F741219298D0A450D612C483AF444A4C0FB2B16'
        );
        $this->m_trust_anchors[38696] = $ds;
    }

    /**
     * Add a trust anchor. A DNSKEY anchor is converted to a synthetic DS (SHA-256).
     *
     * @throws \NetDNS2\Exception
     */
    public function addTrustAnchor(\NetDNS2\RR\DS|\NetDNS2\RR\DNSKEY $_anchor): void
    {
        if ($_anchor instanceof \NetDNS2\RR\DNSKEY)
        {
            $off        = 0;
            $owner_wire = (new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, (string)$_anchor->name))->encode($off);
            $digest_hex = $this->dsDigest($_anchor, $owner_wire, \NetDNS2\ENUM\DNSSEC\Digest::SHA256);
            $key_tag    = $this->keyTag($_anchor);

            /** @var \NetDNS2\RR\DS $ds */
            $ds = \NetDNS2\RR::fromString(sprintf(
                '%s 0 IN DS %d %d %d %s',
                (string)$_anchor->name,
                $key_tag,
                $_anchor->algorithm->value,
                \NetDNS2\ENUM\DNSSEC\Digest::SHA256->value,
                strtoupper($digest_hex)
            ));

            $this->m_trust_anchors[$key_tag] = $ds;

        } else
        {
            $this->m_trust_anchors[$_anchor->keytag] = $_anchor;
        }
    }

    /**
     * Validate DNSSEC signatures on all RRsets in the response answer section.
     *
     * Throws \NetDNS2\Exception on the first validation failure.
     * Returns void silently on success.
     *
     * @throws \NetDNS2\Exception
     */
    public function validate(\NetDNS2\Packet\Response $_response): void
    {
        if (count($this->m_trust_anchors) == 0)
        {
            throw new \NetDNS2\Exception(
                'no trust anchors configured; call useRootTrustAnchor() or addTrustAnchor() first.',
                \NetDNS2\ENUM\Error::INT_DNSSEC_NO_ANCHOR
            );
        }

        if (extension_loaded('openssl') == false)
        {
            throw new \NetDNS2\Exception(
                'ext-openssl is required for DNSSEC signature verification.',
                \NetDNS2\ENUM\Error::INT_INVALID_EXTENSION
            );
        }

        //
        // group answer RRs by (owner_name, type), excluding RRSIG entries themselves
        //
        /** @var array<string,list<\NetDNS2\RR>> $rrsets */
        $rrsets = [];

        /** @var list<\NetDNS2\RR\RRSIG> $rrsigs */
        $rrsigs = [];

        foreach ($_response->answer as $rr)
        {
            if ($rr instanceof \NetDNS2\RR\RRSIG)
            {
                $rrsigs[] = $rr;
                continue;
            }

            $key          = strtolower((string)$rr->name) . '|' . $rr->type->value;
            $rrsets[$key][] = $rr;
        }

        foreach ($rrsets as $rrset)
        {
            $rr_sample = reset($rrset);
            if ($rr_sample === false)
            {
                continue;
            }

            $owner = strtolower((string)$rr_sample->name);
            $type  = $rr_sample->type;

            //
            // collect covering RRSIGs from the answer and authority sections
            //
            /** @var list<\NetDNS2\RR\RRSIG> $covering */
            $covering = [];

            foreach ($rrsigs as $sig)
            {
                if ( (strtolower((string)$sig->name) == $owner) && ($sig->typecovered == $type->label()) )
                {
                    $covering[] = $sig;
                }
            }

            foreach ($_response->authority as $rr)
            {
                if ($rr instanceof \NetDNS2\RR\RRSIG)
                {
                    if ( (strtolower((string)$rr->name) == $owner) && ($rr->typecovered == $type->label()) )
                    {
                        $covering[] = $rr;
                    }
                }
            }

            if (count($covering) == 0)
            {
                throw new \NetDNS2\Exception(
                    sprintf('no RRSIG found for %s %s', $owner, $type->label()),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_UNSIGNED
                );
            }

            //
            // try each covering RRSIG; the first that validates passes the RRset
            //
            $last_exception = null;
            $validated      = false;

            foreach ($covering as $rrsig)
            {
                try
                {
                    $this->verifyRRSIG($rrsig, $rrset);
                    $validated = true;
                    break;

                } catch (\NetDNS2\Exception $e)
                {
                    $last_exception = $e;
                }
            }

            if ($validated == false)
            {
                if (is_null($last_exception) == false)
                {
                    throw $last_exception;
                }

                throw new \NetDNS2\Exception(
                    sprintf('all RRSIGs failed for %s %s', $owner, $type->label()),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_BOGUS
                );
            }
        }
    }

    /**
     * Verify a single RRSIG: time window, DNSKEY lookup, signature, and chain.
     *
     * @param list<\NetDNS2\RR> $_rrset
     *
     * @throws \NetDNS2\Exception
     */
    private function verifyRRSIG(\NetDNS2\RR\RRSIG $_rrsig, array $_rrset): void
    {
        //
        // validate the signature time window
        //
        if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $_rrsig->sigexp, $e) !== 1)
        {
            throw new \NetDNS2\Exception('invalid RRSIG sigexp format.', \NetDNS2\ENUM\Error::INT_DNSSEC_TIME);
        }
        if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $_rrsig->sigincep, $i) !== 1)
        {
            throw new \NetDNS2\Exception('invalid RRSIG sigincep format.', \NetDNS2\ENUM\Error::INT_DNSSEC_TIME);
        }

        $sigexp_epoch   = (int)gmmktime(intval($e[4]), intval($e[5]), intval($e[6]), intval($e[2]), intval($e[3]), intval($e[1]));
        $sigincep_epoch = (int)gmmktime(intval($i[4]), intval($i[5]), intval($i[6]), intval($i[2]), intval($i[3]), intval($i[1]));

        $now = time();

        if ( ($now < $sigincep_epoch) || ($now > $sigexp_epoch) )
        {
            throw new \NetDNS2\Exception(
                sprintf(
                    'RRSIG time window violation: now=%d incep=%d exp=%d',
                    $now, $sigincep_epoch, $sigexp_epoch
                ),
                \NetDNS2\ENUM\Error::INT_DNSSEC_TIME
            );
        }

        //
        // fetch the DNSKEY RRset for the signer's zone
        //
        $keys = $this->fetchDNSKEY((string)$_rrsig->signname);

        $dnskey = null;
        foreach ($keys as $k)
        {
            if ( ($this->keyTag($k) == $_rrsig->keytag) && ($k->algorithm == $_rrsig->algorithm) )
            {
                $dnskey = $k;
                break;
            }
        }

        if (is_null($dnskey) == true)
        {
            throw new \NetDNS2\Exception(
                sprintf(
                    'DNSKEY keytag=%d alg=%s not found in zone %s',
                    $_rrsig->keytag, $_rrsig->algorithm->label(), (string)$_rrsig->signname
                ),
                \NetDNS2\ENUM\Error::INT_DNSSEC_NO_KEY
            );
        }

        //
        // build the RFC 4034 §6.2 signed-data block and verify the signature
        //
        $signed_data = $this->signedData($_rrsig, $_rrset);

        if ($this->verifySignature($_rrsig, $dnskey, $signed_data) == false)
        {
            throw new \NetDNS2\Exception(
                sprintf('RRSIG cryptographic verification failed for keytag=%d', $_rrsig->keytag),
                \NetDNS2\ENUM\Error::INT_DNSSEC_BOGUS
            );
        }

        //
        // walk the DNSKEY chain of trust up to the trust anchor
        //
        $this->validateChain($dnskey, (string)$_rrsig->signname);
    }

    /**
     * Build the RFC 4034 §6.2 signed-data block for an RRset and its covering RRSIG.
     *
     * signed_data = rrsig_rdata_without_sig | canonical_rrset
     *
     * @param list<\NetDNS2\RR> $_rrset
     *
     * @throws \NetDNS2\Exception
     */
    private function signedData(\NetDNS2\RR\RRSIG $_rrsig, array $_rrset): string
    {
        //
        // parse the YmdHis timestamps from the RRSIG fields
        //
        if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $_rrsig->sigexp, $e) !== 1)
        {
            throw new \NetDNS2\Exception('invalid RRSIG sigexp format.', \NetDNS2\ENUM\Error::INT_DNSSEC_BOGUS);
        }
        if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $_rrsig->sigincep, $i) !== 1)
        {
            throw new \NetDNS2\Exception('invalid RRSIG sigincep format.', \NetDNS2\ENUM\Error::INT_DNSSEC_BOGUS);
        }

        $sigexp_epoch   = (int)gmmktime(intval($e[4]), intval($e[5]), intval($e[6]), intval($e[2]), intval($e[3]), intval($e[1]));
        $sigincep_epoch = (int)gmmktime(intval($i[4]), intval($i[5]), intval($i[6]), intval($i[2]), intval($i[3]), intval($i[1]));

        //
        // RRSIG header (type covered through key tag) — no signature bytes
        //
        $rrsig_header = pack(
            'nCCNNNn',
            \NetDNS2\ENUM\RR\Type::set($_rrsig->typecovered)->value,
            $_rrsig->algorithm->value,
            $_rrsig->labels,
            $_rrsig->origttl,
            $sigexp_epoch,
            $sigincep_epoch,
            $_rrsig->keytag
        );

        //
        // signer's name in canonical (uncompressed, lowercase) wire form
        //
        $dummy        = 0;
        $rrsig_header .= (new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, (string)$_rrsig->signname))->encode($dummy);

        //
        // build a canonical wire-format entry for each RR in the RRset
        //
        /** @var list<array{entry:string,rdata:string}> $entries */
        $entries = [];

        foreach ($_rrset as $rr)
        {
            $dummy      = 0;
            $owner_wire = (new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, strtolower((string)$rr->name)))->encode($dummy);
            $rdata      = $rr->canonicalRdata();

            $entries[] = [
                'entry' => $owner_wire
                         . pack('nnN', $rr->type->value, $rr->class->value, $_rrsig->origttl)
                         . pack('n', strlen($rdata))
                         . $rdata,
                'rdata' => $rdata,
            ];
        }

        //
        // sort by canonical RDATA (RFC 4034 §6.3: left-justified unsigned octet sequence)
        //
        usort($entries, function(array $a, array $b): int {
            return strcmp((string)$a['rdata'], (string)$b['rdata']);
        });

        //
        // concatenate RRSIG header with sorted RR entries
        //
        $signed_data = $rrsig_header;
        foreach ($entries as $entry)
        {
            $signed_data .= (string)$entry['entry'];
        }

        return $signed_data;
    }

    /**
     * Walk the DNSKEY → DS chain of trust from $_zone up to the root trust anchor.
     *
     * The chain has two cases:
     *  - ZSK (SEP bit not set): does not have a DS in the parent zone; the parent
     *    DS covers the KSK instead.  We find the KSK via the RRSIG on the DNSKEY
     *    RRset, verify it, and recurse with the KSK.  This applies at every level
     *    including the root zone.
     *  - KSK (SEP bit set): has a DS in the parent zone (or is verified against
     *    a configured trust anchor at the root).
     *
     * @throws \NetDNS2\Exception
     */
    private function validateChain(\NetDNS2\RR\DNSKEY $_dnskey, string $_zone): void
    {
        $key_tag    = $this->keyTag($_dnskey);
        $zone_lower = strtolower(rtrim($_zone, '.'));
        $zone_qname = ($zone_lower === '') ? '.' : $zone_lower;

        //
        // ZSK path: the DNSKEY has no DS in the parent zone because the parent DS
        // covers the KSK, not the ZSK.  Walk the DNSKEY RRset to find the KSK
        // (identified by the RRSIG typecovered=DNSKEY), verify the KSK signed it,
        // then recurse with the KSK.  This applies at every zone level.
        //
        if ($_dnskey->sep == false)
        {
            $this->validateChainViaKSK($_zone, $zone_lower, $zone_qname);
            return;
        }

        //
        // KSK path: the DNSKEY has the SEP bit set.
        //

        $dummy      = 0;
        $owner_wire = (new \NetDNS2\Data\Domain(\NetDNS2\Data::DATA_TYPE_CANON, $zone_lower))->encode($dummy);

        //
        // at the root zone, verify the KSK against a configured trust anchor
        //
        if ( ($zone_lower == '') || ($zone_lower == '.') )
        {
            if (isset($this->m_trust_anchors[$key_tag]) == false)
            {
                throw new \NetDNS2\Exception(
                    sprintf('no trust anchor found for root KSK keytag=%d', $key_tag),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
                );
            }

            $anchor   = $this->m_trust_anchors[$key_tag];
            $computed = $this->dsDigest($_dnskey, $owner_wire, $anchor->digesttype);

            if ($computed !== strtolower($anchor->digest))
            {
                throw new \NetDNS2\Exception(
                    sprintf('root trust anchor digest mismatch for keytag=%d', $key_tag),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
                );
            }

            return;
        }

        //
        // non-root zone: find the DS record in the parent zone that covers this KSK
        //
        $dot_pos = strpos($zone_lower, '.');
        $parent  = ($dot_pos === false) ? '' : substr($zone_lower, $dot_pos + 1);

        $prev_dnssec              = $this->m_resolver->dnssec;
        $this->m_resolver->dnssec = true;

        try
        {
            $ds_response = $this->m_resolver->query($zone_lower, 'DS');

        } catch (\NetDNS2\Exception $e)
        {
            $this->m_resolver->dnssec = $prev_dnssec;
            throw new \NetDNS2\Exception(
                sprintf('DS query for zone %s failed: %s', $_zone, $e->getMessage()),
                \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
            );
        }

        $this->m_resolver->dnssec = $prev_dnssec;

        //
        // find the DS record matching this KSK by key tag and algorithm
        //
        $matching_ds = null;
        foreach ($ds_response->answer as $rr)
        {
            if ( ($rr instanceof \NetDNS2\RR\DS) && ($rr->keytag == $key_tag) && ($rr->algorithm == $_dnskey->algorithm) )
            {
                $matching_ds = $rr;
                break;
            }
        }

        if (is_null($matching_ds) == true)
        {
            throw new \NetDNS2\Exception(
                sprintf('no DS record found for KSK keytag=%d in zone %s', $key_tag, $_zone),
                \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
            );
        }

        //
        // verify DNSKEY digest matches the DS record
        //
        $computed = $this->dsDigest($_dnskey, $owner_wire, $matching_ds->digesttype);
        if ($computed !== strtolower($matching_ds->digest))
        {
            throw new \NetDNS2\Exception(
                sprintf('DS digest mismatch for zone %s keytag=%d', $_zone, $key_tag),
                \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
            );
        }

        //
        // collect the DS RRset and its covering RRSIGs from the DS query response
        //
        /** @var list<\NetDNS2\RR> $ds_rrset */
        $ds_rrset = [];

        /** @var list<\NetDNS2\RR\RRSIG> $ds_rrsigs */
        $ds_rrsigs = [];

        foreach ($ds_response->answer as $rr)
        {
            if ($rr instanceof \NetDNS2\RR\RRSIG)
            {
                $ds_rrsigs[] = $rr;

            } else if ($rr instanceof \NetDNS2\RR\DS)
            {
                $ds_rrset[] = $rr;
            }
        }

        foreach ($ds_response->authority as $rr)
        {
            if ($rr instanceof \NetDNS2\RR\RRSIG)
            {
                $ds_rrsigs[] = $rr;
            }
        }

        //
        // find a covering RRSIG for the DS RRset signed by the parent zone
        //
        $ds_owner = strtolower(rtrim((string)$matching_ds->name, '.'));
        $ds_rrsig = null;

        foreach ($ds_rrsigs as $sig)
        {
            if ( (strtolower(rtrim((string)$sig->name, '.')) == $ds_owner) && ($sig->typecovered == 'DS') )
            {
                $ds_rrsig = $sig;
                break;
            }
        }

        if (is_null($ds_rrsig) == true)
        {
            throw new \NetDNS2\Exception(
                sprintf('no RRSIG found for DS RRset in zone %s', $_zone),
                \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
            );
        }

        //
        // verify the DS RRSIG using the parent zone's DNSKEY
        //
        $parent_name = ($parent === '') ? '.' : $parent;
        $parent_keys = $this->fetchDNSKEY($parent_name);

        $parent_dnskey = null;
        foreach ($parent_keys as $k)
        {
            if ( ($this->keyTag($k) == $ds_rrsig->keytag) && ($k->algorithm == $ds_rrsig->algorithm) )
            {
                $parent_dnskey = $k;
                break;
            }
        }

        if (is_null($parent_dnskey) == true)
        {
            throw new \NetDNS2\Exception(
                sprintf('parent DNSKEY keytag=%d not found for zone %s', $ds_rrsig->keytag, $parent_name),
                \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
            );
        }

        $signed_data = $this->signedData($ds_rrsig, $ds_rrset);

        if ($this->verifySignature($ds_rrsig, $parent_dnskey, $signed_data) == false)
        {
            throw new \NetDNS2\Exception(
                sprintf('DS RRSIG verification failed for zone %s', $_zone),
                \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
            );
        }

        //
        // recurse: verify the parent's DNSKEY chain up to the root
        //
        $this->validateChain($parent_dnskey, $parent_name);
    }

    /**
     * ZSK path: find the KSK that signed the DNSKEY RRset for $_zone, verify the
     * signature, and recurse with the KSK via validateChain().
     *
     * Called when the current DNSKEY has sep=false (ZSK) and therefore has no DS
     * record in the parent zone.
     *
     * @throws \NetDNS2\Exception
     */
    private function validateChainViaKSK(string $_zone, string $_zone_lower, string $_zone_qname): void
    {
        $dnskey_response = $this->fetchDNSKEYResponse($_zone_qname);

        //
        // collect the DNSKEY RRset and the covering RRSIG (typecovered=DNSKEY)
        //
        /** @var list<\NetDNS2\RR\DNSKEY> $dnskey_rrset */
        $dnskey_rrset = [];

        /** @var list<\NetDNS2\RR\RRSIG> $dnskey_rrsigs */
        $dnskey_rrsigs = [];

        foreach ($dnskey_response->answer as $rr)
        {
            if ($rr instanceof \NetDNS2\RR\DNSKEY)
            {
                $dnskey_rrset[] = $rr;

            } else if ($rr instanceof \NetDNS2\RR\RRSIG)
            {
                if ($rr->typecovered == 'DNSKEY')
                {
                    $dnskey_rrsigs[] = $rr;
                }
            }
        }

        foreach ($dnskey_response->authority as $rr)
        {
            if ( ($rr instanceof \NetDNS2\RR\RRSIG) && ($rr->typecovered == 'DNSKEY') )
            {
                $dnskey_rrsigs[] = $rr;
            }
        }

        if (count($dnskey_rrsigs) == 0)
        {
            throw new \NetDNS2\Exception(
                sprintf('no RRSIG for DNSKEY RRset in zone %s', $_zone),
                \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
            );
        }

        //
        // try each RRSIG(DNSKEY) until one verifies — zones doing algorithm
        // rollovers publish multiple RRSIGs, one per active KSK.
        //
        $last_exception = null;

        foreach ($dnskey_rrsigs as $dnskey_rrsig)
        {
            //
            // find the KSK whose key tag matches this RRSIG
            //
            /** @var \NetDNS2\RR\DNSKEY|null $ksk */
            $ksk = null;

            foreach ($dnskey_rrset as $k)
            {
                if ( ($this->keyTag($k) == $dnskey_rrsig->keytag) && ($k->algorithm == $dnskey_rrsig->algorithm) )
                {
                    $ksk = $k;
                    break;
                }
            }

            if (is_null($ksk) == true)
            {
                $last_exception = new \NetDNS2\Exception(
                    sprintf('KSK keytag=%d not found in DNSKEY RRset for zone %s', $dnskey_rrsig->keytag, $_zone),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
                );
                continue;
            }

            //
            // guard against infinite recursion: the key that signed the DNSKEY
            // RRset must itself be a KSK (SEP bit set)
            //
            if ($ksk->sep == false)
            {
                $last_exception = new \NetDNS2\Exception(
                    sprintf('DNSKEY RRSIG keytag=%d in zone %s does not identify a KSK', $dnskey_rrsig->keytag, $_zone),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
                );
                continue;
            }

            //
            // verify the time window for the DNSKEY RRSIG
            //
            if ( preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $dnskey_rrsig->sigexp, $ke) !== 1 ||
                 preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $dnskey_rrsig->sigincep, $ki) !== 1 )
            {
                $last_exception = new \NetDNS2\Exception(
                    sprintf('invalid DNSKEY RRSIG time format in zone %s', $_zone),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
                );
                continue;
            }

            $ksk_exp   = (int)gmmktime(intval($ke[4]), intval($ke[5]), intval($ke[6]), intval($ke[2]), intval($ke[3]), intval($ke[1]));
            $ksk_incep = (int)gmmktime(intval($ki[4]), intval($ki[5]), intval($ki[6]), intval($ki[2]), intval($ki[3]), intval($ki[1]));
            $now       = time();

            if ( ($now < $ksk_incep) || ($now > $ksk_exp) )
            {
                $last_exception = new \NetDNS2\Exception(
                    sprintf('DNSKEY RRSIG time window violation in zone %s', $_zone),
                    \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
                );
                continue;
            }

            //
            // verify the DNSKEY RRset cryptographic signature using the KSK
            //
            $ksk_signed_data = $this->signedData($dnskey_rrsig, $dnskey_rrset);

            try
            {
                if ($this->verifySignature($dnskey_rrsig, $ksk, $ksk_signed_data) == false)
                {
                    $last_exception = new \NetDNS2\Exception(
                        sprintf('DNSKEY RRSIG cryptographic verification failed for zone %s', $_zone),
                        \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
                    );
                    continue;
                }

            } catch (\NetDNS2\Exception $e)
            {
                $last_exception = $e;
                continue;
            }

            //
            // this RRSIG verified — recurse with the matching KSK
            //
            $this->validateChain($ksk, $_zone);
            return;
        }

        //
        // all RRSIGs failed
        //
        if (is_null($last_exception) == false)
        {
            throw $last_exception;
        }

        throw new \NetDNS2\Exception(
            sprintf('all DNSKEY RRSIGs failed for zone %s', $_zone),
            \NetDNS2\ENUM\Error::INT_DNSSEC_CHAIN
        );
    }

    /**
     * Fetch and cache DNSKEY records for the given zone name.
     *
     * @return list<\NetDNS2\RR\DNSKEY>
     *
     * @throws \NetDNS2\Exception
     */
    private function fetchDNSKEY(string $_zone): array
    {
        $zone_key = strtolower(rtrim($_zone, '.'));
        $zone_key = ($zone_key === '') ? '.' : $zone_key;

        if (isset($this->m_key_cache[$zone_key]) == true)
        {
            return $this->m_key_cache[$zone_key];
        }

        $response = $this->fetchDNSKEYResponse($zone_key);

        /** @var list<\NetDNS2\RR\DNSKEY> $keys */
        $keys = [];

        foreach ($response->answer as $rr)
        {
            if ($rr instanceof \NetDNS2\RR\DNSKEY)
            {
                $keys[] = $rr;
            }
        }

        $this->m_key_cache[$zone_key] = $keys;

        return $keys;
    }

    /**
     * Fetch and cache the full DNSKEY query response (including RRSIGs) for the
     * given zone.  Used by fetchDNSKEY() and by the ZSK path in validateChain().
     *
     * @throws \NetDNS2\Exception
     */
    private function fetchDNSKEYResponse(string $_zone): \NetDNS2\Packet\Response
    {
        $zone_key = strtolower(rtrim($_zone, '.'));
        $zone_key = ($zone_key === '') ? '.' : $zone_key;

        if (isset($this->m_dnskey_response_cache[$zone_key]) == true)
        {
            return $this->m_dnskey_response_cache[$zone_key];
        }

        $prev_dnssec              = $this->m_resolver->dnssec;
        $this->m_resolver->dnssec = true;

        try
        {
            $response = $this->m_resolver->query($zone_key, 'DNSKEY');

        } catch (\NetDNS2\Exception $e)
        {
            $this->m_resolver->dnssec = $prev_dnssec;
            throw $e;
        }

        $this->m_resolver->dnssec = $prev_dnssec;

        $this->m_dnskey_response_cache[$zone_key] = $response;

        return $response;
    }

    /**
     * Compute the RFC 4034 Appendix B key tag for a DNSKEY RR.
     */
    public function keyTag(\NetDNS2\RR\DNSKEY $_key): int
    {
        //
        // reconstruct the flags value from the boolean fields
        //
        $flags  = 0;
        $flags |= ($_key->zone == true)   ? 0x0100 : 0;
        $flags |= ($_key->sep == true)    ? 0x0001 : 0;
        $flags |= ($_key->revoke == true) ? 0x0080 : 0;

        $wire = pack('nCC', $flags, $_key->protocol, $_key->algorithm->value);

        $decode = base64_decode($_key->key);
        if ($decode !== false)
        {
            $wire .= $decode;
        }

        //
        // key tag algorithm: RFC 4034 Appendix B
        //
        $ac = 0;
        for ($i = 0; $i < strlen($wire); $i++)
        {
            $ac += (($i & 1) == 0) ? (ord($wire[$i]) << 8) : ord($wire[$i]);
        }

        $ac += ($ac >> 16) & 0xffff;

        return $ac & 0xffff;
    }

    /**
     * Compute the DS digest for a DNSKEY; returns a lowercase hex string.
     *
     * Input = owner_wire || flags (2 octets) || protocol (1 octet) || algorithm (1 octet) || public_key
     *
     * @throws \NetDNS2\Exception
     */
    public function dsDigest(\NetDNS2\RR\DNSKEY $_key, string $_owner_wire, \NetDNS2\ENUM\DNSSEC\Digest $_type): string
    {
        $flags  = 0;
        $flags |= ($_key->zone == true)   ? 0x0100 : 0;
        $flags |= ($_key->sep == true)    ? 0x0001 : 0;
        $flags |= ($_key->revoke == true) ? 0x0080 : 0;

        $input = $_owner_wire . pack('nCC', $flags, $_key->protocol, $_key->algorithm->value);

        $decode = base64_decode($_key->key);
        if ($decode !== false)
        {
            $input .= $decode;
        }

        return match($_type)
        {
            \NetDNS2\ENUM\DNSSEC\Digest::SHA1   => sha1($input),
            \NetDNS2\ENUM\DNSSEC\Digest::SHA256 => hash('sha256', $input),
            \NetDNS2\ENUM\DNSSEC\Digest::SHA384 => hash('sha384', $input),
            default => throw new \NetDNS2\Exception(
                sprintf('unsupported DS digest type: %s', $_type->label()),
                \NetDNS2\ENUM\Error::INT_FAILED_OPENSSL
            )
        };
    }

    /**
     * Verify an RRSIG signature against a DNSKEY; returns true if valid.
     *
     * @throws \NetDNS2\Exception
     */
    private function verifySignature(\NetDNS2\RR\RRSIG $_rrsig, \NetDNS2\RR\DNSKEY $_dnskey, string $_data): bool
    {
        $sig_raw = base64_decode($_rrsig->signature);
        if ($sig_raw === false)
        {
            throw new \NetDNS2\Exception(
                'failed to base64_decode RRSIG signature.',
                \NetDNS2\ENUM\Error::INT_DNSSEC_BOGUS
            );
        }

        $key_raw = base64_decode($_dnskey->key);
        if ($key_raw === false)
        {
            throw new \NetDNS2\Exception(
                'failed to base64_decode DNSKEY key.',
                \NetDNS2\ENUM\Error::INT_DNSSEC_BOGUS
            );
        }

        switch ($_rrsig->algorithm)
        {
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSAMD5:
            {
                trigger_error(
                    'RSAMD5 (algorithm 1) is deprecated per RFC 6944; do not use for new deployments.',
                    E_USER_DEPRECATED
                );
                $key = $this->buildRSAKey($key_raw);
                return openssl_verify($_data, $sig_raw, $key, OPENSSL_ALGO_MD5) === 1;
            }
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA1:
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA1NSEC3SHA1:
            {
                $key = $this->buildRSAKey($key_raw);
                return openssl_verify($_data, $sig_raw, $key, OPENSSL_ALGO_SHA1) === 1;
            }
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA256:
            {
                $key = $this->buildRSAKey($key_raw);
                return openssl_verify($_data, $sig_raw, $key, OPENSSL_ALGO_SHA256) === 1;
            }
            case \NetDNS2\ENUM\DNSSEC\Algorithm::RSASHA512:
            {
                $key = $this->buildRSAKey($key_raw);
                return openssl_verify($_data, $sig_raw, $key, OPENSSL_ALGO_SHA512) === 1;
            }
            case \NetDNS2\ENUM\DNSSEC\Algorithm::ECDSAP256SHA256:
            {
                $key = $this->buildECKey($key_raw, 256);
                $sig = $this->rawToDerSignature($sig_raw, 32);
                return openssl_verify($_data, $sig, $key, OPENSSL_ALGO_SHA256) === 1;
            }
            case \NetDNS2\ENUM\DNSSEC\Algorithm::ECDSAP384SHA384:
            {
                $key = $this->buildECKey($key_raw, 384);
                $sig = $this->rawToDerSignature($sig_raw, 48);
                return openssl_verify($_data, $sig, $key, OPENSSL_ALGO_SHA384) === 1;
            }
            case \NetDNS2\ENUM\DNSSEC\Algorithm::ED25519:
            {
                return $this->verifyED25519($sig_raw, $_data, $key_raw);
            }
            case \NetDNS2\ENUM\DNSSEC\Algorithm::ED448:
            {
                throw new \NetDNS2\Exception(
                    'ED448 (algorithm 16) is not supported in Phase 1.',
                    \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM
                );
            }
            default:
            {
                throw new \NetDNS2\Exception(
                    sprintf('unsupported DNSSEC algorithm: %s', $_rrsig->algorithm->label()),
                    \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM
                );
            }
        }
    }

    /**
     * Verify an ED25519 signature using ext-sodium (preferred) or OpenSSL (fallback).
     *
     * @throws \NetDNS2\Exception
     */
    private function verifyED25519(string $_sig, string $_data, string $_key): bool
    {
        if (extension_loaded('sodium') == true)
        {
            if ( ($_sig === '') || ($_key === '') )
            {
                return false;
            }

            return \sodium_crypto_sign_verify_detached($_sig, $_data, $_key);
        }

        //
        // fallback: build a SubjectPublicKeyInfo DER for ED25519 and use openssl_verify
        // OID 1.3.101.112 for id-EdDSA / Ed25519: 06 03 2B 65 70
        //
        $oid_ed25519    = "\x06\x03\x2b\x65\x70";
        $alg_seq        = "\x30" . chr(strlen($oid_ed25519)) . $oid_ed25519;
        $bit_string_val = "\x00" . $_key;
        $bit_string     = "\x03" . chr(strlen($bit_string_val)) . $bit_string_val;
        $spki_inner     = $alg_seq . $bit_string;
        $spki           = "\x30" . $this->derLength(strlen($spki_inner)) . $spki_inner;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($spki), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if ($key === false)
        {
            throw new \NetDNS2\Exception(
                'failed to import ED25519 public key via OpenSSL.',
                \NetDNS2\ENUM\Error::INT_FAILED_OPENSSL
            );
        }

        if (defined('OPENSSL_ALGO_ED25519') == false)
        {
            throw new \NetDNS2\Exception(
                'OPENSSL_ALGO_ED25519 is not defined; OpenSSL 1.1.1+ is required for ED25519.',
                \NetDNS2\ENUM\Error::INT_INVALID_ALGORITHM
            );
        }

        return openssl_verify($_data, $_sig, $key, OPENSSL_ALGO_ED25519) === 1;
    }

    /**
     * Build an OpenSSL RSA public key from raw RFC 3110 bytes (DNSKEY wire format).
     *
     * Format: [exp_len_byte | 0x00 exp_len_word] exponent_bytes modulus_bytes
     *
     * @throws \NetDNS2\Exception
     */
    private function buildRSAKey(string $_raw): \OpenSSLAsymmetricKey
    {
        if (strlen($_raw) < 3)
        {
            throw new \NetDNS2\Exception('RSA key material too short.', \NetDNS2\ENUM\Error::INT_FAILED_OPENSSL);
        }

        //
        // parse RFC 3110 exponent length: byte[0] == 0 means 2-byte length follows
        //
        $exp_len = ord($_raw[0]);
        if ($exp_len == 0)
        {
            $exp_len  = (ord($_raw[1]) << 8) | ord($_raw[2]);
            $exponent = substr($_raw, 3, $exp_len);
            $modulus  = substr($_raw, 3 + $exp_len);
        } else
        {
            $exponent = substr($_raw, 1, $exp_len);
            $modulus  = substr($_raw, 1 + $exp_len);
        }

        //
        // ASN.1 INTEGER values must be positive; prepend 0x00 if the high bit is set
        //
        if ( (strlen($modulus) > 0) && (ord($modulus[0]) >= 0x80) )
        {
            $modulus = "\x00" . $modulus;
        }
        if ( (strlen($exponent) > 0) && (ord($exponent[0]) >= 0x80) )
        {
            $exponent = "\x00" . $exponent;
        }

        //
        // DER RSAPublicKey: SEQUENCE { INTEGER modulus, INTEGER publicExponent }
        //
        $mod_int       = "\x02" . $this->derLength(strlen($modulus)) . $modulus;
        $exp_int       = "\x02" . $this->derLength(strlen($exponent)) . $exponent;
        $rsa_seq_inner = $mod_int . $exp_int;
        $rsa_pub       = "\x30" . $this->derLength(strlen($rsa_seq_inner)) . $rsa_seq_inner;

        //
        // SubjectPublicKeyInfo: SEQUENCE { SEQUENCE { OID rsaEncryption, NULL }, BIT STRING RSAPublicKey }
        // OID 1.2.840.113549.1.1.1
        //
        $oid_rsa          = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";
        $null             = "\x05\x00";
        $alg_seq          = "\x30" . $this->derLength(strlen($oid_rsa . $null)) . $oid_rsa . $null;
        $bit_string_inner = "\x00" . $rsa_pub;
        $bit_string       = "\x03" . $this->derLength(strlen($bit_string_inner)) . $bit_string_inner;
        $spki_inner       = $alg_seq . $bit_string;
        $spki             = "\x30" . $this->derLength(strlen($spki_inner)) . $spki_inner;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($spki), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if ($key === false)
        {
            throw new \NetDNS2\Exception(
                'failed to import RSA public key via OpenSSL.',
                \NetDNS2\ENUM\Error::INT_FAILED_OPENSSL
            );
        }

        return $key;
    }

    /**
     * Build an OpenSSL ECDSA public key from raw (X || Y) key bytes (DNSKEY wire format).
     *
     * @throws \NetDNS2\Exception
     */
    private function buildECKey(string $_raw, int $_curve_bits): \OpenSSLAsymmetricKey
    {
        //
        // EC point in uncompressed form: 0x04 || X || Y
        //
        $ec_point = "\x04" . $_raw;

        //
        // OID 1.2.840.10045.2.1 (id-ecPublicKey)
        //
        $oid_ec_public_key = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";

        if ($_curve_bits == 256)
        {
            //
            // OID 1.2.840.10045.3.1.7 (P-256)
            //
            $oid_curve = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        } else if ($_curve_bits == 384)
        {
            //
            // OID 1.3.132.0.34 (P-384)
            //
            $oid_curve = "\x06\x05\x2b\x81\x04\x00\x22";

        } else
        {
            throw new \NetDNS2\Exception(
                sprintf('unsupported EC curve bit length: %d', $_curve_bits),
                \NetDNS2\ENUM\Error::INT_FAILED_OPENSSL
            );
        }

        $alg_seq          = "\x30" . $this->derLength(strlen($oid_ec_public_key . $oid_curve)) . $oid_ec_public_key . $oid_curve;
        $bit_string_inner = "\x00" . $ec_point;
        $bit_string       = "\x03" . $this->derLength(strlen($bit_string_inner)) . $bit_string_inner;
        $spki_inner       = $alg_seq . $bit_string;
        $spki             = "\x30" . $this->derLength(strlen($spki_inner)) . $spki_inner;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($spki), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if ($key === false)
        {
            throw new \NetDNS2\Exception(
                'failed to import EC public key via OpenSSL.',
                \NetDNS2\ENUM\Error::INT_FAILED_OPENSSL
            );
        }

        return $key;
    }

    /**
     * Convert a raw r||s ECDSA signature to DER SEQUENCE { INTEGER r, INTEGER s }.
     */
    private function rawToDerSignature(string $_raw, int $_part_len): string
    {
        $r = substr($_raw, 0, $_part_len);
        $s = substr($_raw, $_part_len, $_part_len);

        //
        // each integer must be positive in ASN.1; prepend 0x00 if the high bit is set
        //
        if ( (strlen($r) > 0) && (ord($r[0]) >= 0x80) )
        {
            $r = "\x00" . $r;
        }
        if ( (strlen($s) > 0) && (ord($s[0]) >= 0x80) )
        {
            $s = "\x00" . $s;
        }

        $r_int   = "\x02" . $this->derLength(strlen($r)) . $r;
        $s_int   = "\x02" . $this->derLength(strlen($s)) . $s;
        $seq_val = $r_int . $s_int;

        return "\x30" . $this->derLength(strlen($seq_val)) . $seq_val;
    }

    /**
     * Encode an ASN.1 DER variable-length field.
     */
    private function derLength(int $_len): string
    {
        if ($_len < 0x80)
        {
            return chr($_len);
        } else if ($_len <= 0xff)
        {
            return "\x81" . chr($_len);
        } else
        {
            return "\x82" . chr($_len >> 8) . chr($_len & 0xff);
        }
    }
}
