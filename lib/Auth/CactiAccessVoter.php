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

declare(strict_types = 1);

namespace Cacti\Auth;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * CactiAccessVoter - a symfony/security-core Voter over Cacti's realm policy.
 *
 * RFC/reference implementation.  This is the proposed single place a realm /
 * page-access decision is expressed, so that the scattered is_realm_allowed()
 * call sites can migrate to one audited chokepoint.
 *
 * IMPORTANT: the voter does NOT re-implement Cacti's permission policy.  It
 * delegates verbatim to the existing is_realm_allowed() function, so the
 * allow/deny outcome is byte-for-byte identical to the legacy call.  All this
 * class adds is the Symfony decision shape (GRANTED / DENIED / ABSTAIN) so the
 * decision can be composed with other voters by an AccessDecisionManager.
 *
 * It implements VoterInterface directly (rather than extending the abstract
 * Voter) to keep the contract minimal and the delegation explicit; the
 * supports()/voteOnAttribute() split mirrors the abstract base's shape.
 */
final class CactiAccessVoter implements VoterInterface {
	/**
	 * Vote GRANTED/DENIED/ABSTAIN on the Cacti subject.
	 *
	 * @param array<int,mixed> $attributes
	 */
	public function vote(TokenInterface $token, mixed $subject, array $attributes): int {
		$vote = self::ACCESS_ABSTAIN;

		foreach ($attributes as $attribute) {
			if (!is_string($attribute) || !$this->supports($attribute, $subject)) {
				continue;
			}

			// A supported attribute was seen: we must return a concrete
			// decision rather than abstain, so default to DENIED and upgrade.
			$vote = self::ACCESS_DENIED;

			if ($this->voteOnAttribute($attribute, $subject, $token)) {
				return self::ACCESS_GRANTED;
			}
		}

		return $vote;
	}

	/**
	 * This voter only decides the realm/page-access attribute, and only when
	 * handed a CactiSubject.  Anything else it abstains on, so unrelated voters
	 * in the same AccessDecisionManager are free to decide.
	 */
	private function supports(string $attribute, mixed $subject): bool {
		return $attribute === CactiSubject::ATTR_REALM && $subject instanceof CactiSubject;
	}

	/**
	 * The actual decision: delegate verbatim to the legacy policy function so
	 * behavior is unchanged.  $token is unused because Cacti identifies the
	 * acting user through the session (or the subject's explicit $check_user),
	 * not through a Symfony security token.
	 */
	private function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
		/** @var CactiSubject $subject (guaranteed by supports()) */
		return is_realm_allowed($subject->getRealm(), $subject->getCheckUser());
	}
}
