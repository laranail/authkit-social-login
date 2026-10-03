# Getting started

Install the package, configure Google, render the buttons, and follow one sign-in from the redirect to the session.

The package is enabled as soon as it is installed: `authkit-social-login.enabled` defaults to `true`
(`AUTHKIT_SOCIAL_ENABLED`), and it registers its own web routes, callback controllers, linked-accounts
page and Blade button component. Set `AUTHKIT_SOCIAL_ENABLED=false` to switch it off.

## 1. Install and migrate

After adding the repositories block described in [installation](installation.md):

```bash
composer require laranail/authkit-social-login
php artisan laranail::authkit-social-login.install --social=google
php artisan migrate
```

The installer publishes `config/authkit-social-login.php` (tag `laranail::authkit-social-login-config`),
writes `'providers' => ['google']` into it, publishes the `socials` migration (tag
`laranail::authkit-social-login-migrations`) unless one is already present, and appends any missing
`AUTHKIT_GOOGLE_*` variables to `.env` and `.env.example` when those files exist. `--social` accepts
`google`, `apple`, `x`, `linkedin` and `paypal`, and can be repeated. Run without it in an
interactive terminal and it asks which providers to enable.

## 2. Add the credentials

Add the generated client ID and secret to `.env`:

```env
AUTHKIT_GOOGLE_CLIENT_ID=
AUTHKIT_GOOGLE_CLIENT_SECRET=
AUTHKIT_GOOGLE_REDIRECT="${APP_URL}/auth/social/google/callback"
```

Then register this callback URL in the provider's developer console:

```text
https://your-app.test/auth/social/google/callback
```

At boot the package copies each provider block from its config into Laravel's `services.*`, which is
where Socialite reads credentials. Clear the configuration cache after changing these values.

## 3. Render the buttons

The package provides a reusable Blade button component. Use it on your own login and registration
views:

```blade
<x-laranail-authkit-social-login::social-buttons />
```

It renders one link per slug in `providers` that has a `client_id` set, so a provider with no
credentials yet produces no button rather than a broken one. Labels, icons, classes and order can be
changed per provider under `ui`; see [configuration](configuration.md).

## 4. What happens on sign-in

Standalone, the package registers these routes under the `web` middleware and the `auth` prefix, with
names prefixed `laranail-social.`:

| Method | Path | Name | Middleware |
|---|---|---|---|
| `GET` | `/auth/social/{provider}` | `laranail-social.social.redirect` | `guest:web` |
| `GET`, `POST` | `/auth/social/{provider}/callback` | `laranail-social.social.callback` | `guest:web`, CSRF excluded |
| `GET` | `/auth/user/social-accounts` | `laranail-social.user-social-accounts.index` | `auth:web` |
| `DELETE` | `/auth/user/social-accounts/{provider}` | `laranail-social.user-social-accounts.destroy` | `auth:web` |

1. The button links to the redirect route, which sends the browser to Google with the scopes and
   `with` parameters from the `google` config block. An unknown `{provider}` is a 404.
2. Google returns to the callback route. It accepts POST and skips CSRF because Apple posts its
   callback; the other providers use GET.
3. The callback reads the Socialite user and runs `ResolveSocialIdentity`: an existing link is reused,
   a verified email matching a local account is linked, and a verified email with no account
   provisions a new verified user. Anything else fails. The full rules are in
   [social login](social-login.md#identity-resolution-and-account-linking-safety).
4. On success the user is logged in on the mount's guard and redirected to the intended URL, falling
   back to `web.after_login` (`/dashboard`, or `AUTHKIT_SOCIAL_AFTER_LOGIN`).
5. On failure the user is redirected to the mount's `login` route when one exists
   (`laranail-social.login` standalone), otherwise to `web.failed_redirect` (`/login`, or
   `AUTHKIT_SOCIAL_FAILED_REDIRECT`), with an error under `email`.

Signed-in users can review and remove linked providers at `/auth/user/social-accounts`; see
[social accounts](connected-accounts.md).

## With authkit-preset

The preset does not install the social package. For the recommended integration, add
`laranail/authkit-social-login` explicitly to your application. The preset login and registration
views detect the package component and render it when available. Social routes automatically use
the preset's configured prefixes, guards, middleware, and route names.

The social package also works without the preset. In that case, configure its `web` route settings
and include its Blade component in your own views.

## Next steps

- Sign-in from a SPA or native client: enable `AUTHKIT_SOCIAL_API_ENABLED` — see
  [social login](social-login.md#social-sign-in-without-a-session).
- Owning the route file: `php artisan laranail::authkit-social-login.install --publish-routes`, then
  set `AUTHKIT_SOCIAL_ROUTES_MODE=published` and load `routes/laranail-authkit-social-login-web.php`
  from your application's route bootstrap.

See [installation](installation.md), [configuration](configuration.md), and
[social login](social-login.md) for provider details and customization.

---

[← Docs index](../README.md#documentation)
