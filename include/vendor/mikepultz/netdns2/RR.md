# DNS Resource Record Support

This document describes every DNS resource record type known to the NetDNS2 library, organized into three sections:

1. [Supported Resource Records](#supported-resource-records) — fully implemented with wire-format encode/decode, zone-file text parse/format, and PHP class.
2. [Meta / Pseudo-types](#meta--pseudo-types) — types that appear in DNS packets but are not stored resource records (EDNS, zone-transfer control, transaction security, query wildcards).
3. [Unsupported Resource Records](#unsupported-resource-records) — types that appear in the IANA registry but are not implemented, with an explanation for each.

---

## Supported Resource Records

### A — IPv4 Address
| Field | Value |
|-------|-------|
| Type | 1 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\A` |

Maps a hostname to a 32-bit IPv4 address. The most common record type in the DNS.

**Key fields:** `address` (`\NetDNS2\Data\IPv4`)

---

### NS — Name Server
| Field | Value |
|-------|-------|
| Type | 2 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\NS` |

Delegates a DNS zone to use the given authoritative name server.

**Key fields:** `nsdname` (`\NetDNS2\Data\Domain`)

---

### CNAME — Canonical Name
| Field | Value |
|-------|-------|
| Type | 5 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\CNAME` |

Creates an alias from one domain name to another (the canonical name). Resolvers follow CNAME chains automatically.

**Key fields:** `cname` (`\NetDNS2\Data\Domain`)

---

### SOA — Start of Authority
| Field | Value |
|-------|-------|
| Type | 6 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\SOA` |

Marks the beginning of a DNS zone and carries administrative parameters used for zone transfers and negative caching.

**Key fields:** `mname` (primary NS), `rname` (admin mailbox), `serial`, `refresh`, `retry`, `expire`, `minimum`

---

### NULL — Null Record
| Field | Value |
|-------|-------|
| Type | 10 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\RR_NULL` |

Can hold any binary data up to 65,535 bytes. Used experimentally and for testing. The PHP class is named `RR_NULL` (not `NULL`) to avoid a conflict with the PHP reserved word.

**Key fields:** `data` (raw binary string)

---

### WKS — Well-Known Services
| Field | Value |
|-------|-------|
| Type | 11 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\WKS` |

Describes the well-known IP services (TCP/UDP port numbers) that a host provides. Largely superseded by SRV records in modern deployments.

**Key fields:** `address` (IPv4), `protocol` (IP protocol number), `bitmap` (port bitmap)

---

### PTR — Pointer
| Field | Value |
|-------|-------|
| Type | 12 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\PTR` |

Maps an IP address to a hostname (reverse DNS lookup). Used in `in-addr.arpa` (IPv4) and `ip6.arpa` (IPv6) zones.

**Key fields:** `ptrdname` (`\NetDNS2\Data\Domain`)

---

### HINFO — Host Information
| Field | Value |
|-------|-------|
| Type | 13 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\HINFO` |

Describes the CPU and operating system of a host. Rarely published publicly due to security concerns; still used in some private/internal zones.

**Key fields:** `cpu`, `os` (character-string pairs)

---

### MX — Mail Exchanger
| Field | Value |
|-------|-------|
| Type | 15 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\MX` |

Specifies the mail server responsible for accepting email for a domain. Multiple records with different `preference` values allow fallback.

**Key fields:** `preference` (priority, lower = preferred), `exchange` (`\NetDNS2\Data\Domain`)

---

### TXT — Text
| Field | Value |
|-------|-------|
| Type | 16 |
| RFC | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) |
| Class | `\NetDNS2\RR\TXT` |

Holds one or more arbitrary text strings. Widely used for domain ownership verification, SPF policies, DKIM public keys, and many other purposes.

**Key fields:** `text` (array of character strings, each up to 255 bytes)

---

### RP — Responsible Person
| Field | Value |
|-------|-------|
| Type | 17 |
| RFC | [RFC 1183](https://www.rfc-editor.org/rfc/rfc1183) |
| Class | `\NetDNS2\RR\RP` |

Identifies the person responsible for a host, with an optional pointer to a TXT record containing contact details.

**Key fields:** `mbox` (mailbox domain), `txtdname` (TXT record name)

---

### AFSDB — AFS Database Location
| Field | Value |
|-------|-------|
| Type | 18 |
| RFC | [RFC 1183](https://www.rfc-editor.org/rfc/rfc1183) |
| Class | `\NetDNS2\RR\AFSDB` |

Locates an AFS cell database server or a DCE/NCA cell directory server.  Subtype 1 = AFS version 3 cell database server; subtype 2 = DCE authenticated name server.

**Key fields:** `subtype` (1 or 2), `hostname` (`\NetDNS2\Data\Domain`)

---

### X25 — X.25 PSDN Address
| Field | Value |
|-------|-------|
| Type | 19 |
| RFC | [RFC 1183](https://www.rfc-editor.org/rfc/rfc1183) |
| Class | `\NetDNS2\RR\X25` |

Stores the X.25 Public Switched Data Network (PSDN) address for a host.

**Key fields:** `address` (PSDN address string)

---

### ISDN — ISDN Address
| Field | Value |
|-------|-------|
| Type | 20 |
| RFC | [RFC 1183](https://www.rfc-editor.org/rfc/rfc1183) |
| Class | `\NetDNS2\RR\ISDN` |

Stores an ISDN telephone number and optional subaddress for a host.

**Key fields:** `address` (ISDN number), `sa` (optional subaddress)

---

### RT — Route Through
| Field | Value |
|-------|-------|
| Type | 21 |
| RFC | [RFC 1183](https://www.rfc-editor.org/rfc/rfc1183) |
| Class | `\NetDNS2\RR\RT` |

Specifies an intermediate host for routing to destinations that use non-standard addresses (e.g., X.25, ISDN). Analogous to MX but for routing.

**Key fields:** `preference`, `intermediate` (`\NetDNS2\Data\Domain`)

---

### SIG — Cryptographic Signature (legacy)
| Field | Value |
|-------|-------|
| Type | 24 |
| RFC | [RFC 2535](https://www.rfc-editor.org/rfc/rfc2535) |
| Class | `\NetDNS2\RR\SIG` |

The original DNSSEC signature record from RFC 2535. Now superseded by RRSIG (type 46), but still used for SIG(0) transaction authentication on DNS UPDATE messages. The library uses SIG for signing dynamic updates.

**Key fields:** `typecovered`, `algorithm`, `labels`, `origttl`, `sigexp`, `sigincep`, `keytag`, `signname`, `signature`

---

### KEY — Public Key (legacy)
| Field | Value |
|-------|-------|
| Type | 25 |
| RFC | [RFC 2535](https://www.rfc-editor.org/rfc/rfc2535), [RFC 2930](https://www.rfc-editor.org/rfc/rfc2930) |
| Class | `\NetDNS2\RR\KEY` |

The original DNSSEC public key record from RFC 2535, superseded by DNSKEY (type 48). Still used for TKEY key exchange.

**Key fields:** `flags`, `protocol`, `algorithm`, `key`

---

### PX — X.400/RFC 822 Mail Mapping
| Field | Value |
|-------|-------|
| Type | 26 |
| RFC | [RFC 2163](https://www.rfc-editor.org/rfc/rfc2163) |
| Class | `\NetDNS2\RR\PX` |

Maps between RFC 822 (internet) email addresses and X.400 email addresses for gateways.

**Key fields:** `preference`, `map822` (RFC 822 domain), `mapx400` (X.400 OR-address domain)

---

### GPOS — Geographical Position
| Field | Value |
|-------|-------|
| Type | 27 |
| RFC | [RFC 1712](https://www.rfc-editor.org/rfc/rfc1712) |
| Class | `\NetDNS2\RR\GPOS` |

Specifies the geographical position of a host using longitude, latitude, and altitude strings. Superseded by LOC (type 29).

**Key fields:** `longitude`, `latitude`, `altitude`

---

### AAAA — IPv6 Address
| Field | Value |
|-------|-------|
| Type | 28 |
| RFC | [RFC 3596](https://www.rfc-editor.org/rfc/rfc3596) |
| Class | `\NetDNS2\RR\AAAA` |

Maps a hostname to a 128-bit IPv6 address. The IPv6 equivalent of the A record.

**Key fields:** `address` (`\NetDNS2\Data\IPv6`)

---

### LOC — Location Information
| Field | Value |
|-------|-------|
| Type | 29 |
| RFC | [RFC 1876](https://www.rfc-editor.org/rfc/rfc1876) |
| Class | `\NetDNS2\RR\LOC` |

Stores the geographic location (latitude, longitude, altitude, size, horizontal and vertical precision) of an internet resource.

**Key fields:** `latitude`, `longitude`, `altitude`, `size`, `horiz_pre`, `vert_pre`

---

### EID — Endpoint Identifier
| Field | Value |
|-------|-------|
| Type | 31 |
| RFC | Patton 1995 |
| Class | `\NetDNS2\RR\EID` |

Used in the Nimrod routing architecture to store endpoint identifiers. Rarely seen in production.

**Key fields:** `endpoint` (binary hex string)

---

### NIMLOC — Nimrod Locator
| Field | Value |
|-------|-------|
| Type | 32 |
| RFC | Patton 1995 |
| Class | `\NetDNS2\RR\NIMLOC` |

Used in the Nimrod routing architecture to store locators. Rarely seen in production.

**Key fields:** `locator` (binary hex string)

---

### SRV — Service Location
| Field | Value |
|-------|-------|
| Type | 33 |
| RFC | [RFC 2782](https://www.rfc-editor.org/rfc/rfc2782) |
| Class | `\NetDNS2\RR\SRV` |

Specifies the hostname and port for a service. Allows services to be discovered without hard-coding port numbers. Widely used for SIP, XMPP, and many other protocols.

**Key fields:** `priority`, `weight`, `port`, `target` (`\NetDNS2\Data\Domain`)

---

### NAPTR — Naming Authority Pointer
| Field | Value |
|-------|-------|
| Type | 35 |
| RFC | [RFC 2915](https://www.rfc-editor.org/rfc/rfc2915) |
| Class | `\NetDNS2\RR\NAPTR` |

Provides regular-expression-based rewriting of domain names and URIs. Used in ENUM (telephone-number-to-URI mapping) and for service discovery in VoIP.

**Key fields:** `order`, `preference`, `flags`, `services`, `regexp`, `replacement`

---

### KX — Key Exchanger
| Field | Value |
|-------|-------|
| Type | 36 |
| RFC | [RFC 2230](https://www.rfc-editor.org/rfc/rfc2230) |
| Class | `\NetDNS2\RR\KX` |

Identifies hosts willing to act as key exchange intermediaries for a domain, analogous to MX for mail. Used with DNSSEC in some architectures.

**Key fields:** `preference`, `exchanger` (`\NetDNS2\Data\Domain`)

---

### CERT — Certificate
| Field | Value |
|-------|-------|
| Type | 37 |
| RFC | [RFC 4398](https://www.rfc-editor.org/rfc/rfc4398) |
| Class | `\NetDNS2\RR\CERT` |

Stores a certificate (X.509, PGP, SPKI, etc.) or a certificate revocation list (CRL) in the DNS.

**Key fields:** `format`, `key_tag`, `algorithm`, `certificate` (base64)

---

### DNAME — Delegation Name
| Field | Value |
|-------|-------|
| Type | 39 |
| RFC | [RFC 2672](https://www.rfc-editor.org/rfc/rfc2672) |
| Class | `\NetDNS2\RR\DNAME` |

Redirects an entire subtree of the DNS namespace (unlike CNAME, which only redirects a single name). Often used for IPv6 reverse-lookup zones or domain mergers.

**Key fields:** `target` (`\NetDNS2\Data\Domain`)

---

### OPT — EDNS Options
| Field | Value |
|-------|-------|
| Type | 41 |
| RFC | [RFC 2671](https://www.rfc-editor.org/rfc/rfc2671) / [RFC 6891](https://www.rfc-editor.org/rfc/rfc6891) |
| Class | `\NetDNS2\RR\OPT` |

A pseudo-RR (meta-type) that carries EDNS(0) options in the additional section of a DNS message. Not a real resource record — used only for protocol extension signalling. The library supports the following EDNS option sub-types:

| Option | Code | RFC |
|--------|------|-----|
| NSID | 3 | RFC 5001 |
| DAU | 5 | RFC 6975 |
| DHU | 6 | RFC 6975 |
| N3U | 7 | RFC 6975 |
| ECS (Client Subnet) | 8 | RFC 7871 |
| EXPIRE | 9 | RFC 7314 |
| COOKIE | 10 | RFC 7873 |
| KEEPALIVE (TCP Keepalive) | 11 | RFC 7828 |
| PADDING | 12 | RFC 7830 |
| CHAIN | 13 | RFC 7901 |
| KEYTAG | 14 | RFC 8145 |
| UMBRELLA | 20292 | Cisco-internal |
| ZONEVERSION | 19 | RFC 9660 |

---

### APL — Address Prefix List
| Field | Value |
|-------|-------|
| Type | 42 |
| RFC | [RFC 3123](https://www.rfc-editor.org/rfc/rfc3123) |
| Class | `\NetDNS2\RR\APL` |

Stores a list of address prefixes (IPv4 and/or IPv6) with optional negation. Used for Access Control Lists (ACLs) in DNS-based configurations.

**Key fields:** `apl_items` (array of prefix entries, each with address family, prefix length, negation flag, and address)

---

### DS — Delegation Signer
| Field | Value |
|-------|-------|
| Type | 43 |
| RFC | [RFC 4034](https://www.rfc-editor.org/rfc/rfc4034) |
| Class | `\NetDNS2\RR\DS` |

Contains a hash of a DNSKEY record in a child zone, creating the chain of trust in DNSSEC. Published in the parent zone to authenticate the child zone's key.

**Key fields:** `keytag`, `algorithm`, `digesttype`, `digest`

---

### SSHFP — SSH Public Key Fingerprint
| Field | Value |
|-------|-------|
| Type | 44 |
| RFC | [RFC 4255](https://www.rfc-editor.org/rfc/rfc4255) |
| Class | `\NetDNS2\RR\SSHFP` |

Stores the fingerprint of an SSH server's public key in DNS, enabling clients to verify host keys without manual intervention (used with DNSSEC).

**Key fields:** `algorithm` (key type: RSA, DSS, ECDSA, ED25519, etc.), `fingerprint_type` (SHA-1 or SHA-256), `fingerprint`

---

### IPSECKEY — IPsec Key
| Field | Value |
|-------|-------|
| Type | 45 |
| RFC | [RFC 4025](https://www.rfc-editor.org/rfc/rfc4025) |
| Class | `\NetDNS2\RR\IPSECKEY` |

Stores a public key for use with IPsec, allowing hosts to discover IPsec keying material via DNS.

**Key fields:** `precedence`, `gateway_type`, `algorithm`, `gateway` (IPv4/IPv6/domain), `key` (base64)

---

### RRSIG — Resource Record Set Signature
| Field | Value |
|-------|-------|
| Type | 46 |
| RFC | [RFC 4034](https://www.rfc-editor.org/rfc/rfc4034) |
| Class | `\NetDNS2\RR\RRSIG` |

The DNSSEC cryptographic signature for an RRset. Replaces the older SIG record (type 24) for signing zone data.

**Key fields:** `typecovered`, `algorithm`, `labels`, `origttl`, `sigexp`, `sigincep`, `keytag`, `signname`, `signature`

---

### NSEC — Next Secure
| Field | Value |
|-------|-------|
| Type | 47 |
| RFC | [RFC 4034](https://www.rfc-editor.org/rfc/rfc4034) |
| Class | `\NetDNS2\RR\NSEC` |

Used in DNSSEC to provide authenticated denial of existence. Links the signed zone's resource records in canonical order and identifies which record types exist at the owner name.

**Key fields:** `next` (next owner name), `types` (array of type mnemonics)

---

### DNSKEY — DNS Public Key
| Field | Value |
|-------|-------|
| Type | 48 |
| RFC | [RFC 4034](https://www.rfc-editor.org/rfc/rfc4034) |
| Class | `\NetDNS2\RR\DNSKEY` |

Holds the public key used in DNSSEC to verify RRSIG signatures. Zone signing keys (ZSK) and key signing keys (KSK) are both stored as DNSKEY records.

**Key fields:** `flags` (Zone Key flag, SEP bit), `protocol` (must be 3), `algorithm`, `key` (base64)

---

### DHCID — DHCP Identifier
| Field | Value |
|-------|-------|
| Type | 49 |
| RFC | [RFC 4701](https://www.rfc-editor.org/rfc/rfc4701) |
| Class | `\NetDNS2\RR\DHCID` |

Used in the DHCP/DNS interaction protocol to associate a DHCP client with its DNS records, preventing unauthorized updates.

**Key fields:** `id` (base64-encoded identifier)

---

### NSEC3 — Next Secure v3
| Field | Value |
|-------|-------|
| Type | 50 |
| RFC | [RFC 5155](https://www.rfc-editor.org/rfc/rfc5155) |
| Class | `\NetDNS2\RR\NSEC3` |

An improvement over NSEC that uses hashed owner names to prevent zone enumeration while still providing authenticated denial of existence.

**Key fields:** `algorithm`, `flags`, `iterations`, `salt`, `hnxt` (hashed next owner name), `types`

---

### NSEC3PARAM — NSEC3 Parameters
| Field | Value |
|-------|-------|
| Type | 51 |
| RFC | [RFC 5155](https://www.rfc-editor.org/rfc/rfc5155) |
| Class | `\NetDNS2\RR\NSEC3PARAM` |

Published at the zone apex to indicate the NSEC3 hashing parameters (algorithm, flags, iterations, salt) used in the zone.

**Key fields:** `algorithm`, `flags`, `iterations`, `salt`

---

### TLSA — TLS Authentication
| Field | Value |
|-------|-------|
| Type | 52 |
| RFC | [RFC 6698](https://www.rfc-editor.org/rfc/rfc6698) |
| Class | `\NetDNS2\RR\TLSA` |

Associates a TLS server certificate with a domain name (DANE — DNS-based Authentication of Named Entities), allowing clients to verify TLS certificates via DNSSEC rather than traditional CAs.

**Key fields:** `cert_usage`, `selector`, `matching_type`, `certificate` (hex)

---

### SMIMEA — S/MIME Certificate Association
| Field | Value |
|-------|-------|
| Type | 53 |
| RFC | [RFC 8162](https://www.rfc-editor.org/rfc/rfc8162) |
| Class | `\NetDNS2\RR\SMIMEA` |

Associates an S/MIME certificate with an email address via DNS (using DANE), enabling secure email clients to discover encryption certificates automatically.

**Key fields:** `cert_usage`, `selector`, `matching_type`, `certificate` (hex)

---

### HIP — Host Identity Protocol
| Field | Value |
|-------|-------|
| Type | 55 |
| RFC | [RFC 5205](https://www.rfc-editor.org/rfc/rfc5205) |
| Class | `\NetDNS2\RR\HIP` |

Stores HIP parameters: a public key and optionally a list of rendezvous servers, separating the identity of a host from its location (IP address).

**Key fields:** `hit` (host identity tag), `algorithm`, `public_key` (base64), `rendezvous_servers` (array of domains)

---

### TALINK — Trust Anchor Link
| Field | Value |
|-------|-------|
| Type | 58 |
| RFC | — |
| Class | `\NetDNS2\RR\TALINK` |

Used by DNSSEC trust anchor management to link trust anchors. Part of a mechanism for automated trust anchor updates.

**Key fields:** `previous` (domain), `next` (domain)

---

### CDS — Child DS
| Field | Value |
|-------|-------|
| Type | 59 |
| RFC | [RFC 7344](https://www.rfc-editor.org/rfc/rfc7344) |
| Class | `\NetDNS2\RR\CDS` |

Published in a child zone to signal the desired DS record content in the parent zone. Enables automated DNSSEC delegation trust management. Identical wire format to DS.

**Key fields:** `keytag`, `algorithm`, `digesttype`, `digest`

---

### CDNSKEY — Child DNSKEY
| Field | Value |
|-------|-------|
| Type | 60 |
| RFC | [RFC 7344](https://www.rfc-editor.org/rfc/rfc7344) |
| Class | `\NetDNS2\RR\CDNSKEY` |

Published in a child zone to indicate the DNSKEY that the parent should use when constructing a DS record. Companion to CDS. Identical wire format to DNSKEY.

**Key fields:** `flags`, `protocol`, `algorithm`, `key` (base64)

---

### OPENPGPKEY — OpenPGP Public Key
| Field | Value |
|-------|-------|
| Type | 61 |
| RFC | [RFC 7929](https://www.rfc-editor.org/rfc/rfc7929) |
| Class | `\NetDNS2\RR\OPENPGPKEY` |

Stores an OpenPGP public key in the DNS, allowing email clients to discover encryption keys automatically for a given email address.

**Key fields:** `key` (base64-encoded transferable public key packet)

---

### CSYNC — Child-to-Parent Synchronization
| Field | Value |
|-------|-------|
| Type | 62 |
| RFC | [RFC 7477](https://www.rfc-editor.org/rfc/rfc7477) |
| Class | `\NetDNS2\RR\CSYNC` |

Allows a child zone to request that the parent zone synchronize specific record types (e.g., NS glue, A/AAAA records) automatically.

**Key fields:** `serial`, `flags`, `types` (array of type mnemonics)

---

### ZONEMD — Zone Message Digest
| Field | Value |
|-------|-------|
| Type | 63 |
| RFC | [RFC 8976](https://www.rfc-editor.org/rfc/rfc8976) |
| Class | `\NetDNS2\RR\ZONEMD` |

Contains a cryptographic digest of the entire zone's contents, allowing recipients of zone files to verify zone integrity.

**Key fields:** `serial`, `scheme`, `algorithm`, `digest` (hex)

---

### SVCB — Service Binding
| Field | Value |
|-------|-------|
| Type | 64 |
| RFC | [RFC 9460](https://www.rfc-editor.org/rfc/rfc9460) |
| Class | `\NetDNS2\RR\SVCB` |

Provides connection endpoints and associated parameters for internet services, enabling clients to connect more efficiently (e.g., via ALPN negotiation, ECH, IP hints). The HTTPS record type (65) is a variant.

**Key fields:** `priority` (0 = alias mode), `target` (domain), `params` (array of SvcParams: `alpn`, `port`, `ipv4hint`, `ipv6hint`, `ech`, `mandatory`, etc.)

---

### HTTPS — HTTPS Service Binding
| Field | Value |
|-------|-------|
| Type | 65 |
| RFC | [RFC 9460](https://www.rfc-editor.org/rfc/rfc9460) |
| Class | `\NetDNS2\RR\HTTPS` |

An SVCB-compatible record specifically for HTTPS services. Allows browsers and clients to discover HTTPS connection parameters (ALPN, ECH keys, IP hints) via DNS before connecting. Subclasses SVCB.

**Key fields:** Same as SVCB

---

### DSYNC — Generalized Notify
| Field | Value |
|-------|-------|
| Type | 66 |
| RFC | [draft-ietf-dnsop-generalized-notify](https://datatracker.ietf.org/doc/draft-ietf-dnsop-generalized-notify/) |
| Class | `\NetDNS2\RR\DSYNC` |

Provides a mechanism for generalizing DNS NOTIFY messages to support delegation management operations (CSYNC, CDS/CDNSKEY updates) beyond zone transfers.

**Key fields:** `type` (covered type), `scheme`, `port`, `target` (domain)

---

### HHIT — HIP Intermediary Discovery (HIT)
| Field | Value |
|-------|-------|
| Type | 67 |
| RFC | [RFC 9886](https://www.rfc-editor.org/rfc/rfc9886) |
| Class | `\NetDNS2\RR\HHIT` |

Part of the HIP (Host Identity Protocol) Intermediary Discovery mechanism. Stores a HIT (Host Identity Tag) for a HIP intermediary.

**Key fields:** `hit_suite_id`, `hit` (hex)

---

### BRID — HIP Intermediary Discovery (Rendezvous)
| Field | Value |
|-------|-------|
| Type | 68 |
| RFC | [RFC 9886](https://www.rfc-editor.org/rfc/rfc9886) |
| Class | `\NetDNS2\RR\BRID` |

Part of the HIP (Host Identity Protocol) Intermediary Discovery mechanism. Stores the rendezvous server address for a HIP relay.

**Key fields:** `hit_suite_id`, `hit` (hex)

---

### SPF — Sender Policy Framework
| Field | Value |
|-------|-------|
| Type | 99 |
| RFC | [RFC 4408](https://www.rfc-editor.org/rfc/rfc4408) |
| Class | `\NetDNS2\RR\SPF` |

Originally a dedicated record type for SPF email authentication policies. Superseded by TXT records (RFC 7208 removed SPF as a record type); both types carry the same `v=spf1 …` string. SPF type is still encountered in older zones.

**Key fields:** `text` (same format as TXT)

---

### NID — Node Identifier
| Field | Value |
|-------|-------|
| Type | 104 |
| RFC | [RFC 6742](https://www.rfc-editor.org/rfc/rfc6742) |
| Class | `\NetDNS2\RR\NID` |

Part of the ILNP (Identifier-Locator Network Protocol) suite. Stores a 64-bit node identifier for a network node.

**Key fields:** `preference`, `node_id` (64-bit identifier in colon-separated hex)

---

### L32 — 32-bit Locator
| Field | Value |
|-------|-------|
| Type | 105 |
| RFC | [RFC 6742](https://www.rfc-editor.org/rfc/rfc6742) |
| Class | `\NetDNS2\RR\L32` |

Part of the ILNP suite. Stores a 32-bit locator (IPv4-style) identifying a network attachment point.

**Key fields:** `preference`, `locator` (dotted-decimal IPv4 notation)

---

### L64 — 64-bit Locator
| Field | Value |
|-------|-------|
| Type | 106 |
| RFC | [RFC 6742](https://www.rfc-editor.org/rfc/rfc6742) |
| Class | `\NetDNS2\RR\L64` |

Part of the ILNP suite. Stores a 64-bit locator (IPv6-prefix-style) identifying a network attachment point.

**Key fields:** `preference`, `locator` (colon-separated hex)

---

### LP — Locator Pointer
| Field | Value |
|-------|-------|
| Type | 107 |
| RFC | [RFC 6742](https://www.rfc-editor.org/rfc/rfc6742) |
| Class | `\NetDNS2\RR\LP` |

Part of the ILNP suite. Points to a domain name that holds L32 or L64 records, allowing indirection in ILNP locator lookups.

**Key fields:** `preference`, `target` (`\NetDNS2\Data\Domain`)

---

### EUI48 — 48-bit EUI (MAC-48)
| Field | Value |
|-------|-------|
| Type | 108 |
| RFC | [RFC 7043](https://www.rfc-editor.org/rfc/rfc7043) |
| Class | `\NetDNS2\RR\EUI48` |

Stores a 48-bit IEEE EUI (Ethernet MAC address) in the DNS. Intended for specific use cases such as DHCP-DNS mapping; not for general publication.

**Key fields:** `address` (colon-separated hex, e.g., `00:11:22:33:44:55`)

---

### EUI64 — 64-bit EUI
| Field | Value |
|-------|-------|
| Type | 109 |
| RFC | [RFC 7043](https://www.rfc-editor.org/rfc/rfc7043) |
| Class | `\NetDNS2\RR\EUI64` |

Stores a 64-bit IEEE EUI address in the DNS. Same purpose as EUI48 but for 64-bit identifiers.

**Key fields:** `address` (colon-separated hex, e.g., `00:11:22:33:44:55:66:77`)

---

### URI — Uniform Resource Identifier
| Field | Value |
|-------|-------|
| Type | 256 |
| RFC | [RFC 7553](https://www.rfc-editor.org/rfc/rfc7553) |
| Class | `\NetDNS2\RR\URI` |

Maps a hostname (or `_service._proto.hostname`) to a target URI. Allows DNS-based discovery of services via full URIs.

**Key fields:** `priority`, `weight`, `target` (URI string)

---

### CAA — Certification Authority Authorization
| Field | Value |
|-------|-------|
| Type | 257 |
| RFC | [RFC 8659](https://www.rfc-editor.org/rfc/rfc8659) |
| Class | `\NetDNS2\RR\CAA` |

Restricts which certificate authorities (CAs) are authorized to issue TLS certificates for a domain. CAs must check CAA records before issuance.

**Key fields:** `flags`, `tag` (`issue`, `issuewild`, `iodef`), `value`

---

### AVC — Application Visibility and Control
| Field | Value |
|-------|-------|
| Type | 258 |
| RFC | — (Cisco proprietary) |
| Class | `\NetDNS2\RR\AVC` |

A Cisco-specific record type used in Application Visibility and Control deployments. Carries text strings similar to TXT records.

**Key fields:** `text` (array of strings)

---

### AMTRELAY — Automatic Multicast Tunneling Relay
| Field | Value |
|-------|-------|
| Type | 260 |
| RFC | [RFC 8777](https://www.rfc-editor.org/rfc/rfc8777) |
| Class | `\NetDNS2\RR\AMTRELAY` |

Discovers AMT (Automatic Multicast Tunneling) relay addresses via DNS. Clients use AMTRELAY records to find the appropriate AMT relay for a multicast source.

**Key fields:** `precedence`, `discovery_optional` (flag), `relay_type`, `relay` (IPv4/IPv6/domain)

---

### RESINFO — Resolver Information
| Field | Value |
|-------|-------|
| Type | 261 |
| RFC | [RFC 9606](https://www.rfc-editor.org/rfc/rfc9606) |
| Class | `\NetDNS2\RR\RESINFO` |

Published by a DNS resolver to communicate its capabilities and configuration to clients. Uses the same key=value SvcParam format as SVCB.

**Key fields:** SvcParam key-value pairs (e.g., `qnamemin`, `exterr`, `infourl`)

---

### TA — DNSSEC Trust Authority
| Field | Value |
|-------|-------|
| Type | 32768 |
| RFC | — |
| Class | `\NetDNS2\RR\TA` |

Used in the DNSSEC Lookaside Validation (DLV) alternative trust hierarchy. Same wire format as DS.

**Key fields:** `keytag`, `algorithm`, `digesttype`, `digest`

---

### DLV — DNSSEC Lookaside Validation
| Field | Value |
|-------|-------|
| Type | 32769 |
| RFC | [RFC 4431](https://www.rfc-editor.org/rfc/rfc4431) |
| Class | `\NetDNS2\RR\DLV` |

Allows DNSSEC validation in zones whose parent zones do not publish DS records, using a separate lookaside validation tree. Now obsolete since the root zone is signed; the ISC DLV registry was shut down in 2017.

**Key fields:** `keytag`, `algorithm`, `digesttype`, `digest`

---

### TYPE65534 — Private BIND Record
| Field | Value |
|-------|-------|
| Type | 65534 |
| RFC | — |
| Class | `\NetDNS2\RR\TYPE65534` |

An internal private record type used by BIND for its own purposes. Not an IANA-assigned type.

**Key fields:** `data` (raw binary)

---

## Meta / Pseudo-types

These types appear in DNS packet headers or the additional section, but are not stored resource records — they carry protocol control information.

| Type | Number | RFC | Description |
|------|--------|-----|-------------|
| **SIG0** | 0 | [RFC 2931](https://www.rfc-editor.org/rfc/rfc2931) | Transaction-level signature using SIG records. The library uses SIG(0) to sign DNS UPDATE messages. Handled via `\NetDNS2\RR\SIG`. |
| **OPT** | 41 | [RFC 6891](https://www.rfc-editor.org/rfc/rfc6891) | EDNS(0) options container — carried in the additional section, not stored in zone data. See the OPT entry above for the full list of supported EDNS options. |
| **TKEY** | 249 | [RFC 2930](https://www.rfc-editor.org/rfc/rfc2930) | Transaction key establishment for TSIG. Used to negotiate shared secrets for subsequent TSIG-signed requests. |
| **TSIG** | 250 | [RFC 2845](https://www.rfc-editor.org/rfc/rfc2845) | Transaction signature. Signs individual DNS messages using a pre-shared HMAC secret. Supported algorithms: HMAC-MD5, HMAC-SHA1, HMAC-SHA224, HMAC-SHA256, HMAC-SHA384, HMAC-SHA512. |
| **IXFR** | 251 | [RFC 1995](https://www.rfc-editor.org/rfc/rfc1995) | Incremental zone transfer. Only full-zone (AXFR-style) responses to IXFR queries are currently supported. |
| **AXFR** | 252 | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) | Full zone transfer. Fully supported; the library can initiate and receive AXFR responses, optionally authenticated with TSIG or SIG(0). |
| **ANY** | 255 | [RFC 1035](https://www.rfc-editor.org/rfc/rfc1035) | Wildcard query type requesting all record types for a name. Supported as a query type; handled via `\NetDNS2\RR\ANY`. |

---

## Unsupported Resource Records

The following types are registered in the IANA DNS Parameters registry but are not implemented in NetDNS2. The reason for each omission is given below.

### Obsolete types — retired in RFC 1035 itself or replaced early

| Type | Number | RFC | Reason |
|------|--------|-----|--------|
| **MD** | 3 | RFC 1035 | Obsolete. "Mail Destination" — superseded by MX before DNS was widely deployed. RFC 1035 itself marks it obsolete. |
| **MF** | 4 | RFC 1035 | Obsolete. "Mail Forwarder" — superseded by MX at the same time as MD. RFC 1035 marks it obsolete. |
| **MB** | 7 | RFC 1035 | Obsolete. Experimental mailbox record that was never standardized or deployed in practice. |
| **MG** | 8 | RFC 1035 | Obsolete. Experimental mail group record; same story as MB. |
| **MR** | 9 | RFC 1035 | Obsolete. Experimental mail rename record; same story as MB. |
| **MINFO** | 14 | RFC 1035 | Obsolete. Mailbox/mail list information; never widely deployed and officially obsolete. |

### Deprecated types — superseded by better alternatives

| Type | Number | RFC | Reason |
|------|--------|-----|--------|
| **NSAP** | 22 | RFC 1706 | Deprecated. OSI NSAP (Network Service Access Point) addresses; OSI networking never achieved widespread internet adoption. |
| **NSAP-PTR** | 23 | RFC 1348 | Deprecated. Reverse-mapping for NSAP addresses; superseded along with NSAP. |
| **NXT** | 30 | RFC 2065 | Obsolete. The original DNSSEC "Next" record for authenticated denial of existence, replaced by NSEC (type 47) in RFC 3755. |
| **ATMA** | 34 | — | Removed from the IANA registry. ATM (Asynchronous Transfer Mode) networking was never widely integrated with DNS. |
| **A6** | 38 | RFC 2874 | Experimental. An alternative IPv6 address record with complex chain-following semantics; downgraded to experimental status by RFC 3363 and superseded entirely by AAAA (type 28). |
| **GPOS** | 27 | RFC 1712 | Superseded by LOC (type 29). GPOS is still implemented in this library for completeness but LOC is preferred. |
| **SPF** | 99 | RFC 4408 | Functionally replaced by TXT. RFC 7208 removed SPF as a distinct record type; all SPF policies are now published in TXT records. The SPF type is still implemented in this library to handle legacy zones. |
| **MAILB** | 253 | RFC 883 | Obsolete. A meta-query for MB, MG, and MR records — none of which are deployed. |
| **MAILA** | 254 | RFC 973 | Obsolete. A meta-query for MD and MF records — none of which are deployed. |

### Types without a published RFC or stable specification

| Type | Number | Reference | Reason |
|------|--------|-----------|--------|
| **SINK** | 40 | Internet-Draft | An Internet-Draft to store arbitrary "kitchen sink" data was proposed but never published as an RFC. |
| **NINFO** | 56 | Internet-Draft | A proposed record for zone status information; the draft expired and was never standardized. |
| **RKEY** | 57 | Internet-Draft | A proposed resource record for additional DNSSEC keying material; the draft was abandoned. |
| **UINFO** | 100 | — | No RFC. An internal BIND extension for storing user information; not an IANA standard. |
| **UID** | 101 | — | No RFC. An internal BIND extension for user IDs; not an IANA standard. |
| **GID** | 102 | — | No RFC. An internal BIND extension for group IDs; not an IANA standard. |
| **UNSPEC** | 103 | — | No RFC. An internal BIND extension for unspecified data; not an IANA standard. |
| **DOA** | 259 | Internet-Draft | "DNS-based Objects and Attributes" — the draft ([draft-durand-doa-over-dns](https://datatracker.ietf.org/doc/draft-durand-doa-over-dns/)) has not been standardized. |
| **WALLET** | 262 | Internet-Draft | A proposed record for cryptocurrency wallet addresses; not yet standardized. |
| **CLA** | 263 | Internet-Draft | Not yet standardized. |
| **IPN** | 264 | Internet-Draft | Not yet standardized. |

### Partially supported / planned

| Type | Number | RFC | Status |
|------|--------|-----|--------|
| **IXFR** | 251 | [RFC 1995](https://www.rfc-editor.org/rfc/rfc1995) | Only full-zone (AXFR-style) responses are supported. True incremental transfer parsing (delta sections) is not yet implemented. |
| **NXNAME** | 128 | Internet-Draft | Reserved for a new authenticated NXDOMAIN response type; not yet standardized or implemented. |
