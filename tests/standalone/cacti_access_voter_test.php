<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Standalone (plain-PHP-assert) test for the CactiAccessVoter RFC.         |
 |                                                                         |
 | Run both paths:                                                         |
 |     php tests/standalone/cacti_access_voter_test.php                    |
 |     CACTI_TEST_NO_SYMFONY=1 php tests/standalone/cacti_access_voter_test.php
 |                                                                         |
 | The first run exercises the full voter + AccessDecisionManager path     |
 | (using minimal in-file doubles for symfony/security-core that preserve  |
 | the real method/constant contract).  The second run forces the          |
 | graceful-degradation fallback by not defining those doubles.            |
 |                                                                         |
 | In every case the helpers must return the SAME decision as the legacy   |
 | is_realm_allowed() function, which is stubbed here so no DB is needed.   |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

namespace {
	define('CACTI_TEST_USE_STUBS', \getenv('CACTI_TEST_NO_SYMFONY') !== '1');

	$GLOBALS['__assert_failures'] = 0;
	$GLOBALS['__assert_count']    = 0;

	/**
	 * Stubbed Cacti policy function. Decisions come from a fixture map keyed
	 * "realm:user" (user is 'cur' when $check_user === false). This is the
	 * ground truth every helper is compared against.
	 */
	function is_realm_allowed(int $realm, int|bool $check_user = false): bool {
		$key = $realm . ':' . ($check_user === false ? 'cur' : (string) $check_user);

		return !empty($GLOBALS['__realm_map'][$key]);
	}

	function t_check(bool $cond, string $msg): void {
		$GLOBALS['__assert_count']++;

		if (!$cond) {
			$GLOBALS['__assert_failures']++;
			fwrite(STDERR, "  FAIL: $msg\n");
		} else {
			fwrite(STDOUT, "  ok:   $msg\n");
		}
	}
}

/* ---- minimal symfony/security-core doubles (contract-preserving) ---- */

namespace Symfony\Component\Security\Core\Authentication\Token {
	if (CACTI_TEST_USE_STUBS && !interface_exists(TokenInterface::class)) {
		interface TokenInterface {
		}
	}

	if (CACTI_TEST_USE_STUBS && !class_exists(NullToken::class)) {
		final class NullToken implements TokenInterface {
		}
	}
}

namespace Symfony\Component\Security\Core\Authorization\Voter {
	use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

	if (CACTI_TEST_USE_STUBS && !interface_exists(VoterInterface::class)) {
		interface VoterInterface {
			public const ACCESS_GRANTED = 1;
			public const ACCESS_ABSTAIN = 0;
			public const ACCESS_DENIED  = -1;

			public function vote(TokenInterface $token, mixed $subject, array $attributes): int;
		}
	}
}

namespace Symfony\Component\Security\Core\Authorization {
	use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
	use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

	if (CACTI_TEST_USE_STUBS && !class_exists(AccessDecisionManager::class)) {
		/** Minimal affirmative-strategy manager, matching the real default. */
		final class AccessDecisionManager {
			private array $voters;

			public function __construct(iterable $voters = array()) {
				$this->voters = is_array($voters) ? $voters : iterator_to_array($voters);
			}

			public function decide(TokenInterface $token, array $attributes, mixed $object = null): bool {
				foreach ($this->voters as $voter) {
					if ($voter->vote($token, $object, $attributes) === VoterInterface::ACCESS_GRANTED) {
						return true;
					}
				}

				return false;
			}
		}
	}
}

/* ---- the test body ---- */

namespace {
	use Cacti\Auth\CactiSubject;

	$base = dirname(__DIR__, 2);

	// Stand in for Composer's PSR-4 autoloader (composer.json maps
	// "Cacti\\Auth\\" => "lib/Auth/"), so the helper can lazily load the voter
	// exactly as it does in production.
	spl_autoload_register(static function (string $class) use ($base): void {
		$prefix = 'Cacti\\Auth\\';

		if (strncmp($class, $prefix, strlen($prefix)) === 0) {
			$relative = substr($class, strlen($prefix));
			$path     = $base . '/lib/Auth/' . str_replace('\\', '/', $relative) . '.php';

			if (is_file($path)) {
				require_once $path;
			}
		}
	});

	require_once $base . '/lib/cacti_access.php';

	// Fixture policy: realm 29 allowed for current user and user 5; denied
	// for realm 17 and for user 7.
	$GLOBALS['__realm_map'] = array(
		'29:cur' => true,
		'17:cur' => false,
		'29:5'   => true,
		'29:7'   => false,
	);

	$manager_available = cacti_access_decision_manager() !== null;

	fwrite(STDOUT, 'MODE: ' . (CACTI_TEST_USE_STUBS ? 'symfony-doubles' : 'no-symfony') .
		' | decision path: ' . ($manager_available ? 'VOTER/AccessDecisionManager' : 'LEGACY FALLBACK') . "\n");

	if (!CACTI_TEST_USE_STUBS) {
		t_check(!$manager_available, 'with no component present, the fallback path is used');
	} else {
		t_check($manager_available, 'with the component present, the voter path is used');
	}

	// Helper decisions must equal the legacy function for every case.
	$cases = array(
		array(29, false, true,  'realm 29, current user -> granted'),
		array(17, false, false, 'realm 17, current user -> denied'),
		array(29, 5,     true,  'realm 29, explicit user 5 -> granted'),
		array(29, 7,     false, 'realm 29, explicit user 7 -> denied'),
	);

	foreach ($cases as [$realm, $user, $expected, $label]) {
		$legacy  = is_realm_allowed($realm, $user);
		$viaReal = cacti_realm_allowed($realm, $user);
		$viaGen  = cacti_access_granted(CactiSubject::ATTR_REALM, new CactiSubject($realm, $user));

		t_check($legacy === $expected, "legacy baseline: $label");
		t_check($viaReal === $legacy,  "cacti_realm_allowed matches legacy: $label");
		t_check($viaGen === $legacy,   "cacti_access_granted matches legacy: $label");
	}

	// Edge case: an unknown attribute must fail closed (deny), not grant.
	$unknown = cacti_access_granted('CACTI_SOMETHING_ELSE', new CactiSubject(29, false));
	t_check($unknown === false, 'unknown attribute fails closed (deny)');

	// When the voter itself is available, assert its raw GRANTED/DENIED/ABSTAIN
	// mapping directly (can only be done when the component/doubles are loaded,
	// since the voter class implements the Symfony VoterInterface).
	if ($manager_available) {
		require_once $base . '/lib/Auth/CactiAccessVoter.php';

		$voter = new Cacti\Auth\CactiAccessVoter();
		$token = new Symfony\Component\Security\Core\Authentication\Token\NullToken();
		$GRANTED = \Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_GRANTED;
		$DENIED  = \Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_DENIED;
		$ABSTAIN = \Symfony\Component\Security\Core\Authorization\Voter\VoterInterface::ACCESS_ABSTAIN;

		$attrs = array(CactiSubject::ATTR_REALM);

		t_check($voter->vote($token, new CactiSubject(29, false), $attrs) === $GRANTED,
			'voter votes GRANTED when legacy allows');
		t_check($voter->vote($token, new CactiSubject(17, false), $attrs) === $DENIED,
			'voter votes DENIED when legacy denies');
		t_check($voter->vote($token, new CactiSubject(29, false), array('OTHER_ATTR')) === $ABSTAIN,
			'voter ABSTAINS on an unrelated attribute');
		t_check($voter->vote($token, new \stdClass(), $attrs) === $ABSTAIN,
			'voter ABSTAINS on a non-Cacti subject');
	}

	$failures = $GLOBALS['__assert_failures'];
	$total    = $GLOBALS['__assert_count'];

	fwrite(STDOUT, "\n" . ($failures === 0 ? 'PASS' : 'FAIL') . ": $total checks, $failures failure(s)\n");

	exit($failures === 0 ? 0 : 1);
}
