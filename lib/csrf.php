<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * CSRF token issuance and validation for Cacti.
 *
 * Replaces the token core of the vendored csrf-magic fork.  Tokens are random
 * values held in $_SESSION by Symfony rather than HMACs over a secret file.
 * Phase 1 mints every token under a single intention, reproducing csrf-magic's
 * "one token, valid anywhere" behaviour so existing forms and third-party
 * plugins keep working unchanged.
 */
final class CactiCsrfGuard {
	/**
	 * The single token scope used in phase 1.  Per-form intentions arrive in
	 * phase 2; until then every token is minted and checked under this id.
	 */
	public const INTENTION_GLOBAL = 'cacti';

	/**
	 * The POST field name.  Third-party plugins read and write this name, so
	 * it is a compatibility contract and must not change.
	 */
	public const INPUT_NAME = '__csrf_magic';

	/**
	 * csrf-magic's default hash.  It never set csrf_conf('hash'), so every
	 * token Cacti ever issued used sha1.
	 */
	private const LEGACY_HASH = 'sha1';

	private ?CsrfTokenManagerInterface $manager;

	private bool $enabled;

	private string $legacySecret;

	private int $legacyExpiry;

	private string $scriptUrl = '';

	private string $nonce = '';

	private bool $isHtml = false;

	/**
	 * @param CsrfTokenManagerInterface|null $manager      Null when Symfony is not
	 *                                                     available, which happens during a fresh install before composer has
	 *                                                     populated include/vendor.
	 * @param bool                           $enabled      False on the CLI, where there is no session
	 *                                                     and no request to protect.
	 * @param string                         $legacySecret The csrf-magic secret, used only to accept
	 *                                                     tokens minted before the upgrade.  Empty disables the fallback.
	 * @param int                            $legacyExpiry Seconds a legacy token stays acceptable,
	 *                                                     matching csrf-magic's configured expiry.
	 */
	public function __construct(?CsrfTokenManagerInterface $manager = null, bool $enabled = true, string $legacySecret = '', int $legacyExpiry = 7200) {
		$this->manager      = $manager;
		$this->enabled      = $enabled && $manager !== null;
		$this->legacySecret = $legacySecret;
		$this->legacyExpiry = $legacyExpiry;
	}

	/**
	 * @return bool True when tokens will actually be issued and checked.
	 */
	public function isEnabled() : bool {
		return $this->enabled;
	}

	/**
	 * Issue a token for the current session.
	 *
	 * Symfony returns a freshly randomised encoding of one stored secret on
	 * every call, so successive calls return different strings that all
	 * validate.  That is deliberate BREACH mitigation.
	 *
	 * @return string The token, or an empty string when the guard is disabled.
	 */
	public function token() : string {
		if (!$this->enabled) {
			return '';
		}

		return $this->manager->getToken(self::INTENTION_GLOBAL)->getValue();
	}

	/**
	 * @param string $value The submitted token.
	 *
	 * @return bool True only when the token is valid for this session.
	 */
	public function validate(string $value) : bool {
		if (!$this->enabled || $value === '') {
			return false;
		}

		if ($this->manager->isTokenValid(new CsrfToken(self::INTENTION_GLOBAL, $value))) {
			return true;
		}

		return $this->validateLegacyHmac($value);
	}

	/**
	 * @param string $url URL of the XHR-patching script to inject.
	 */
	public function setScriptUrl(string $url) : void {
		$this->scriptUrl = $url;
	}

	/**
	 * Supply a CSP nonce for the scripts this class injects.
	 *
	 * develop currently sends 'unsafe-inline', so this is unused today.  1.2.x
	 * has already moved to nonce-based CSP; when develop follows, the injected
	 * scripts would silently stop running and take every AJAX call with them.
	 *
	 * @param string $nonce The per-request nonce, or an empty string for none.
	 */
	public function setNonce(string $nonce) : void {
		$this->nonce = $nonce;
	}

	/**
	 * Inject the CSRF token into POST forms and the XHR shim into the page.
	 *
	 * The form pattern is carried over from csrf-magic unchanged.  It is
	 * deliberately not widened: every POST is validated, decorated or not, so
	 * a form this pattern misses already fails visibly rather than silently.
	 *
	 * The handler may be called repeatedly for a chunked response, so the
	 * "have we seen <html yet" decision is remembered on the instance.
	 *
	 * @param string $buffer One chunk of output.
	 *
	 * @return string The chunk, rewritten when it is HTML.
	 */
	public function rewriteBuffer(string $buffer) : string {
		if (!$this->enabled) {
			return $buffer;
		}

		if (!$this->isHtml) {
			$this->isHtml = (stripos($buffer, '<html') !== false);
		}

		if (!$this->isHtml) {
			return $buffer;
		}

		$rawToken = $this->token();
		$token    = htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8');
		$name     = self::INPUT_NAME;
		$input    = "<input type='hidden' name='$name' value=\"$token\" />";

		$buffer = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . $input, $buffer);

		if ($this->scriptUrl === '') {
			return $buffer;
		}

		$attr = ($this->nonce !== '' ? ' nonce="' . htmlspecialchars($this->nonce, ENT_QUOTES, 'UTF-8') . '"' : '');
		$url  = htmlspecialchars($this->scriptUrl, ENT_QUOTES, 'UTF-8');

		/*
		 * The hidden field above is an HTML attribute, so htmlspecialchars() is
		 * correct there.  These two values land inside a <script> string
		 * literal instead, a different context that HTML-entity escaping does
		 * not protect; json_encode() with the HEX_* flags is what makes the
		 * token correct by construction rather than safe only because
		 * Symfony's generator happens not to emit quotes today.
		 */
		$jsToken = json_encode($rawToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$jsName  = json_encode($name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		$head = '<script type="text/javascript"' . $attr . '>' .
			'var csrfMagicToken = ' . $jsToken . ';' .
			'var csrfMagicName = ' . $jsName . ';</script>' .
			'<script src="' . $url . '" type="text/javascript"' . $attr . '></script></head>';

		$buffer = str_ireplace('</head>', $head, $buffer);

		$end   = '<script type="text/javascript"' . $attr . '>CsrfMagic.end();</script>';
		$count = 0;

		$buffer = str_ireplace('</body>', $end . '</body>', $buffer, $count);

		if (!$count) {
			$buffer .= $end;
		}

		return $buffer;
	}

	/**
	 * Accept a csrf-magic token minted before the upgrade.
	 *
	 * Only the 'sid:' form is honoured.  Cacti configured csrf-magic in session
	 * mode, so its cookie, key, user and ip branches were unreachable and are
	 * not ported.  The token carries its own issue time, so this path expires
	 * on its own arithmetic and needs no configuration flag.
	 *
	 * Remove this method, and the secret plumbing behind it, once the upgrade
	 * window has passed.  See phase 2 of the design document.
	 *
	 * @param string $value The submitted token.
	 *
	 * @return bool True when the token is a live pre-upgrade token.
	 */
	private function validateLegacyHmac(string $value) : bool {
		if ($this->legacySecret === '' || strpos($value, 'sid:') !== 0) {
			return false;
		}

		$body = substr($value, 4);

		if (strpos($body, ',') === false) {
			return false;
		}

		[$hash, $time] = explode(',', $body, 2);

		if (!ctype_digit($time)) {
			return false;
		}

		if (time() >= (int) $time + $this->legacyExpiry) {
			return false;
		}

		$inner    = hash_hmac(self::LEGACY_HASH, $time . ':' . session_id(), $this->legacySecret);
		$expected = hash_hmac(self::LEGACY_HASH, $inner, $this->legacySecret);

		return hash_equals($expected, $hash);
	}
}
