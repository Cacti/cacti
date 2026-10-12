<?php

declare(strict_types = 1);

namespace Cacti\Auth;

/**
 * Return the IdP's POST to this origin before Cacti opens its session.
 * The relay preserves Strict cookies; authentication still belongs to the
 * provider's signed-response and InResponseTo validation.
 */
final class SamlPostRelay {
	/** @param array<string, mixed> $query
	 *  @param array<string, mixed> $post */
	public static function isCallbackPost(string $method, array $query, array $post): bool {
		$realm = filter_var($query['realm'] ?? null, FILTER_VALIDATE_INT);

		return $method === 'POST'
			&& in_array($query['action'] ?? '', ['acs', 'callback'], true)
			&& $realm !== false && $realm >= 1000
			&& is_string($post['SAMLResponse'] ?? null)
			&& $post['SAMLResponse'] !== '';
	}

	/** @param array<string, mixed> $query
	 *  @param array<string, mixed> $post */
	public static function relay(array $query, array $post): never {
		$nonce = bin2hex(random_bytes(16));
		header('Content-Type: text/html; charset=UTF-8');
		header('Cache-Control: no-store');
		header('Referrer-Policy: no-referrer');
		header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$nonce'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
		print self::render($query, $post, $nonce);
		exit;
	}

	/** @param array<string, mixed> $query
	 *  @param array<string, mixed> $post */
	public static function render(array $query, array $post, string $nonce): string {
		if (!self::isCallbackPost('POST', $query, $post)) {
			throw new \InvalidArgumentException('Invalid SAML callback');
		}
		$target = 'login_sso.php?action=' . $query['action'] . '&realm=' . (int) $query['realm'];
		$fields = ['SAMLResponse' => (string) $post['SAMLResponse'], 'cacti_saml_relay' => '1'];

		if (is_string($post['RelayState'] ?? null)) {
			$fields['RelayState'] = $post['RelayState'];
		}
		$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$html   = '<!doctype html><html><head><meta charset="UTF-8"></head><body><form id="saml-post-relay" method="POST" action="' . $escape($target) . '">';

		foreach ($fields as $name => $value) {
			$html .= '<input type="hidden" name="' . $escape($name) . '" value="' . $escape($value) . '">';
		}
		// The browser supplies its localized default submit label without a session.
		$html .= '<noscript><input type="submit"></noscript></form><script nonce="' . $escape($nonce) . '">document.getElementById("saml-post-relay").submit();</script></body></html>';

		return $html;
	}
}
