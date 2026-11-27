# Feature scope

Social login is pre-1.0. It supplies browser login, explicit identity linking, connected-account
management and an opt-in authorization-code API. It does not supply an OAuth authorization server;
that is the OAuth package's role.

| Provider | Current support |
| --- | --- |
| Google, Apple, X, LinkedIn, PayPal | Sign-in/provisioning only with the provider-specific verified-email claim; existing links resolve by provider ID. |
| GitHub | Verified primary email selected with `user:email`; public profile email alone is insufficient. |
| Facebook | Explicit linking first; later sign-in by provider ID. No email-based provisioning or automatic linking. |

See [Provider setup](social-login.md#supported-providers-and-setup) for credentials, callback URLs,
Apple's POST callback and expiring secret, X email access, LinkedIn OpenID and PayPal sandbox mode.

API clients send an authorization code, not an arbitrary provider access token. The client must
secure its own redirect flow; the stateless exchange does not use a browser session's state check.
Facebook Limited Login token exchange is not implemented. Provider credentials and approval remain
application-owned, and mocked tests do not replace a live callback check for your provider app.

Intentional linking requires recent credential confirmation and a single-use attempt. The legacy
implicit-linking switch is deprecated and disabled by default. Unlinking the last provider is
restricted to avoid locking users out. See [Connected accounts](connected-accounts.md).

Public lifecycle hooks are documented in [Events](events.md). Magic links are outside this package
and deferred from the AuthKit 1.0 scope.

---

[← Docs index](../README.md#documentation)
