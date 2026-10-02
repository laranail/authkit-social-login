# Social login

`laranail/authkit-social-login` owns the browser social routes, callbacks, linked-account page, and
Blade button component. Configure which providers to expose, register each callback URL with its
provider, and render `<x-laranail-authkit-social-login::social-buttons />` in your login or
registration view. When used with `authkit-preset`, its login and registration views render that
component automatically and the package routes inherit the preset mounts. The social package can
also be installed and used on its own.

## Supported providers and setup

| Provider | Route value | Provider-console callback                            | Required environment prefix | Verification claim |
|----------|-------------|------------------------------------------------------|-----------------------------|--------------------|
| Google   | `google`    | `https://your-app.test/auth/social/google/callback`  | `AUTHKIT_GOOGLE_`          | `email_verified`   |
| Apple    | `apple`     | `https://your-app.test/auth/social/apple/callback`   | `AUTHKIT_APPLE_`           | `email_verified`   |
| X        | `x`         | `https://your-app.test/auth/social/x/callback`       | `AUTHKIT_X_`               | `confirmed_email`  |
| LinkedIn | `linkedin`  | `https://your-app.test/auth/social/linkedin/callback`| `AUTHKIT_LINKEDIN_`        | `email_verified`   |
| PayPal   | `paypal`    | `https://your-app.test/auth/social/paypal/callback`  | `AUTHKIT_PAYPAL_`          | `email_verified`   |

Every shipped provider asserts that it verified the address it returns, so all five can
auto-link. They do it with different claims: the OpenID-style three return a boolean
`email_verified`, while X returns the confirmed address itself as `confirmed_email` and
omits the field when the address is unconfirmed. Each provider reads its own claim, so a
payload carrying the wrong key never counts as verification.

Facebook is deliberately absent. It returns no verification flag at all — only an
inference from its documentation — and an address that cannot be shown to be verified must
never link, because anyone able to register an account carrying someone else's address
would otherwise take over that account.

Create an OAuth application in the provider's developer console, add the exact callback URL used by your application, then set its credentials. Google, LinkedIn, and PayPal request OpenID, profile, and email scopes.

Apple has three requirements the others do not. Its `client_id` is the **Services ID**, not the App
ID. Its `client_secret` is not a static string but a short-lived ES256 JWT signed with the `.p8` key
from your developer account, which Apple caps at six months — generate it out of band and rotate it,
or Apple sign-in starts failing on a date nothing in your repository records. And because it requests
the `name` and `email` scopes, Apple replies with `response_mode=form_post`, so it **POSTs** the
callback: the route must accept POST and must not require a CSRF token. The package registers it
that way, and inherits the preset mount when used with `laranail/authkit-preset`.

Apple sends the user's name only on the **first** authorization and never again, and may return a
per-app relay address on `@privaterelay.appleid.com`. Apple verifies relay addresses, so they are
trusted; they simply will not match a local account, so in practice they provision rather than link. X requests `users.read`, `users.email`, and `tweet.read`, and returns `confirmed_email` only when "Request email from users" is enabled on the app in X's developer dashboard — without it the address is absent and no X login can link. LinkedIn authenticates through Socialite's `linkedin-openid` driver, not the legacy `linkedin` one, which returns no verification claim at all; the route value stays `linkedin` because it is stored data. Provider approval, app mode, and email-access requirements remain provider-specific.

```env
AUTHKIT_GOOGLE_CLIENT_ID=
AUTHKIT_GOOGLE_CLIENT_SECRET=
AUTHKIT_GOOGLE_REDIRECT="${APP_URL}/auth/social/google/callback"
```

Replace `GOOGLE` with `APPLE`, `X`, `LINKEDIN`, or `PAYPAL` for the other providers. PayPal is sandboxed by default; set `AUTHKIT_PAYPAL_SANDBOX_MODE=false` only when both the callback and credentials are production values. Clear Laravel's configuration cache after changing environment values.

## Routes, controllers, and persistence

Publish the social migration before enabling a provider:

```bash
php artisan vendor:publish --tag=laranail::authkit-social-login-migrations
php artisan migrate
```

The migration creates a polymorphic `socials` table and enforces uniqueness for the provider/provider-user-ID pair. It stores the provider profile fields and access, refresh, and expiry values returned by Socialite. Treat those tokens as sensitive data: restrict database access and do not expose the model directly in an API response.

Add the relation to every authenticatable model that can own a social identity:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Simtabi\Laranail\AuthKit\Models\Social;

public function socials(): MorphMany
{
    return $this->morphMany(Social::class, 'socialable');
}
```

The package registers the redirect, callback, and connected-account routes. Standalone route prefix,
guard, middleware, and names are configured under `web` in `authkit-social-login`; when
the preset is installed these values come from the preset's mounts. The social button component
renders configured providers that have a client ID, and can be customized through the `ui` config.

Publish routes with `laranail::authkit-social-login-routes` and set
`AUTHKIT_SOCIAL_ROUTES_MODE=published` when you want to own the route file. The callback accepts
both GET and POST and bypasses CSRF validation for OAuth provider callbacks.

## Identity resolution and account-linking safety

`ResolveSocialIdentity` uses the following order:

1. An existing provider/provider-ID record is reused and its token metadata is refreshed.
2. If a user is already authenticated, the provider identity is linked to that user.
3. For a guest, a matching local email is linked only when the provider asserted it verified that address.
4. A guest with a trusted verified email and no local account gets a new local account, marked verified, plus a social record.
5. A missing email, an unverified email, or an existing matching account without a verification claim fails the callback; it never silently links the account. An unrecognised provider slug returns a 404.

Trust is declared per provider on the `SocialProvider` enum: `assertsEmailVerified()` says whether a provider asserts verification at all, and `hasVerifiedEmail()` reads that provider's own claim out of the raw payload. Both matches are exhaustive, so adding a case forces an explicit decision rather than inheriting one. This is what keeps a provider's email claim from becoming an account-takeover path.

## Social sign-in without a session

A SPA or native client has no cookie session, so the browser flow does not apply. Enable
`AUTHKIT_SOCIAL_API_ENABLED=true` and two endpoints appear under the core's API prefix:

| Method and path                             | Purpose                                             |
|---------------------------------------------|-----------------------------------------------------|
| `GET /api/auth/social/{provider}/redirect`  | Returns the URL the client should open               |
| `POST /api/auth/social/{provider}/callback` | Takes the returned `code`, returns an API token      |

The client opens the URL in a system browser or web view, the provider redirects back to the URI
registered for the app — a custom URL scheme for native, a page in the SPA — and the client posts the
`code` it received. The redirect URI must match this package's `redirect` config, because the
provider checks it again during the exchange.

### Why this takes a code, not an access token

The obvious shape is "the app signs in with the provider's SDK and posts the access token". That is
**deliberately not offered**, because it cannot be made safe with what the providers return:

- Socialite's `userFromToken()` calls the provider's userinfo endpoint, whose response carries the
  user's identity and **no audience claim**. There is nothing in it to compare against our client id.
- A token is therefore accepted purely because the provider says it is valid — including one minted
  for a **different application** by the same provider. Anyone who can obtain a token for their own
  app can present it and be signed in as that provider's user.
- Even Apple's identity token, a real JWT, is not audience-checked: the community provider constrains
  issuer, signature and expiry and never adds `PermittedFor`.

The authorization-code flow has no such hole. This package performs the exchange with its own client
id and secret, and a code issued to another application fails it. `stateless()` only removes the
session CSRF state check that a client without cookies cannot participate in; the exchange itself is
unchanged.

Both endpoints run the **same** `ResolveSocialIdentity` as the browser flow, so the verification and
account-linking rules are decided in one place and the API can never grant what a browser could not.
A failure returns 422 with a deliberately unspecific message: saying whether an address was
unverified or already taken tells an unauthenticated caller which addresses have accounts.

## Adding a provider

The accepted route values are the `SocialProvider` enum cases. Adding a provider requires a package change: add its enum case with an arm in both `assertsEmailVerified()` and `hasVerifiedEmail()`, add its credentials, redirect, and scopes under `authkit-social-login`, and ensure Socialite has a driver for that key. First-party Socialite drivers work through the normal `services.<provider>` configuration; a third-party driver must be registered with Socialite's extension mechanism, as PayPal is. Add callback tests for an existing identity, a verified email, an unverified or missing email, and authenticated linking before exposing the new provider.

---

[← Docs index](../README.md#documentation)
