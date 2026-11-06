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

/**
 * cacti_access.php - RFC/reference authorization chokepoint.
 *
 * This file wires CactiAccessVoter behind an AccessDecisionManager and exposes
 * two small procedural helpers that legacy call sites can adopt in place of a
 * bare is_realm_allowed():
 *
 *   cacti_realm_allowed($realm, $check_user)  - drop-in for is_realm_allowed()
 *   cacti_access_granted($attribute, $subject) - the general primitive
 *
 * The goal of the RFC is to give Cacti's scattered permission checks a single,
 * audited place to run, and a composable decision shape, WITHOUT changing the
 * underlying policy.  The actual allow/deny answer still comes from the
 * existing is_realm_allowed() function inside the voter.
 *
 * Graceful degradation: if symfony/security-core is not autoloaded (e.g. a
 * minimal/partial vendor tree), every helper here transparently falls back to
 * calling the legacy function directly, so a site is never left unable to make
 * an authorization decision.
 */

use Cacti\Auth\CactiSubject;

/**
 * Build (once) the AccessDecisionManager with the Cacti voter registered, or
 * return null when symfony/security-core is unavailable.
 *
 * The symfony classes are probed with class_exists()/interface_exists() BEFORE
 * the voter is referenced.  CactiAccessVoter implements the Symfony
 * VoterInterface, so touching it while that interface is absent would be a
 * fatal "interface not found" during autoload rather than a catchable false --
 * hence the ordering: confirm the component, then instantiate the voter.
 *
 * @return object|null An AccessDecisionManager, or null to signal fallback.
 */
function cacti_access_decision_manager(): ?object {
	// false => not resolved yet; null => resolved as "unavailable".
	static $manager = false;

	if ($manager !== false) {
		return $manager;
	}

	$adm_class   = 'Symfony\\Component\\Security\\Core\\Authorization\\AccessDecisionManager';
	$voter_iface = 'Symfony\\Component\\Security\\Core\\Authorization\\Voter\\VoterInterface';
	$token_class = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\NullToken';

	if (class_exists($adm_class) && interface_exists($voter_iface) && class_exists($token_class)) {
		// Safe to autoload the voter now that its parent interface exists.
		$manager = new $adm_class(array(new Cacti\Auth\CactiAccessVoter()));
	} else {
		$manager = null;
	}

	return $manager;
}

/**
 * General authorization primitive: decide a single attribute about a subject.
 *
 * Mirrors AccessDecisionManager::decide() but with graceful degradation.  When
 * the component is present the decision runs through the voter(s); when it is
 * not, it falls back to the legacy policy for the realm/page-access attribute.
 *
 * @param string      $attribute The decision being asked (e.g. CactiSubject::ATTR_REALM).
 * @param CactiSubject $subject   The thing the decision is about.
 */
function cacti_access_granted(string $attribute, CactiSubject $subject): bool {
	$manager = cacti_access_decision_manager();

	if ($manager !== null) {
		$token_class = 'Symfony\\Component\\Security\\Core\\Authentication\\Token\\NullToken';

		// NullToken: Cacti identifies the user via session/$check_user, not a
		// Symfony token, so an unauthenticated placeholder token is correct.
		return (bool) $manager->decide(new $token_class(), array($attribute), $subject);
	}

	// Graceful degradation: identical policy, straight to the legacy function.
	if ($attribute === CactiSubject::ATTR_REALM) {
		return is_realm_allowed($subject->getRealm(), $subject->getCheckUser());
	}

	// Unknown attribute with no decision manager: deny (fail closed).
	return false;
}

/**
 * Thin, drop-in parallel to is_realm_allowed() that routes the check through
 * the voter chokepoint.  Call sites adopt this one line at a time; the outcome
 * (and therefore the surrounding logging/redirect) is unchanged.
 *
 * @param int      $realm      The realm id to check.
 * @param int|bool $check_user The user id, or false for the current session user.
 */
function cacti_realm_allowed(int $realm, int|bool $check_user = false): bool {
	return cacti_access_granted(CactiSubject::ATTR_REALM, new CactiSubject($realm, $check_user));
}
