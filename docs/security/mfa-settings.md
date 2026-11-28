# Confirming identity for MFA settings

Enabling, disabling or confirming an authenticator requires a successful authentication within the preceding five minutes. The server binds the evidence to the signed-in Cacti account. When that window expires, a signed-in user can confirm identity from the 2FA tab. Restoring a remembered login permits ordinary navigation and prompts for confirmation on the 2FA tab.

Local and LDAP-backed accounts confirm with their password. OpenID Connect requests an interactive login with a maximum authentication age of zero and reads `auth_time` from the verified ID token. SAML requests `ForceAuthn` and reads `AuthnInstant` from the validated assertion. Both callbacks require the original session, account and realm. Configure synchronized clocks on Cacti and the identity provider. A provider that omits authentication-time evidence cannot complete the challenge. Basic authentication supplies an identity without a challenge time, so its sessions cannot change MFA settings.

Cancelling leaves existing MFA settings intact. After confirming, choose the desired change again. Each save checks the window independently.
