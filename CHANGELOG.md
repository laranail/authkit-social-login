# Changelog

All notable changes to `laranail/authkit-social-login` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Social Accounts management page** listing supported providers and showing which accounts are linked. It uses the authkit-preset dashboard when that package is installed and remains usable without preset components otherwise.
- **Automatic migration publishing during installation**, including when no provider is selected, so the `socials` table is available when the account page is used.

- **Web routes.** `social.redirect`, `social.callback` and the authenticated connected-account
  endpoints, configured under `web.*` (enabled, guard, prefix, route-name prefix, middleware,
  after_login, failed_redirect, routes_mode). Each defaults to the preset's value, so an installed
  `laranail/authkit-preset` keeps owning the shape and this package inherits it.
- **A reusable buttons component**, `<x-laranail-authkit-social-login::social-buttons />`, plus the
  five provider icons and a `ui` block for label, icon, class and order. A provider renders only
  when it is both listed in `providers` and has a client ID.
- **An installer**, `php artisan laranail::authkit-social-login.install`, which publishes the config
  and migration, records providers, and writes the OAuth credential and callback variables.

  The callback accepts GET and POST and drops the CSRF middleware, because Apple forces
  `response_mode=form_post` and POSTs from its own servers with no session or token.

- **Unlinking a social account** (`UnlinkSocialAccountInterface`, `SocialAccountService`), with the
  rule that matters: the **last remaining link cannot be removed**.

  The obvious rule — allow it when the user has a password — fails in the dangerous direction.
  `ResolveSocialIdentity` provisions a social account with `Hash::make(Str::random(32))`, a password
  the user has never seen, and Laravel's schema makes the column NOT NULL, so it is populated for
  exactly the accounts most at risk. Nothing in a hash distinguishes a chosen password from a
  generated one, so this package does not guess. An application that records whether a password was
  actually chosen can lift the restriction with
  `laranail.authkit-social.unlink.trust_password_column`.

  `canUnlink()` is exposed separately so a UI can disable the control and explain, rather than
  offering an action that then refuses.

- **Social sign-in for clients with no session** (`AUTHKIT_SOCIAL_API_ENABLED`, off by default).
  `GET /api/auth/social/{provider}/redirect` hands the client a URL to open;
  `POST /api/auth/social/{provider}/callback` takes the returned `code` and issues an API token.

  It takes an authorization **code**, not an access token, and that is the whole design. Socialite's
  `userFromToken()` calls the provider's userinfo endpoint, whose response carries no audience claim
  — so a token minted for a *different* application by the same provider cannot be told apart from
  one minted for this one, and anyone able to obtain one for their own app could present it and be
  signed in as that provider's user. Apple's identity token is no better: the community provider
  constrains issuer, signature and expiry and never adds `PermittedFor`. With the code flow this
  package performs the exchange using its own client secret, and a code issued to another
  application simply fails it.

  Both endpoints run the same `ResolveSocialIdentity` as the browser flow, so the API cannot grant
  what a browser could not, and a failure returns 422 with a deliberately unspecific message.

- **A registered provider can carry its Socialite driver class.** Registering with the registry and
  binding the Socialite driver were separate steps, and nothing warned when the second was forgotten
  — the slug resolved and then Socialite threw at the callback. `IdentityProvider` now takes an
  optional `driverClass` and this package binds it.

  Note the timing: SocialiteProviders dispatches `SocialiteWasCalled` exactly once, from its own
  `app->booted()` callback, so a provider must be registered during some package's `boot()`. That is
  the constraint every SocialiteProviders package works under.

- **Apple as a provider** (`SocialProvider::APPLE`), through `socialiteproviders/apple`. It asserts
  `email_verified` like the other OpenID-style providers, so it can auto-link, and it routinely sends
  that claim as the string `"true"` rather than a boolean — covered by a test, because a stricter
  reader would silently reject every Apple sign-in.

  Apple needs three things the others do not, all documented in `docs/social-login.md`: the
  `client_id` is the Services ID rather than the App ID; the `client_secret` is a short-lived ES256
  JWT that Apple caps at six months and that must be rotated out of band; and because it requests the
  `name` and `email` scopes it replies with `response_mode=form_post`, so it **POSTs** the callback.
  A GET-only or CSRF-protected callback route answers Apple with a 405 or a 419 and the sign-in dies
  silently. `laranail/authkit-preset` registers the route accordingly.

- **Social login, extracted from `laranail/authkit`.** Fifteen classes, the `socials` migration and
  its factory, plus the provider credential block that was `laranail.authkit.social` and is now
  `laranail.authkit-social`. Provider env variable names are unchanged.

  The two abstract controllers gained an explicit
  `use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractAuthController;`. They previously resolved
  it by same-namespace lookup, which the move breaks — silently, because the file still parses.

- Package skeleton: service provider, vendor-scoped config key and publish tag, CI, and the
  naming-convention guard every laranail package carries.

  Unlike its sibling packages, this one is **on by default**
  (`AUTHKIT_SOCIAL_ENABLED`, default `true`). The others are inert placeholders whose installation
  must not change behaviour; this package takes over social login that `laranail/authkit` used to
  provide, so an application upgrading across the extraction already has it configured. Defaulting
  off would disable social login during a routine `composer update`, with nothing reported.

- CI actions pinned to commit SHAs, per the org standard. The four existing authkit siblings still
  float on `@v5` — a separate cleanup.

### Changed

- Pull requests run `composer pint` (Pint with the shared laranail config, check-only) in a new
  *Code style* workflow. `SocialAccountsController` was the one file not already formatted to it.

- The `vcs` repository entries for `laranail/captcha` and `db-tools` are gone. Nothing in this package's
  `require` or `require-dev` closure pulls them in (`composer why` finds none of them installed),
  so they only told Composer to clone repositories it never used. The Packagist exclusion for
  `laranail/*` stays.

- **The config is read at `laranail.authkit-social-login` again, and published to
  `config/laranail/authkit-social-login.php`.** An interim change on `main` (never tagged) had moved
  it to the bare `authkit-social-login` key and `config/authkit-social-login.php`, which sits in
  Laravel's flat config map beside every other package's. The registration now goes through
  package-tools' `hasConfigFile()`, every read goes through `Support\SocialConfig`, and
  `ConfigKeyTest` scans `src/` and `routes/` for a bare read in both the `config('…')` and
  `config(key: '…')` forms. A config published at the bare path is still honoured (see
  *Deprecated*). Provider environment variable names are unchanged.
- **`composer.json` `authors` email is `opensource@simtabi.com`**, the community metadata address,
  replacing `hello@simtabi.com`.

- **Breaking. The package is now `laranail/authkit-social-login`**, matching the repository, which
  was renamed to `laranail/authkit-social-login` while this manifest went on declaring
  `laranail/authkit-social`. Composer resolved it the whole time through GitHub's rename redirect,
  so nothing failed and nothing flagged it — until the redirect is the only thing holding the
  dependency graph together and a new repository under the freed name would silently take its place.

  Every public name derives from the package slug, so all of them move with it:

  | Surface | Before | After |
  |---|---|---|
  | Config key | `laranail.authkit-social` | `laranail.authkit-social-login` |
  | Config file | `config/laranail/authkit-social.php` | `config/laranail/authkit-social-login.php` |
  | Publish tag | `laranail::authkit-social-<suffix>` | `laranail::authkit-social-login-<suffix>` |
  | View namespace | `laranail/authkit-social::` | `laranail/authkit-social-login::` |
  | Translation namespace | `laranail/authkit-social::` | `laranail/authkit-social-login::` |
  | Blade component prefix | `laranail-authkit-social::` | `laranail-authkit-social-login::` |
  | Artisan command | `laranail::authkit-social.<command>` | `laranail::authkit-social-login.<command>` |
  | Middleware alias | `laranail-authkit-social` | `laranail-authkit-social-login` |

  **A published config file does not migrate itself, and it fails silently.** An application that
  ran `vendor:publish` holds `config/laranail/authkit-social.php`; this release reads
  `laranail.authkit-social-login`, so that file stops being consulted and every value in it reverts
  to the packaged default with no error. Re-publish, or rename the file and its key. Provider env
  variable names are unchanged.

  Entries below this one name the old keys because that is what shipped under them; they are left
  as written rather than rewritten into a history that did not happen.

- **`socials.avatar_path` is now `socials.avatar_url`.** It never held a path. The column is written
  from `$socialUser->getAvatar()`, which every provider returns as a remote URL, and nothing ever
  downloaded a file — so the name described an intention that was not implemented, and a reader
  taking it at face value would render it as a local asset. A rename migration ships with it; the
  value is unchanged.

- **`SocialProvider::TWITTER` is now `SocialProvider::X`, value `twitter` → `x`.** Breaking for
  anyone referencing the case, the route value, or the stored `socials.provider` value; the env
  prefix moves from `AUTHKIT_TWITTER_` to `AUTHKIT_X_`. Applications with existing X links need
  `UPDATE socials SET provider = 'x' WHERE provider = 'twitter'`.

  The legacy `twitter` driver key is deliberately not used: it is the OAuth 1.0a fallback described
  above. `x` resolves straight to Socialite's `XProvider`.

- **A provider's Socialite driver key is now separate from its slug.** The slug is stored data — the
  route value and what is written to `socials.provider` — while the driver key belongs to Socialite
  and does change. `SocialProvider::LINKEDIN` keeps the slug `linkedin` and drives
  `linkedin-openid`, and credentials publish under the driver key so the driver actually in use is
  the one that receives them.

- **The email-trust policy moved onto `SocialProvider`.** What was a hardcoded allow-list inside a
  private method is now `assertsEmailVerified()` (does this provider assert verification at all) and
  `hasVerifiedEmail()` (does this payload satisfy that provider's own claim). Both matches are
  exhaustive, so adding a case forces an explicit decision instead of silently inheriting one, and
  adding a provider is a single arm rather than an edit to the resolver.

  Behaviour for Google, LinkedIn, and PayPal is unchanged. A test now pins that a claim carrying the
  wrong key for a provider does not count as verification.

### Deprecated

- **The bare `authkit-social-login` config key**, and the `config/authkit-social-login.php` file it
  loads from. When an application still has it, each key it sets wins over the scoped default (a
  whole-block read shallow-merges it over the scoped block, as `mergeConfigFrom()` did), and the
  provider emits one `E_USER_DEPRECATED` at boot naming the replacement. Republish with
  `php artisan vendor:publish --tag=laranail::authkit-social-login-config`. The earliest release that
  could stop reading it is the next minor after 0.1.

### Removed

- **`SocialProvider::FACEBOOK`, and its config block.** Breaking for anyone referencing the case or
  setting `AUTHKIT_FACEBOOK_*`. Facebook returns no email-verification flag — only an inference from
  its documentation — and an address that cannot be shown to be verified must never auto-link, because
  anyone able to register an account carrying someone else's address would otherwise take over that
  account. It was previously listed as supported while being unable to authenticate at all.

### Fixed

- **The declared floor for `socialiteproviders/manager` did not work.** The manifest said `^4.4`;
  every driver throws `TypeError: array_keys(): Argument #1 must be of type array, string given`
  on anything below 4.10. In 4.9.0 the closure `SocialiteWasCalled::extendSocialite()` hands to
  `$socialite->extend()` calls `$this->buildProvider(...)`, and Laravel's `Manager::extend()`
  rebinds a custom-driver callback to the manager — so `$this` is the `SocialiteManager`, and its
  own two-argument `buildProvider($provider, $config)` receives the provider *name* where a config
  array belongs. 4.10.0 captures `$extendSocialiteBy = $this` first, which is immune to the
  rebinding. Floor raised to `^4.10`.

  Nothing in the suite could see this until `--prefer-lowest` landed: a normal install resolves the
  newest 4.x and passes.

- **A registry-contributed provider could not be signed in with.** `Social::$casts` cast `provider`
  straight to the `SocialProvider` enum, so storing or reading a slug with no enum case threw
  `ValueError: "okta" is not a valid backing value`. A sub-package could register a provider, render
  its button and bind its Socialite driver — and then the first person to actually use it made the
  row unreadable. The seam worked right up to the point of being used.

  `IdentityProviderCast` resolves through the enum first and the registry second, and returns an
  unresolvable slug as a plain string rather than throwing: a sub-package can be removed while its
  rows remain, and such a link should still be listable and unlinkable rather than poisoning the
  whole account.

- **X and LinkedIn could not authenticate at all.** Both shipped behind a green suite because every
  social test fakes the Socialite driver, and the fake honours whatever payload the test supplies —
  so a test asserting "X links when `confirmed_email` is present" passed against a driver that never
  sends one.

  `twitter` resolved to Socialite's **OAuth 1.0a** provider, which builds a League `TwitterServer`
  that wants `identifier`/`secret` and is handed `client_id`/`client_secret`: it threw before
  redirecting, so every X sign-in was a 500 on the first click. `linkedin` resolved to the **legacy**
  LinkedIn driver, whose projection is `id, firstName, lastName, profilePicture` with no
  `email_verified` in it at all, so LinkedIn completed the round trip and then silently refused to
  link or provision.

  A `DriverContractTest` now asserts what Socialite actually resolves for every shipped provider. It
  fails if either regression returns.

- **Configured `scopes` were never applied.** Every provider block declared them and nothing read
  them, so only the driver defaults were ever in effect. They are applied on redirect now, alongside
  a new `with` option — which is what carries Google's `hd` Workspace restriction and
  `prompt=select_account`, neither previously reachable.

- **An unknown provider slug returned a 500, not a 404.** `SocialRedirectAction` and
  `SocialCallbackAction` passed the route parameter straight to `SocialProvider::from()`, so any
  unrecognised value raised an uncaught `ValueError` on user-controlled input. Both now use
  `tryFrom()` and raise `NotFoundHttpException`.

- **X (Twitter) could never sign in.** The verification check only ever read `email_verified`, a claim
  X does not use. X returns the confirmed address itself as `confirmed_email` — Socialite already
  requests it via the `users.email` scope and `user.fields` — so every X login was treated as
  unverified. It is now trusted through its own claim.

[Unreleased]: https://github.com/laranail/authkit-social-login/compare/v0.1.0...HEAD
