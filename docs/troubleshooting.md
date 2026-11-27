# Troubleshooting

## Buttons or routes are missing

Confirm the package is enabled, the provider slug is in `providers`, and its client ID is set.
Check `web.enabled` for standalone browser routes and the preset's mounts when installed.
`api.enabled` is a separate opt-in. Clear cached config and inspect `php artisan route:list`.
See [Configuration](configuration.md).

## Provider rejects the callback or code

Register the exact absolute redirect URI, including the configured prefix. Explicit linking uses
`/social/{provider}/connect/callback`, distinct from sign-in's callback. Keep the same URI through
the authorization and token exchange. In browser flows retain session cookies and state; in
stateless clients secure the application callback. See [Provider setup](social-login.md).

## Provider returns an email but sign-in fails

Email-based linking and provisioning require the provider's verified-email claim. GitHub needs
`user:email` and a verified primary address. X needs email access enabled. Facebook requires an
explicit link first; its email does not prove verification. A linked identity can sign in by ID.
Do not weaken these checks to resolve a setup failure.

## Apple callbacks fail

Use the Services ID, a valid signed client-secret JWT and its correct expiry. Apple posts its
callback; retain the package callback's POST support and CSRF exception. Check host cookie/session
settings on the deployed HTTPS domain. Never log authorization codes, secrets or returned tokens.

## Linking or unlinking is refused

Confirm the current password before starting a deliberate connect flow; its attempt is single-use,
short-lived and tied to the original account/authentication context. An identity owned by another
account cannot transfer. Last-provider unlinking is restricted. See [Connected accounts](connected-accounts.md).

## The socials table is missing

Publish `laranail::authkit-social-login-migrations` and run `php artisan migrate`. Migrations are
not automatically loaded. The package queries the table directly; adding a `socials()` model
relation is optional for application code.

---

[← Docs index](../README.md#documentation)
